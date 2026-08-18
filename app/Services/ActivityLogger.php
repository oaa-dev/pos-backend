<?php

namespace App\Services;

use App\Enums\ActivityActionEnum;
use App\Repositories\Contracts\ActivityLogRepositoryInterface;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Throwable;

/**
 * The only writer of `activity_logs`.
 *
 * Two ways in: `record()` for the handful of decisions that carry a human
 * reason, `recordModelChange()` for the mechanical before/after diffs the
 * `LogsActivity` trait produces. One shape out.
 *
 * Three properties this class must hold, each of which has a test:
 *
 * 1. **It cannot throw.** It runs inside the caller's `DB::transaction` — the
 *    void, the write-off, the adjustment — so an exception here would roll back
 *    the business operation it was only supposed to describe. Failing to record
 *    an action is bad; failing to *perform* it because recording broke is worse.
 * 2. **No authenticated user means no row.** The trait fires in seeders, queued
 *    jobs and artisan commands, where `Auth::id()` is null. Writing those as
 *    "system" would fill the table on every `migrate:fresh --seed`. A console
 *    action that genuinely deserves an entry can call `record()` itself.
 * 3. **Suppression is restored in a `finally`.** A bare static toggle left set
 *    by an exception would silently disable logging for the rest of the
 *    request, which is the one failure an audit trail cannot afford.
 */
class ActivityLogger
{
    /**
     * Set only inside `withoutModelLogging()`, and only ever affects the trait.
     * Explicit `record()` calls are never suppressed — the caller asking for an
     * entry is the whole reason the flag exists.
     */
    private bool $modelLoggingSuppressed = false;

    public function __construct(private readonly ActivityLogRepositoryInterface $logs) {}

    /**
     * A named decision, with the reason behind it.
     *
     * `$old` and `$new` are passed by the caller, never diffed off the model —
     * an allow-list by construction, which is what keeps a password hash out of
     * `old_values` without anyone having to remember.
     */
    public function record(
        ActivityActionEnum $action,
        ?Model $subject = null,
        array $old = [],
        array $new = [],
        ?string $reason = null,
        ?int $storeId = null,
    ): void {
        $this->write(
            action: $action->value,
            subject: $subject,
            old: $old,
            new: $new,
            reason: $reason,
            storeId: $storeId,
        );
    }

    /**
     * A mechanical diff from the trait. `{model}.{event}` — `product.updated`.
     *
     * The action is a free string rather than an enum case: the trait covers
     * whatever models it is applied to, and an unmapped one must not throw here.
     */
    public function recordModelChange(
        Model $subject,
        string $event,
        array $old,
        array $new,
        ?int $storeId = null,
    ): void {
        if ($this->modelLoggingSuppressed) {
            return;
        }

        $this->write(
            action: $this->actionFor($subject, $event),
            subject: $subject,
            old: $old,
            new: $new,
            reason: null,
            storeId: $storeId,
        );
    }

    /**
     * Run `$callback` with the trait silenced, so an explicit `record()` for
     * the same write does not produce a second, reasonless row.
     *
     * The restore is in a `finally` on purpose: an exception inside the closure
     * must not leave logging off for everything that follows it.
     */
    public function withoutModelLogging(Closure $callback): mixed
    {
        $previous = $this->modelLoggingSuppressed;
        $this->modelLoggingSuppressed = true;

        try {
            return $callback();
        } finally {
            $this->modelLoggingSuppressed = $previous;
        }
    }

    /** Whether the trait should currently write. Read by `LogsActivity`. */
    public function modelLoggingEnabled(): bool
    {
        return ! $this->modelLoggingSuppressed;
    }

    /**
     * The single write path.
     *
     * Everything after the user check is wrapped: a JSON-encode failure on an
     * unexpected value, a missing column, a detached relation — none of them may
     * reach the caller. `report()` sends it to the log so a broken audit trail
     * is still discoverable rather than merely silent.
     */
    private function write(
        string $action,
        ?Model $subject,
        array $old,
        array $new,
        ?string $reason,
        ?int $storeId,
    ): void {
        $userId = Auth::id();

        // Property 2. No user, no row — see the class docblock.
        if ($userId === null) {
            return;
        }

        try {
            $request = request();

            $this->logs->create([
                'user_id' => $userId,
                'store_id' => $storeId,
                'action' => $action,
                'auditable_type' => $subject?->getMorphClass(),
                'auditable_id' => $subject?->getKey(),
                'old_values' => $old === [] ? null : $old,
                'new_values' => $new === [] ? null : $new,
                'reason' => $reason,
                // Both need a Request; null by construction from a queued job.
                'device' => $request?->userAgent(),
                'ip_address' => $request?->ip(),
            ]);
        } catch (Throwable $e) {
            // Property 1. Never let recording break the thing being recorded.
            report($e);
        }
    }

    /** `App\Models\Product` + `updated` → `product.updated`. */
    private function actionFor(Model $subject, string $event): string
    {
        $name = str(class_basename($subject))->kebab()->toString();

        return "{$name}.{$event}";
    }
}
