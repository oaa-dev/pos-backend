<?php

namespace App\Repositories;

use App\Filters\GlobalSearchFilter;
use App\Models\SupportTicket;
use App\Repositories\Contracts\SupportTicketRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class SupportTicketRepository extends BaseRepository implements SupportTicketRepositoryInterface
{
    protected function model(): string
    {
        return SupportTicket::class;
    }

    protected function allowedFilters(): array
    {
        return [
            AllowedFilter::custom('q', new GlobalSearchFilter([
                'subject',
                'body',
                'reporter.name',
            ])),
            'status',
            'category',
            'priority',
            'store_id',

            AllowedFilter::callback(
                'opened_from',
                fn (Builder $query, $value) => $query->whereDate('created_at', '>=', $value),
            ),
            AllowedFilter::callback(
                'opened_to',
                fn (Builder $query, $value) => $query->whereDate('created_at', '<=', $value),
            ),
        ];
    }

    protected function allowedSorts(): array
    {
        return ['id', 'status', 'priority', 'created_at', 'last_replied_at'];
    }

    protected function allowedIncludes(): array
    {
        return ['replies', 'replies.author', 'reporter', 'store'];
    }

    protected function defaultSort(): string
    {
        return '-created_at';
    }

    /**
     * The store rule, and the reason there is no `support.view-all` permission.
     *
     * `owner` is a wildcard role — `SystemRoleSeeder` resolves `'*'` to every
     * non-platform permission at seed time — so a `support.view-all` in the
     * catalogue would be inherited by every customer and hand each of them the
     * others' tickets. `stores.view` is platform-level and cannot be inherited,
     * which makes it the marker only the operator carries.
     *
     * Note this is a **scope**, not a filter: unlike `activity_logs.store_id`,
     * it is applied whether or not the caller asked, because everyone but the
     * operator has exactly one store's tickets they may see.
     */
    protected function query(): QueryBuilder
    {
        $query = parent::query()
            ->with(['reporter', 'store'])
            ->withCount('replies');

        $user = auth()->user();

        // No authenticated user means no tickets, not every ticket. Seeders and
        // console commands run here too, and `where('store_id', null)` would
        // match nothing anyway — this says so deliberately rather than by
        // accident.
        if ($user === null) {
            return $query->whereRaw('1 = 0');
        }

        if (! $user->can('stores.view')) {
            $query->where('store_id', $user->store?->id);
        }

        return $query;
    }
}
