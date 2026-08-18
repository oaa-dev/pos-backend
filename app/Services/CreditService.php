<?php

namespace App\Services;

use App\Enums\ActivityActionEnum;
use App\Enums\CreditTransactionTypeEnum;
use App\Exceptions\CreditLimitExceededException;
use App\Models\CashDrawerSession;
use App\Models\CreditTransaction;
use App\Models\Customer;
use App\Models\Sale;
use App\Repositories\Contracts\CreditTransactionRepositoryInterface;
use App\Repositories\Contracts\CustomerRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * The utang ledger.
 *
 * `credit_transactions` is append-only and is the source of truth;
 * `customers.current_balance` is a cache of it, exactly as
 * `branch_product_stocks` caches the stock ledger. A mistaken charge is
 * reversed by an adjustment row, never deleted — so a statement a suki
 * disputes always explains itself.
 *
 * Payments allocate FIFO against the oldest unsettled charges. Nobody at a
 * sari-sari pays per-invoice, but recording *what* a payment settled is what
 * makes aging possible: "₱200, 45 days na" is unanswerable from a bare
 * running balance.
 */
class CreditService
{
    private const SCALE = 2;

    public function __construct(
        protected readonly CreditTransactionRepositoryInterface $transactions,
        protected readonly CustomerRepositoryInterface $customers,
        protected readonly ActivityLogger $activity,
    ) {}

    /**
     * Charge a sale to the customer's account.
     *
     * @throws CreditLimitExceededException
     */
    public function charge(
        Customer $customer,
        Sale $sale,
        string|float|int $amount,
        ?string $dueDate = null,
        ?string $note = null,
    ): CreditTransaction {
        if (! $sale->exists || (int) $sale->customer_id !== $customer->id) {
            throw new \InvalidArgumentException('A credit charge must belong to a persisted POS sale for this customer.');
        }

        $value = $this->money($amount);

        if (bccomp($value, '0', self::SCALE) <= 0) {
            throw new \InvalidArgumentException('A charge must be greater than zero.');
        }

        return DB::transaction(function () use ($customer, $value, $sale, $dueDate, $note) {
            $customer = $this->customers->lockFor($customer->id);

            $this->guardCredit($customer, $value);

            $balance = bcadd((string) $customer->current_balance, $value, self::SCALE);

            $transaction = $this->transactions->record([
                'customer_id' => $customer->id,
                'branch_id' => $sale->branch_id,
                'type' => CreditTransactionTypeEnum::CHARGE->value,
                'amount' => $value,
                'balance_after' => $balance,
                // A fresh charge is entirely unsettled; payments draw this down.
                'outstanding' => $value,
                'sale_id' => $sale->id,
                'due_date' => $dueDate,
                'user_id' => Auth::id(),
                'note' => $note,
                'occurred_at' => now(),
            ]);

            $this->customers->setBalance($customer, $balance);

            return $transaction;
        });
    }

    /**
     * Record a payment and settle the oldest charges first.
     *
     * Overpayment is allowed and leaves a negative balance — a suki paying
     * ₱500 against ₱480 of utang is in credit, not an error.
     */
    public function collect(
        Customer $customer,
        string|float|int $amount,
        ?CashDrawerSession $session = null,
        ?string $note = null,
    ): CreditTransaction {
        return $this->settle(
            $customer,
            $amount,
            CreditTransactionTypeEnum::PAYMENT,
            $session,
            $note,
        );
    }

    /**
     * Forgive part or all of a balance. Reduces what is owed without money
     * changing hands, so it never touches the drawer.
     */
    public function writeOff(
        Customer $customer,
        string|float|int $amount,
        ?int $approvedBy = null,
        ?string $note = null,
    ): CreditTransaction {
        return $this->settle(
            $customer,
            $amount,
            CreditTransactionTypeEnum::WRITEOFF,
            null,
            $note,
            $approvedBy,
        );
    }

