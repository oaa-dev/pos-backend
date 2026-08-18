<?php

namespace App\Repositories;

use App\Models\CashDrawerSession;
use App\Models\CashMovement;
use App\Repositories\Contracts\BranchScopedInterface;
use App\Repositories\Contracts\CashDrawerSessionRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class CashDrawerSessionRepository extends BaseRepository implements BranchScopedInterface, CashDrawerSessionRepositoryInterface
{
    protected function model(): string
    {
        return CashDrawerSession::class;
    }

    public function branchColumn(): string
    {
        return 'branch_id';
    }

    protected function allowedFilters(): array
    {
        return [
            'branch_id',
            'user_id',
            'status',
            AllowedFilter::callback(
                'opened_from',
                fn ($query, $value) => $query->whereDate('opened_at', '>=', $value),
            ),
            AllowedFilter::callback(
                'opened_to',
                fn ($query, $value) => $query->whereDate('opened_at', '<=', $value),
            ),
        ];
    }

    protected function allowedSorts(): array
    {
        return ['id', 'opened_at', 'closed_at'];
    }

    protected function allowedIncludes(): array
    {
        return ['branch', 'user', 'cashMovements'];
    }

    protected function defaultSort(): string
    {
        return '-opened_at';
    }

    protected function query(): QueryBuilder
    {
        return parent::query()->with(['branch', 'user']);
    }

    public function findOpenForBranch(int $branchId): ?CashDrawerSession
    {
        return $this->builder()
            ->where('branch_id', $branchId)
            ->open()
            ->with(['cashMovements', 'user'])
            ->first();
    }

    /** Must be called inside a transaction — this is the one-open-per-branch guard. */
    public function lockOpenForBranch(int $branchId): ?CashDrawerSession
    {
        return $this->builder()
            ->where('branch_id', $branchId)
            ->open()
            ->lockForUpdate()
            ->first();
    }

    public function recordMovement(CashDrawerSession $session, array $values): CashMovement
    {
        return $session->cashMovements()->create($values);
    }

    /**
     * Sales land later in Phase 3 than the drawer does, so these read the
     * tables directly and return zero until they exist. The guard comes out
     * once the sell loop is in place.
     */
    public function cashSalesTotal(int $sessionId): string
    {
        if (! $this->hasSalesTables()) {
            return '0.00';
        }

        $total = DB::table('sale_payments')
            ->join('sales', 'sales.id', '=', 'sale_payments.sale_id')
            ->where('sales.cash_drawer_session_id', $sessionId)
            ->where('sales.status', 'completed')
            ->where('sale_payments.method', 'cash')
            ->sum('sale_payments.amount');

        return bcadd((string) ($total ?: 0), '0', 2);
    }

    /**
     * Utang paid off during the shift. This lands in the drawer, so it has to
     * count toward the expected cash or every collecting shift reads as an
     * overage.
     */
    public function creditCollectionsTotal(int $sessionId): string
    {
        if (! Schema::hasTable('credit_transactions')) {
            return '0.00';
        }

        $total = DB::table('credit_transactions')
            ->where('cash_drawer_session_id', $sessionId)
            ->where('type', 'payment')
            ->sum('amount');

        return bcadd((string) ($total ?: 0), '0', 2);
    }

    public function refundsTotal(int $sessionId): string
    {
        if (! $this->hasSalesTables()) {
            return '0.00';
        }

        $total = DB::table('sales')
            ->where('cash_drawer_session_id', $sessionId)
            ->where('status', 'voided')
            ->sum('amount_tendered');

        return bcadd((string) ($total ?: 0), '0', 2);
    }

    private function hasSalesTables(): bool
    {
        return Schema::hasTable('sales') && Schema::hasTable('sale_payments');
    }
}
