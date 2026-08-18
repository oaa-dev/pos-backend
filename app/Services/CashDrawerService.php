<?php

namespace App\Services;

use App\Enums\CashMovementTypeEnum;
use App\Exceptions\ShiftNotOpenException;
use App\Models\Branch;
use App\Models\CashDrawerSession;
use App\Models\CashMovement;
use App\Repositories\Contracts\CashDrawerSessionRepositoryInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Shifts are what make a shortage attributable. Every sale belongs to an open
 * session, so at close the drawer is answerable to a specific person and a
 * specific window rather than to "the day".
 */
class CashDrawerService extends BaseService
{
    private const SCALE = 2;

    public function __construct(
        protected readonly CashDrawerSessionRepositoryInterface $sessions
    ) {
        parent::__construct($sessions);
    }

    /**
     * At most one open session per branch.
     *
     * MySQL has no partial unique index, so "one *open* row per branch" cannot
     * be expressed as a constraint — a unique on (branch_id, status) would also
     * forbid a second *closed* session. The guard is therefore a locked read
     * inside the transaction that creates the row.
     */
    public function open(Branch $branch, string|float|int $openingFloat = 0): CashDrawerSession
    {
        return DB::transaction(function () use ($branch, $openingFloat) {
            $existing = $this->sessions->lockOpenForBranch($branch->id);

            if ($existing !== null) {
                throw new \InvalidArgumentException(sprintf(
                    'A cash drawer session is already open in %s, opened by %s.',
                    $branch->name,
                    $existing->user?->name ?? 'someone else',
                ));
            }

            return $this->sessions->create([
                'branch_id' => $branch->id,
                'user_id' => Auth::id(),
                'opened_at' => now(),
                'opening_float' => $this->money($openingFloat),
                'status' => 'open',
            ]);
        });
    }

    public function findOpenForBranch(int $branchId): ?CashDrawerSession
    {
        return $this->sessions->findOpenForBranch($branchId);
    }

    /**
     * @throws ShiftNotOpenException
     */
    public function requireOpenSession(Branch $branch): CashDrawerSession
    {
        $session = $this->sessions->findOpenForBranch($branch->id);

        if ($session === null) {
            throw new ShiftNotOpenException(sprintf(
                'No cash drawer session is open in %s. Open a shift before selling.',
                $branch->name,
            ));
        }

        return $session;
    }

    public function close(
        CashDrawerSession $session,
        string|float|int $countedCash,
        ?string $notes = null,
    ): CashDrawerSession {
        return DB::transaction(function () use ($session, $countedCash, $notes) {
            if (! $session->isOpen()) {
                throw new \InvalidArgumentException('That cash drawer session is already closed.');
            }

            $expected = $this->expectedCash($session);
            $counted = $this->money($countedCash);

            return $this->sessions->update($session, [
                'closed_at' => now(),
                'closing_counted' => $counted,
                'expected_cash' => $expected,
                // Negative is a shortage, positive an overage.
                'variance' => bcsub($counted, $expected, self::SCALE),
                'status' => 'closed',
                'closing_notes' => $notes,
                'closed_by' => Auth::id(),
            ]);
        });
    }

    /**
     * Expected cash at close:
     *
     *   opening_float
     *   + cash sales
     *   + credit collections
     *   + float_in, gcash_cash_in, paid_in
     *   − store expenses, supplier payments, owner withdrawals, petty cash,
     *     drops, gcash_cash_out, paid_out
     *   − refunds
     *
     * Every term is present now even though expenses (Phase 5) and credit
     * collections (Phase 4) contribute zero until those modules land. Building
     * the full formula up front means the close is never quietly wrong — it
     * simply has fewer inputs at first.
     */
    public function expectedCash(CashDrawerSession $session): string
    {
        $total = $this->money($session->opening_float);

        $total = bcadd($total, $this->sessions->cashSalesTotal($session->id), self::SCALE);
        $total = bcadd($total, $this->sessions->creditCollectionsTotal($session->id), self::SCALE);
        $total = bcsub($total, $this->sessions->refundsTotal($session->id), self::SCALE);

        foreach ($session->cashMovements as $movement) {
            $total = bcadd($total, $movement->signedAmount(), self::SCALE);
        }

        return $total;
    }

    public function recordMovement(
        CashDrawerSession $session,
        CashMovementTypeEnum $type,
        string|float|int $amount,
        ?string $reason = null,
        ?Model $reference = null,
    ): CashMovement {
        if (! $session->isOpen()) {
            throw new \InvalidArgumentException('Cannot record cash against a closed session.');
        }

        $value = $this->money($amount);

        // The type carries the direction, so a negative amount would flip it
        // twice and silently invert the movement.
        if (bccomp($value, '0', self::SCALE) <= 0) {
            throw new \InvalidArgumentException('A cash movement must be greater than zero.');
        }

        return $this->sessions->recordMovement($session, [
            'type' => $type->value,
            'amount' => $value,
            'reason' => $reason,
            'reference_type' => $reference?->getMorphClass(),
            'reference_id' => $reference?->getKey(),
            'user_id' => Auth::id(),
            'occurred_at' => now(),
        ]);
    }

    private function money(string|float|int $value): string
    {
        return bcadd((string) $value, '0', self::SCALE);
    }
}
