<?php

namespace App\Services;

use App\Data\StockTransferData;
use App\Enums\StockMovementTypeEnum;
use App\Models\Branch;
use App\Models\StockMovement;
use App\Models\StockTransfer;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Spatie\LaravelData\Optional;

/**
 * Moving stock between two branches of the same store.
 *
 * **One step, not three.** A transfer used to sit at `pending` until someone
 * sent it and again at `sent` until someone received it. Between two branches of
 * the same small business there is nobody to approve anything — the tindera who
 * carries the goods across town *is* the approval — so the request now issues
 * from the origin and receives at the destination before it returns.
 *
 * The two legs are still separate work, and still written in that order, because
 * the issue leg is what decides which batches move: FEFO draws them, the ledger
 * records them, and the receive leg mirrors that record batch for batch. The
 * `status`, `sent_at` and `received_at` columns are all stamped here, so the
 * ledger and the transfer report read exactly as they did before.
 */
class StockTransferService
{
    public function __construct(private readonly StockService $stock) {}

    /**
     * Create the transfer and move the stock, in one transaction.
     *
     * The order inside the transaction is load-bearing: `issue()` writes the
     * `transfer_out` movements that `mirrorToDestination()` reads back. Splitting
     * these into two transactions — or reordering them — leaves the destination
     * receiving nothing, silently.
     */
    public function create(StockTransferData $data): StockTransfer
    {
        return DB::transaction(function () use ($data) {
            $from = Branch::findOrFail($data->from_branch_id);
            $to = Branch::findOrFail($data->to_branch_id);

            $transfer = StockTransfer::create([
                'from_branch_id' => $from->id,
                'to_branch_id' => $to->id,
                'created_by' => Auth::id(),
                'status' => StockTransfer::PENDING,
                'transfer_number' => sprintf('TRF-%s-%06d', $from->code, StockTransfer::max('id') + 1),
                'notes' => $data->notes instanceof Optional ? null : $data->notes,
            ]);

            foreach ($data->items as $item) {
                $transfer->items()->create($item->toArray());
            }

            $this->issueFromOrigin($transfer);
            $this->mirrorToDestination($transfer);

            $transfer->update([
                'status' => StockTransfer::RECEIVED,
                'sent_at' => now(),
                'received_at' => now(),
            ]);

            return $transfer->load('items.productUnit');
        });
    }

    /**
     * Stock leaves the origin.
     *
     * FEFO decides which batches are drawn, so one item may produce several
     * movements at different costs. Those movements *are* the record of what
     * left — `mirrorToDestination()` reads them back rather than re-deriving
     * anything.
     */
    private function issueFromOrigin(StockTransfer $transfer): void
    {
        $transfer->load('items.product', 'items.productUnit', 'fromBranch', 'toBranch');

        foreach ($transfer->items as $item) {
            $this->stock->issue(
                branch: $transfer->fromBranch,
                product: $item->product,
                productUnit: $item->productUnit,
                quantity: $item->quantity,
                type: StockMovementTypeEnum::TRANSFER_OUT,
                reference: $transfer,
                note: 'Transfer to '.$transfer->toBranch->name,
            );
        }
    }

    /**
     * Stock arrives at the destination, batch for batch.
     *
     * Each outgoing movement is mirrored with **that batch's** cost, expiry and
     * code rather than averaging the lot into one. Two cartons with different
     * expiry dates stay two batches, so FEFO at the destination still sells the
     * older one first — averaging would flatten them into one date and quietly
     * extend the shelf life of the older half.
     *
     * This used to pass `0` as the cost — the sixth positional argument of
     * `StockService::receive()`. Stock arrived free, the destination's average
     * cost was dragged toward zero, and every later sale there reported
     * near-total margin.
     */
    private function mirrorToDestination(StockTransfer $transfer): void
    {
        $transfer->load('toBranch', 'fromBranch');

        // Read back inside the same transaction, so these are the rows
        // `issueFromOrigin()` just wrote — uncommitted, but on this connection.
        $outgoing = StockMovement::query()
            ->where('reference_type', $transfer->getMorphClass())
            ->where('reference_id', $transfer->getKey())
            ->where('type', StockMovementTypeEnum::TRANSFER_OUT->value)
            ->with(['product.baseProductUnit', 'batch'])
            ->get();

        foreach ($outgoing as $movement) {
            // Received against the product's **base** unit using the base
            // quantity, so the conversion factor is 1 and the amount is not
            // re-multiplied. Passing the transfer item's unit here would
            // turn 100 sachets into 100 boxes.
            $baseUnit = $movement->product->baseProductUnit;

            if ($baseUnit === null) {
                throw new InvalidArgumentException(
                    "Product {$movement->product->name} has no base selling unit to receive into."
                );
            }

            $this->stock->receive(
                branch: $transfer->toBranch,
                product: $movement->product,
                productUnit: $baseUnit,
                // `quantity_base` is signed — negative, because it left.
                quantity: ltrim((string) $movement->quantity_base, '-'),
                type: StockMovementTypeEnum::TRANSFER_IN,
                unitCost: $movement->unit_cost ?? 0,
                expiryDate: $movement->batch?->expiry_date?->toDateString(),
                batchCode: $movement->batch?->batch_code,
                reference: $transfer,
                note: 'Transfer from '.$transfer->fromBranch->name,
            );
        }
    }
}