    /**
     * A signed correction. Positive increases the balance, negative reduces it.
     *
     * This is how a mistaken entry is undone — the original row stays, so the
     * ledger still shows what happened and who fixed it.
     */
    public function adjust(
        Customer $customer,
        string|float|int $signedAmount,
        ?string $note = null,
        ?int $approvedBy = null,
    ): CreditTransaction {
        $value = $this->money($signedAmount);

        if (bccomp($value, '0', self::SCALE) === 0) {
            throw new \InvalidArgumentException('An adjustment cannot be zero.');
        }

        return DB::transaction(function () use ($customer, $value, $note, $approvedBy) {
            $customer = $this->customers->lockFor($customer->id);

            // Before `setBalance()` mutates it — see `settle()`.
            $previousBalance = (string) $customer->current_balance;

            $balance = bcadd((string) $customer->current_balance, $value, self::SCALE);

            $magnitude = ltrim($value, '-');
            $reducing = str_starts_with($value, '-');

            $transaction = $this->transactions->record([
                'customer_id' => $customer->id,
                'branch_id' => $customer->branch_id,
                'type' => CreditTransactionTypeEnum::ADJUSTMENT->value,
                'amount' => $magnitude,
                'balance_after' => $balance,
                // A positive adjustment behaves like a charge and can be paid
                // off; a negative one settles nothing on its own.
                'outstanding' => $reducing ? '0' : $magnitude,
                'user_id' => Auth::id(),
                'approved_by' => $approvedBy,
                'note' => $note,
                'occurred_at' => now(),
            ]);

            // A reduction still has to come off the oldest charges, or the
            // aging report would keep showing debt that no longer exists.
            if ($reducing) {
                $this->allocate($transaction, $magnitude);
            }

            $this->customers->setBalance($customer, $balance);

            // A correction is someone saying the ledger was wrong. Worth the
            // entry whichever direction it moves the balance.
            $this->activity->record(
                ActivityActionEnum::CREDIT_ADJUSTED,
                subject: $transaction,
                old: ['balance' => $previousBalance],
                new: ['balance' => $balance, 'amount' => $value],
                reason: $note,
                storeId: $customer->store_id,
            );

            return $transaction;
        });
    }

    /**
     * Recompute the cached balance from the ledger and report the drift.
     *
     * @return array{balance_before: string, balance_after: string, drift: string}
     */
    public function reconcile(Customer $customer): array
    {
        return DB::transaction(function () use ($customer) {
            $customer = $this->customers->lockFor($customer->id);

            $before = $this->money($customer->current_balance);
            $after = $this->money($this->transactions->balanceFor($customer->id));

            $this->customers->setBalance($customer, $after);

            return [
                'balance_before' => $before,
                'balance_after' => $after,
                'drift' => bcsub($after, $before, self::SCALE),
            ];
        });
    }

    /**
     * Outstanding charges bucketed by age, for the collections view.
     *
     * @return array{current: string, days_1_7: string, days_8_30: string, days_31_60: string, days_over_60: string, total: string}
     */
    public function aging(Customer $customer): array
    {
        $buckets = [
            'current' => '0.00',
            'days_1_7' => '0.00',
            'days_8_30' => '0.00',
            'days_31_60' => '0.00',
            'days_over_60' => '0.00',
        ];

        foreach ($this->transactions->unsettledCharges($customer->id) as $charge) {
            $age = $charge->ageInDays();

            $key = match (true) {
                $age <= 0 => 'current',
                $age <= 7 => 'days_1_7',
                $age <= 30 => 'days_8_30',
                $age <= 60 => 'days_31_60',
                default => 'days_over_60',
            };

            $buckets[$key] = bcadd($buckets[$key], (string) $charge->outstanding, self::SCALE);
        }

        $buckets['total'] = array_reduce(
            array_values($buckets),
            fn (string $carry, string $value) => bcadd($carry, $value, self::SCALE),
            '0.00',
        );

        return $buckets;
    }

