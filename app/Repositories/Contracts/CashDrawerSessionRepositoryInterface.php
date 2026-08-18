<?php

namespace App\Repositories\Contracts;

use App\Models\CashDrawerSession;
use App\Models\CashMovement;

interface CashDrawerSessionRepositoryInterface extends BaseRepositoryInterface
{
    public function findOpenForBranch(int $branchId): ?CashDrawerSession;

    public function lockOpenForBranch(int $branchId): ?CashDrawerSession;

    /**
     * @param  array<string, mixed>  $values
     */
    public function recordMovement(CashDrawerSession $session, array $values): CashMovement;

    public function cashSalesTotal(int $sessionId): string;

    public function creditCollectionsTotal(int $sessionId): string;

    public function refundsTotal(int $sessionId): string;
}
