<?php

namespace App\Services;

use App\Enums\ActivityActionEnum;
use App\Enums\StockMovementTypeEnum;
use App\Exceptions\CreditLimitExceededException;
use App\Exceptions\SaleAlreadyVoidedException;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\StockMovement;
use App\Repositories\Contracts\CustomerRepositoryInterface;
use App\Repositories\Contracts\DiscountTypeRepositoryInterface;
use App\Repositories\Contracts\SaleRepositoryInterface;
use App\Support\Money;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * The sell loop.
 *
 * Every sale is written in one transaction: stock issued FEFO, costs snapshot
 * from the batches actually drawn, discounts applied per eligibility, payments
 * validated against the total, and any credit portion charged to the suki.
 *
 * The whole thing is keyed on a client-generated UUID. A retried submit — a
 * flaky connection, a double-tap on the pay button — returns the original sale
 * instead of charging a customer twice.
 */
class SaleService extends BaseService
{
    private const MONEY = 2;

    private const QTY = 3;

    public function __construct(
        protected readonly SaleRepositoryInterface $sales,
        protected readonly StockService $stock,
        protected readonly CashDrawerService $drawer,
        protected readonly CustomerRepositoryInterface $customers,
        protected readonly CreditService $credit,
        protected readonly DiscountTypeRepositoryInterface $discountTypes,
        protected readonly ActivityLogger $activity,
    ) {
        parent::__construct($sales);
    }

    /**
     * @param  array{
     *     uuid: string,
     *     branch_id: int,
     *     customer_id?: int|null,
     *     items: list<array{product_id:int, product_unit_id:int, quantity:float|string, line_discount?:float|string}>,
     *     payments: list<array{method:string, amount:float|string, reference_no?:string|null}>,
     *     discounts?: list<array<string, mixed>>,
     *     amount_tendered?: float|string,
     * }  $data
     */
    public function findByUuid(string $uuid): ?Sale
    {
        return $this->sales->findByUuid($uuid);
    }

    /**
     * The last array at the controller→service boundary, and deliberately so.
     *
     * Everything else now takes a `Data` object. `StoreSaleRequest` carries
     * items, payments, discounts and the senior/PWD logbook at once, and this
     * is the till's hot path — the one method whose failure mode is a
     * mis-rung sale. Converting it is its own change with its own tests.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Sale
    {
        // Idempotency first, and outside the transaction: the common retry is
        // a sale that already succeeded, and it should cost one SELECT.
        $existing = $this->sales->findByUuid($data['uuid']);

        if ($existing !== null) {
            return $existing->load($this->relations());
        }

        $branch = Branch::findOrFail($data['branch_id']);
        $session = $this->drawer->requireOpenSession($branch);

        return DB::transaction(function () use ($data, $branch, $session) {
            // Re-check inside the lock: two concurrent submits of the same
            // cart would both miss the check above.
            $existing = $this->sales->lockByUuid($data['uuid']);

            if ($existing !== null) {
                return $existing->load($this->relations());
            }

            $customer = isset($data['customer_id']) && $data['customer_id']
                ? $this->customers->lockFor((int) $data['customer_id'])
                : null;

            $sale = $this->sales->create([
                'uuid' => $data['uuid'],
                'sale_number' => $this->sales->nextSaleNumber($branch),
                'branch_id' => $branch->id,
                'cash_drawer_session_id' => $session->id,
                'user_id' => Auth::id(),
                'customer_id' => $customer?->id,
                'status' => 'completed',
                'sold_at' => now(),
            ]);

            $subtotal = '0.00';

            foreach ($data['items'] as $line) {
                $subtotal = bcadd($subtotal, $this->addItem($sale, $branch, $line), self::MONEY);
            }

            $discountTotal = $this->applyDiscounts($sale, $data['discounts'] ?? []);
            $total = bcsub($subtotal, $discountTotal, self::MONEY);

            $this->recordPayments($sale, $data['payments'], $total, $customer);

            $tendered = $this->money($data['amount_tendered'] ?? $this->cashPortion($data['payments']));
            $cashDue = $this->methodTotal($data['payments'], 'cash');

            $this->sales->update($sale, [
                'subtotal' => $subtotal,
                'discount_total' => $discountTotal,
                'total' => $total,
                'amount_tendered' => $tendered,
                'change_due' => bccomp($tendered, $cashDue, self::MONEY) > 0
                    ? bcsub($tendered, $cashDue, self::MONEY)
                    : '0.00',
                'credit_amount' => $this->methodTotal($data['payments'], 'credit'),
            ]);

            return $sale->refresh()->load($this->relations());
        });
    }

    /**
     * Voiding never deletes. The row flips status and the stock comes back
     * through reversing movements, so the ledger still explains itself.
     */
    public function void(Sale $sale, string $reason, ?int $approvedBy = null): Sale
    {
        return DB::transaction(function () use ($sale, $reason, $approvedBy) {
            if ($sale->isVoided()) {
                throw new SaleAlreadyVoidedException;
            }

            foreach ($sale->items as $item) {
                $this->stock->receive(
                    branch: $sale->branch,
                    product: $item->product,
                    productUnit: $item->productUnit,
                    quantity: $item->quantity,
                    type: StockMovementTypeEnum::SALE_VOID,
                    unitCost: bcmul((string) $item->unit_cost, (string) $item->productUnit->conversion_factor, 4),
                    reference: $sale,
                    note: 'Void: '.$reason,
                );
            }

            // The suki should not still owe for a sale that never happened.
            // A reversing adjustment, not a deletion — the original charge
            // stays on the statement with its reversal beside it.
            if ($sale->customer_id !== null && bccomp((string) $sale->credit_amount, '0', self::MONEY) > 0) {
                $this->credit->adjust(
                    customer: $sale->customer,
                    signedAmount: '-'.$sale->credit_amount,
                    note: 'Void of sale '.$sale->sale_number,
                    approvedBy: $approvedBy,
                );
            }

            $voided = $this->sales->update($sale, [
                'status' => 'voided',
                'voided_at' => now(),
                'voided_by' => Auth::id(),
                'void_reason' => $reason,
                'approved_by' => $approvedBy,
            ]);

            // Inside the transaction, so a rolled-back void leaves no entry
            // claiming a void happened. `Sale` carries no LogsActivity trait —
            // every sale is a create on the till's hot path — so there is no
            // mechanical row to suppress here, only this one to write.
            $this->activity->record(
                ActivityActionEnum::SALE_VOIDED,
                subject: $voided,
                old: ['status' => 'completed'],
                new: ['status' => 'voided'],
                reason: $reason,
                storeId: $voided->branch?->store_id,
            );

            return $voided->load($this->relations());
        });
    }

