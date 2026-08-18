<?php

namespace App\Services;

use App\Data\StockReceiptData;
use App\Enums\CashMovementTypeEnum;
use App\Enums\StockMovementTypeEnum;
use App\Models\Branch;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use Illuminate\Support\Facades\DB;

/**
 * Stock coming in, with or without money behind it.
 *
 * This is what the purchasing module used to be. A goods receipt did five
 * things — moved stock, charged the till, remembered the supplier's cost,
 * drew down a purchase order, and wrote a receipt document. Only the first
 * three were load-bearing for a sari-sari, and requiring a supplier and a
 * purchase order to reach them pushed everyday deliveries onto the stock-in
 * screen, where the till was never charged at all.
 *
 * So the three that mattered moved here and the rest went. One screen now
 * records both a delivery and an opening balance, and the difference between
 * them is what the owner fills in rather than which screen she opened.
 *
 * `StockService` stays pure — batches, movements and the stock cache. This
 * orchestrates around it, the same shape `StoreExpenseService` already uses to
 * charge the drawer without being the drawer.
 */
class StockReceiptService
{
    public function __construct(
        private readonly StockService $stock,
        private readonly CashDrawerService $drawer,
    ) {}

    private const MONEY = 2;

    public function record(Branch $branch, StockReceiptData $data): StockMovement
    {
        return DB::transaction(function () use ($branch, $data) {
            $product = Product::findOrFail($data->product_id);
            $productUnit = ProductUnit::findOrFail($data->product_unit_id);

            $supplier = $data->supplier_id === null
                ? null
                : Supplier::findOrFail($data->supplier_id);

            $unitCost = (string) ($data->unit_cost ?? 0);

            $movement = $this->stock->receive(
                branch: $branch,
                product: $product,
                productUnit: $productUnit,
                quantity: $data->quantity,
                // What the owner filled in decides this, not which screen she
                // opened. A supplier or a payment means it was bought; neither
                // means it was counted in.
                type: $this->isPurchase($data)
                    ? StockMovementTypeEnum::PURCHASE_RECEIPT
                    : StockMovementTypeEnum::OPENING_BALANCE,
                unitCost: $unitCost,
                expiryDate: $data->expiry_date,
                batchCode: $data->batch_code,
                note: $data->note,
            );

            $this->chargeDrawer($branch, $data, $movement, $unitCost);
            $this->rememberCost($supplier, $product, $productUnit, $unitCost);

            return $movement;
        });
    }

    /** A stock-in is a purchase when someone was paid, or someone is named. */
    private function isPurchase(StockReceiptData $data): bool
    {
        return $data->supplier_id !== null || $data->paid_from !== null;
    }

    /**
     * Money leaving the till has to reach the shift, or the drawer comes up
     * short at close and reads as a shortage rather than a purchase. This is
     * the whole reason a delivery could not simply be a stock-in before.
     *
     * Only `drawer` moves it — a delivery paid from the owner's pocket or by
     * bank transfer never touched the till.
     */
    private function chargeDrawer(
        Branch $branch,
        StockReceiptData $data,
        StockMovement $movement,
        string $unitCost,
    ): void {
        if ($data->paid_from !== 'drawer') {
            return;
        }

        $total = bcmul((string) $data->quantity, $unitCost, self::MONEY);

        // `recordMovement` refuses a non-positive amount, and a free delivery
        // moved no cash to record.
        if (bccomp($total, '0', self::MONEY) <= 0) {
            return;
        }

        $session = $this->drawer->findOpenForBranch($branch->id);

        if ($session === null) {
            return;
        }

        $this->drawer->recordMovement(
            session: $session,
            type: CashMovementTypeEnum::SUPPLIER_PAYMENT,
            amount: $total,
            reason: 'Delivery received',
            reference: $movement,
        );
    }

    /**
     * What a *named* supplier last charged, which the What to Buy report reads
     * as a quote. Skipped without one — a row keyed on nobody is not a quote.
     */
    private function rememberCost(
        ?Supplier $supplier,
        Product $product,
        ProductUnit $productUnit,
        string $unitCost,
    ): void {
        if ($supplier === null || bccomp($unitCost, '0', 4) <= 0) {
            return;
        }

        SupplierProduct::updateOrCreate(
            ['supplier_id' => $supplier->id, 'product_id' => $product->id],
            ['product_unit_id' => $productUnit->id, 'last_cost' => $unitCost],
        );
    }
}