    /**
     * Charges past their due date and still owing.
     *
     * @return Collection<int, CreditTransaction>
     */
    public function overdue(Customer $customer): Collection
    {
        return $this->transactions
            ->unsettledCharges($customer->id)
            ->filter(fn (CreditTransaction $charge) => $charge->isOverdue())
            ->values();
    }

    /**
     * Shared by payment and writeoff: record the row, then draw down the
     * oldest charges by that amount.
     */
    private function settle(
        Customer $customer,
        string|float|int $amount,
        CreditTransactionTypeEnum $type,
        ?CashDrawerSession $session,
        ?string $note,
        ?int $approvedBy = null,
    ): CreditTransaction {
        $value = $this->money($amount);

        if (bccomp($value, '0', self::SCALE) <= 0) {
            throw new \InvalidArgumentException('A payment must be greater than zero.');
        }

        return DB::transaction(function () use ($customer, $value, $type, $session, $note, $approvedBy) {
            $customer = $this->customers->lockFor($customer->id);

            // Captured before `setBalance()` writes the new value onto this same
            // instance — read afterwards, `old` and `new` would be identical and
            // the entry would claim the balance never moved.
            $previousBalance = (string) $customer->current_balance;

            $balance = bcsub((string) $customer->current_balance, $value, self::SCALE);

            $transaction = $this->transactions->record([
                'customer_id' => $customer->id,
                'branch_id' => $session?->branch_id ?? $customer->branch_id,
                'type' => $type->value,
                'amount' => $value,
                'balance_after' => $balance,
                'outstanding' => 0,
                // Only a real payment reaches the drawer; a writeoff moves no
                // money and must not inflate the expected cash at close.
                'cash_drawer_session_id' => $type->isCashCollection() ? $session?->id : null,
                'user_id' => Auth::id(),
                'approved_by' => $approvedBy,
                'note' => $note,
                'occurred_at' => now(),
            ]);

            $this->allocate($transaction, $value);
            $this->customers->setBalance($customer, $balance);

            // Forgiving debt is the decision in this class most worth a trail:
            // no money moves, so nothing in the drawer reconciliation shows it.
            // Collections are ordinary counter work and are not logged here.
            if ($type === CreditTransactionTypeEnum::WRITEOFF) {
                $this->activity->record(
                    ActivityActionEnum::CREDIT_WRITTEN_OFF,
                    subject: $transaction,
                    old: ['balance' => $previousBalance],
                    new: ['balance' => $balance, 'amount' => $value],
                    reason: $note,
                    storeId: $customer->store_id,
                );
            }

            return $transaction;
        });
    }

    /**
     * Apply an amount to the oldest unsettled charges, oldest first.
     *
     * Any remainder is deliberately left unallocated: a payment larger than
     * the outstanding debt is a credit balance, not an error, and inventing a
     * charge to absorb it would falsify the ledger.
     */
    private function allocate(CreditTransaction $payment, string $amount): void
    {
        $remaining = $amount;

        foreach ($this->transactions->lockUnsettledCharges($payment->customer_id) as $charge) {
            if (bccomp($remaining, '0', self::SCALE) <= 0) {
                break;
            }

            $available = $this->money($charge->outstanding);
            $applied = bccomp($available, $remaining, self::SCALE) <= 0 ? $available : $remaining;

            $this->transactions->allocate($payment, $charge, $applied);

            $remaining = bcsub($remaining, $applied, self::SCALE);
        }
    }

    /**
     * @throws CreditLimitExceededException
     */
    private function guardCredit(Customer $customer, string $amount): void
    {
        // There is no ceiling on utang. A sari-sari owner judges each suki
        // case by case, so `is_blocked` is the only rule actually enforced.

        if ($customer->is_blocked) {
            throw new CreditLimitExceededException(sprintf(
                '%s is blocked from credit purchases.',
                $customer->nickname ?? $customer->name,
            ));
        }

    }

    private function money(string|float|int $value): string
    {
        return bcadd((string) $value, '0', self::SCALE);
    }
}
