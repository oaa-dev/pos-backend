<?php

namespace App\Console\Commands;

use App\Models\BranchProductStock;
use App\Repositories\Contracts\StockMovementRepositoryInterface;
use App\Services\StockService;
use Illuminate\Console\Command;

/**
 * `branch_product_stocks.quantity_on_hand` is a cache of the movement ledger.
 * Only StockService writes both, inside one transaction, so they should never
 * disagree — but a cache that can drift silently needs a way to prove it has
 * not.
 */
class ReconcileStock extends Command
{
    protected $signature = 'inventory:reconcile
                            {--branch= : Limit to one branch id}
                            {--dry-run : Report drift without correcting it}';

    protected $description = 'Recompute cached stock levels from the movement ledger and report drift';

    public function handle(StockService $stock): int
    {
        $query = BranchProductStock::query()
            ->when($this->option('branch'), fn ($q, $branch) => $q->where('branch_id', $branch))
            ->with('product:id,name');

        $drifted = [];
        $checked = 0;

        foreach ($query->cursor() as $row) {
            $checked++;

            if ($this->option('dry-run')) {
                $expected = app(StockMovementRepositoryInterface::class)
                    ->sumBaseQuantity($row->branch_id, $row->product_id);

                $drift = bcsub(
                    bcadd($expected, '0', 3),
                    bcadd((string) $row->quantity_on_hand, '0', 3),
                    3,
                );

                if (bccomp($drift, '0', 3) !== 0) {
                    $drifted[] = [$row->branch_id, $row->product->name ?? $row->product_id, $row->quantity_on_hand, $expected, $drift];
                }

                continue;
            }

            $result = $stock->reconcile($row->branch_id, $row->product_id);

            if (bccomp($result['drift'], '0', 3) !== 0) {
                $drifted[] = [
                    $row->branch_id,
                    $row->product->name ?? $row->product_id,
                    $result['quantity_before'],
                    $result['quantity_after'],
                    $result['drift'],
                ];
            }
        }

        if ($drifted === []) {
            $this->info("Checked {$checked} stock rows. No drift.");

            return self::SUCCESS;
        }

        $this->warn(sprintf('Checked %d stock rows. %d drifted:', $checked, count($drifted)));
        $this->table(['Branch', 'Product', 'Cached', 'Ledger', 'Drift'], $drifted);

        // Non-zero exit so a scheduled run surfaces rather than passing
        // quietly in a log nobody reads.
        return self::FAILURE;
    }
}
