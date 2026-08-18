<?php

namespace App\Repositories;

use App\Filters\GlobalSearchFilter;
use App\Models\CreditAllocation;
use App\Models\CreditTransaction;
use App\Repositories\Contracts\BranchScopedInterface;
use App\Repositories\Contracts\CreditTransactionRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class CreditTransactionRepository extends BaseRepository implements BranchScopedInterface, CreditTransactionRepositoryInterface
{
    protected function model(): string
    {
        return CreditTransaction::class;
    }

    public function branchColumn(): string
    {
        return 'branch_id';
    }

    protected function allowedFilters(): array
    {
        return [
            AllowedFilter::custom('q', new GlobalSearchFilter([
                'note',
                'customer.name',
                'customer.nickname',
            ])),
            'customer_id',
            'branch_id',
            'type',
            'cash_drawer_session_id',
            AllowedFilter::callback(
                'occurred_from',
                fn ($query, $value) => $query->whereDate('occurred_at', '>=', $value),
            ),
            AllowedFilter::callback(
                'occurred_to',
                fn ($query, $value) => $query->whereDate('occurred_at', '<=', $value),
            ),
        ];
    }

    protected function allowedSorts(): array
    {
        return ['id', 'occurred_at', 'amount'];
    }

    protected function allowedIncludes(): array
    {
        return ['customer', 'sale', 'user', 'branch'];
    }

    protected function defaultSort(): string
    {
        return '-occurred_at';
    }

    protected function query(): QueryBuilder
    {
        return parent::query()->with(['customer', 'user']);
    }

    public function record(array $values): CreditTransaction
    {
        return CreditTransaction::create($values);
    }

    /** Oldest first — the FIFO queue, read without locking. */
    public function unsettledCharges(int $customerId): Collection
    {
        return $this->builder()
            ->where('customer_id', $customerId)
            ->unsettledCharges()
            ->get();
    }

    /**
     * The same queue, locked. Must be called inside a transaction: two
     * payments collected at once would otherwise both settle the same charge.
     */
    public function lockUnsettledCharges(int $customerId): Collection
    {
        return $this->builder()
            ->where('customer_id', $customerId)
            ->unsettledCharges()
            ->lockForUpdate()
            ->get();
    }

    public function allocate(
        CreditTransaction $payment,
        CreditTransaction $charge,
        string $amount,
    ): CreditAllocation {
        $charge->outstanding = bcsub((string) $charge->outstanding, $amount, 2);
        $charge->save();

        return CreditAllocation::create([
            'payment_transaction_id' => $payment->id,
            'charge_transaction_id' => $charge->id,
            'amount' => $amount,
        ]);
    }

    /**
     * The balance implied by the ledger: charges and positive adjustments up,
     * payments, writeoffs and negative adjustments down.
     */
    public function balanceFor(int $customerId): string
    {
        $rows = $this->builder()
            ->where('customer_id', $customerId)
            ->get(['type', 'amount', 'balance_after']);

        // Adjustments are stored positive with their direction implied by how
        // they were allocated, so the running balance_after of the last row is
        // the honest total rather than a re-sum by sign.
        $last = $rows->last();

        return $last ? bcadd((string) $last->balance_after, '0', 2) : '0.00';
    }

    /** Cash collected against utang during a shift. */
    public function collectionsForSession(int $sessionId): string
    {
        $total = DB::table('credit_transactions')
            ->where('cash_drawer_session_id', $sessionId)
            ->where('type', 'payment')
            ->sum('amount');

        return bcadd((string) ($total ?: 0), '0', 2);
    }

    public function statementFor(int $customerId): Collection
    {
        return $this->builder()
            ->where('customer_id', $customerId)
            ->with(['sale', 'user'])
            ->orderBy('occurred_at')
            ->orderBy('id')
            ->get();
    }
}