    /**
     * Issue the stock and record the line, snapshotting what it actually cost.
     *
     * @param  array<string, mixed>  $line
     * @return string the line total
     */
    private function addItem(Sale $sale, Branch $branch, array $line): string
    {
        $product = Product::findOrFail($line['product_id']);
        $productUnit = ProductUnit::findOrFail($line['product_unit_id']);

        $quantity = bcadd((string) $line['quantity'], '0', self::QTY);

        $movements = $this->stock->issue(
            branch: $branch,
            product: $product,
            productUnit: $productUnit,
            quantity: $quantity,
            type: StockMovementTypeEnum::SALE,
            reference: $sale,
        );

        $unitPrice = $this->money($line['unit_price'] ?? $productUnit->selling_price);
        $lineDiscount = $this->money($line['line_discount'] ?? 0);
        $lineTotal = bcsub(bcmul($quantity, $unitPrice, self::MONEY), $lineDiscount, self::MONEY);

        $this->sales->addItem($sale, [
            'product_id' => $product->id,
            'product_unit_id' => $productUnit->id,
            'quantity' => $quantity,
            'quantity_base' => bcmul($quantity, (string) $productUnit->conversion_factor, self::QTY),
            'unit_price' => $unitPrice,
            'unit_cost' => $this->weightedUnitCost($movements),
            'product_name_snapshot' => $product->name,
            'unit_name_snapshot' => $productUnit->unit?->name ?? '',
            'line_discount' => $lineDiscount,
            'line_total' => $lineTotal,
        ]);

        return $lineTotal;
    }

    /**
     * Cost per base unit, weighted by how much came from each batch.
     *
     * `StockService::issue()` returns one movement per batch drawn. Fifteen
     * sachets taken as ten at ₱7 and five at ₱9 cost ₱7.6667 each, not ₱7 —
     * taking the first movement's cost would misprice every sale that crosses
     * a batch boundary, and nothing would fail.
     *
     * @param  Collection<int, StockMovement>  $movements
     */
    private function weightedUnitCost(Collection $movements): string
    {
        if ($movements->isEmpty()) {
            return '0.0000';
        }

        $totalQty = '0.000';
        $totalCost = '0.0000';

        foreach ($movements as $movement) {
            // quantity_base is negative on an issue.
            $qty = ltrim((string) $movement->quantity_base, '-');
            $totalQty = bcadd($totalQty, $qty, self::QTY);
            $totalCost = bcadd($totalCost, bcmul($qty, (string) $movement->unit_cost, 4), 4);
        }

        if (bccomp($totalQty, '0', self::QTY) === 0) {
            return '0.0000';
        }

        // Divide wide, then round — bcmath truncates, and truncating cost
        // systematically overstates profit on every batch-crossing sale.
        return Money::divide($totalCost, $totalQty, 4);
    }

