<?php

namespace App\Repositories;

use App\Filters\GlobalSearchFilter;
use App\Models\StockTransfer;
use App\Repositories\Contracts\StockTransferRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class StockTransferRepository extends BaseRepository implements StockTransferRepositoryInterface
{
    protected function model(): string
    {
        return StockTransfer::class;
    }

    /**
     * Deliberately **not** `BranchScopedInterface`. That contract returns one
     * column, and a transfer has two branches — a user assigned to either end
     * has a legitimate interest in it. Narrowing on `from_branch_id` alone
     * would hide every incoming transfer from the branch receiving it.
     */
    protected function allowedFilters(): array
    {
        return [
            AllowedFilter::custom('q', new GlobalSearchFilter([
                'transfer_number',
                'fromBranch.name',
                'toBranch.name',
            ])),
            'status',
            'from_branch_id',
            'to_branch_id',

            // One branch, either end. This is what the Stock Transfer report
            // asks — "what moved in or out of here" — and neither single-column
            // filter above can answer it.
            //
            // The `orWhere` pair is wrapped in its own closure on purpose. Left
            // unnested it would escape the `store_id` constraint below and pull
            // every other customer's transfers into a filtered listing.
            AllowedFilter::callback(
                'branch_id',
                fn (Builder $query, $value) => $query->where(
                    fn (Builder $either) => $either
                        ->where('from_branch_id', $value)
                        ->orWhere('to_branch_id', $value),
                ),
            ),

            // Bounds the report's date range. Named for the transfer rather
            // than the column because `created_at`, `sent_at` and `received_at`
            // are all the same instant now.
            AllowedFilter::callback(
                'transferred_from',
                fn (Builder $query, $value) => $query->whereDate('created_at', '>=', $value),
            ),
            AllowedFilter::callback(
                'transferred_to',
                fn (Builder $query, $value) => $query->whereDate('created_at', '<=', $value),
            ),

            // `stock_transfers` has no `store_id` — it reaches its store
            // through the origin branch. Unfiltered, this listing returns
            // every customer's transfers.
            AllowedFilter::callback(
                'store_id',
                fn (Builder $query, $value) => $query->whereHas(
                    'fromBranch',
                    fn (Builder $branch) => $branch->where('store_id', $value),
                ),
            ),
        ];
    }

    protected function allowedSorts(): array
    {
        return ['id', 'transfer_number', 'status', 'sent_at', 'received_at', 'created_at'];
    }

    /**
     * `items.productUnit.unit` is what turns a bare `1` into "1 box". Without
     * the unit the quantity on a transfer line is unreadable — a box and a
     * sachet are both `1`, and they differ by the conversion factor.
     */
    protected function allowedIncludes(): array
    {
        return [
            'items',
            'items.product',
            'items.productUnit',
            'items.productUnit.unit',
            'fromBranch',
            'toBranch',
        ];
    }

    /** Newest first, so the report reads correctly without asking. */
    protected function defaultSort(): string
    {
        return '-created_at';
    }

    protected function query(): QueryBuilder
    {
        return parent::query()
            ->with(['fromBranch', 'toBranch'])
            ->withCount('items');
    }
}
