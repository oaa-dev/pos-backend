<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\SaleSuspension;
use App\Repositories\Contracts\SaleSuspensionRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * A parked cart — the suki who runs home for money must not hold the queue.
 *
 * Suspensions hold **no stock**. Nothing moves until the sale completes, so a
 * parked cart can never oversell and a forgotten one costs nothing. Its saved
 * cart snapshot is resumable for fifteen minutes; after that it expires so an
 * old price cannot be restored accidentally.
 */
class SaleSuspensionService extends BaseService
{
    public function __construct(
        protected readonly SaleSuspensionRepositoryInterface $suspensions
    ) {
        parent::__construct($suspensions);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function suspend(Branch $branch, array $payload, ?string $label = null): SaleSuspension
    {
        return $this->suspensions->create([
            'branch_id' => $branch->id,
            'user_id' => Auth::id(),
            'label' => $label,
            'payload' => $payload,
            'suspended_at' => now(),
            'status' => 'suspended',
        ]);
    }

    public function resume(SaleSuspension $suspension): SaleSuspension
    {
        if ($suspension->status !== 'suspended') {
            throw new \InvalidArgumentException('That cart has already been resumed or discarded.');
        }

        if ($suspension->isExpired()) {
            throw new \InvalidArgumentException(
                'This parked cart expired after 15 minutes. Park the items again to use current prices.'
            );
        }

        return $this->suspensions->update($suspension, [
            'status' => 'resumed',
            'resumed_at' => now(),
        ]);
    }

    public function discard(SaleSuspension $suspension): SaleSuspension
    {
        return $this->suspensions->update($suspension, ['status' => 'discarded']);
    }

    public function forBranch(Branch $branch): Collection
    {
        return $this->suspensions->suspendedForBranch($branch->id);
    }
}