    /**
     * @param  list<array<string, mixed>>  $discounts
     * @return string the total discounted
     */
    private function applyDiscounts(Sale $sale, array $discounts): string
    {
        $total = '0.00';

        foreach ($discounts as $discount) {
            // Resolved from the table, not an enum: the owner can define a
            // promo without a migration. findApplicable() returns only active
            // rows, so a sale quoting a switched-off type fails here rather
            // than discounting zero and looking correct.
            $type = $this->discountTypes->findApplicable($discount['type']);

            if ($type === null) {
                throw new \InvalidArgumentException(
                    "There is no active discount called '{$discount['type']}'."
                );
            }

            // Every discount applies to the whole cart. Per-product
            // eligibility was dropped on 2026-08-06 — the flag existed to
            // narrow a statutory discount to qualified goods, but nothing
            // could set it per branch and it was one more thing to get wrong
            // at the counter.
            $base = $this->lineSubtotal($sale);

            $percentage = $this->money(
                $discount['percentage'] ?? $type->percentage ?? 0,
            );

            $amount = isset($discount['amount'])
                ? $this->money($discount['amount'])
                : bcdiv(bcmul($base, $percentage, 4), '100', self::MONEY);

            // Never discount below zero, however the numbers were supplied.
            if (bccomp($amount, $base, self::MONEY) > 0) {
                $amount = $base;
            }

            $this->sales->addDiscount($sale, [
                'sale_item_id' => $discount['sale_item_id'] ?? null,
                // Both: the slug is the historical record of what was applied
                // and must not move if the type is later renamed; the FK is
                // the forward link for reporting.
                'type' => $type->slug,
                'discount_type_id' => $type->id,
                'percentage' => $percentage,
                'amount' => $amount,
                'amount_before' => $base,
                'amount_after' => bcsub($base, $amount, self::MONEY),
                'id_number' => $discount['id_number'] ?? null,
                'customer_name' => $discount['customer_name'] ?? null,
                'reason' => $discount['reason'] ?? null,
                'cashier_id' => Auth::id(),
                'approved_by' => $discount['approved_by'] ?? null,
            ]);

            $total = bcadd($total, $amount, self::MONEY);
        }

        return $total;
    }

    private function lineSubtotal(Sale $sale): string
    {
        return $sale->items()->get()->reduce(
            fn (string $carry, SaleItem $item) => bcadd($carry, (string) $item->line_total, self::MONEY),
            '0.00',
        );
    }

    /**
     * @param  list<array<string, mixed>>  $payments
     */
    private function recordPayments(Sale $sale, array $payments, string $total, ?Customer $customer): void
    {
        $paid = '0.00';

        foreach ($payments as $payment) {
            $amount = $this->money($payment['amount']);

            if (bccomp($amount, '0', self::MONEY) <= 0) {
                throw new \InvalidArgumentException('A payment must be greater than zero.');
            }

            if ($payment['method'] === 'credit') {
                if ($customer === null) {
                    throw new \InvalidArgumentException('An utang payment needs a customer.');
                }

                if ($customer->is_blocked) {
                    throw new CreditLimitExceededException(
                        sprintf('%s is blocked from credit purchases.', $customer->nickname ?? $customer->name),
                    );
                }

                // Goes through the ledger, not straight at the cached balance:
                // the charge row is what a statement prints and what a later
                // payment allocates against. CreditService re-checks the limit
                // and the blocked flag under its own lock.
                $this->credit->charge(
                    customer: $customer,
                    amount: $amount,
                    sale: $sale,
                    note: 'Sale '.$sale->sale_number,
                );
            }

            $this->sales->addPayment($sale, [
                'method' => $payment['method'],
                'amount' => $amount,
                'reference_no' => $payment['reference_no'] ?? null,
            ]);

            $paid = bcadd($paid, $amount, self::MONEY);
        }

        // Overpayment in cash is change, not a mismatch; anything else means
        // the cart and the tender disagree and the sale must not be recorded.
        if (bccomp($paid, $total, self::MONEY) < 0) {
            throw new \InvalidArgumentException(sprintf(
                'Payments total ₱%s but the sale comes to ₱%s.',
                $paid,
                $total,
            ));
        }
    }

    /**
     * @param  list<array<string, mixed>>  $payments
     */
    private function methodTotal(array $payments, string $method): string
    {
        $total = '0.00';

        foreach ($payments as $payment) {
            if ($payment['method'] === $method) {
                $total = bcadd($total, $this->money($payment['amount']), self::MONEY);
            }
        }

        return $total;
    }

    /**
     * @param  list<array<string, mixed>>  $payments
     */
    private function cashPortion(array $payments): string
    {
        return $this->methodTotal($payments, 'cash');
    }

    /**
     * @return list<string>
     */
    private function relations(): array
    {
        return ['items.product', 'items.productUnit.unit', 'payments', 'discounts', 'customer', 'branch'];
    }

    private function money(string|float|int $value): string
    {
        return bcadd((string) $value, '0', self::MONEY);
    }
}
