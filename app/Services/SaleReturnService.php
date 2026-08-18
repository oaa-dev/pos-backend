<?php

namespace App\Services;

use App\Data\SaleReturnData;
use App\Enums\ActivityActionEnum;
use App\Enums\StockMovementTypeEnum;
use App\Models\Sale;
use App\Models\SaleReturn;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SaleReturnService
{
    public function __construct(private readonly StockService $stock, private readonly CashDrawerService $drawer, private readonly ActivityLogger $activity) {}

    public function create(Sale $sale, SaleReturnData $data): SaleReturn
    {
        return DB::transaction(function () use ($sale, $data) {
            if ($sale->status !== 'completed') {
                throw new \InvalidArgumentException('Only completed sales can be returned.');
            }$sale->load('items.product', 'items.productUnit', 'branch');
            $refund = '0.00';
            $return = SaleReturn::create(['sale_id' => $sale->id, 'branch_id' => $sale->branch_id, 'cash_drawer_session_id' => $data->refund_method === 'cash' ? $sale->cash_drawer_session_id : null, 'user_id' => Auth::id(), 'return_number' => 'RET-'.$sale->branch->code.'-'.str_pad((string) (SaleReturn::max('id') + 1), 6, '0', STR_PAD_LEFT), 'reason' => $data->reason, 'refund_method' => $data->refund_method, 'refund_total' => 0, 'returned_at' => now()]);
            foreach ($data->items as $row) {
                $item = $sale->items->firstWhere('id', $row->sale_item_id);
                if (! $item) {
                    throw new \InvalidArgumentException('A returned item must belong to the original sale.');
                }if ((float) $row->quantity > (float) $item->quantity) {
                    throw new \InvalidArgumentException('Return quantity exceeds sold quantity.');
                }$amount = bcmul((string) $row->quantity, (string) $item->unit_price, 2);
                $return->items()->create(['sale_item_id' => $item->id, 'quantity' => $row->quantity, 'refund_amount' => $amount, 'restock' => $row->restock]);
                $refund = bcadd($refund, $amount, 2);
                if ($row->restock) {
                    $this->stock->receive($sale->branch, $item->product, $item->productUnit, $row->quantity, StockMovementTypeEnum::RETURN_IN, $item->unit_cost, null, null, $return, $data->reason);
                }
            }$return->update(['refund_total' => $refund]);

            // `SaleReturn` carries no LogsActivity trait — returns are part of
            // the till's hot path — and the customer's stated reason is the
            // whole point of the entry, which a mechanical diff could not
            // supply. Inside the transaction, so a failed return logs nothing.
            $this->activity->record(
                ActivityActionEnum::SALE_RETURNED,
                subject: $return,
                old: [],
                new: ['return_number' => $return->return_number, 'refund_total' => $refund, 'refund_method' => $data->refund_method],
                reason: $data->reason,
                storeId: $sale->branch?->store_id,
            );

            return $return->load('items.saleItem');
        });
    }
}
