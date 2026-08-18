<?php

namespace App\Repositories\Contracts;

use App\Models\CreditAllocation;
use App\Models\CreditTransaction;
use Illuminate\Database\Eloquent\Collection;

interface CreditTransactionRepositoryInterface extends BaseRepositoryInterface
{
    /** @param array<string, mixed> $values */
    public function record(array $values): CreditTransaction;

    public function unsettledCharges(int $customerId): Collection;

    public function lockUnsettledCharges(int $customerId): Collection;

    public function allocate(
        CreditTransaction $payment,
        CreditTransaction $charge,
        string $amount,
    ): CreditAllocation;

    public function balanceFor(int $customerId): string;

    public function collectionsForSession(int $sessionId): string;

    public function statementFor(int $customerId): Collection;
}
