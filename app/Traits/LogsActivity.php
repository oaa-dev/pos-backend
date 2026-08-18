<?php

namespace App\Traits;

use App\Services\ActivityLogger;

/**
 * Records create/update/delete on the model using it.
 *
 * The counterpart to `ActivityLogger::record()`: this catches the changes
 * nobody thought to log explicitly — including ones made from tinker or a code
 * path written next year — while `record()` catches the decisions that carry a
 * human reason. A write that does both wraps itself in
 * `ActivityLogger::withoutModelLogging()` so it produces one row, not two.
 *
 * Two methods are required of every user of this trait, and neither has a
 * default. That is deliberate:
 *
 * - `activityAttributes()` is a **redaction allow-list**. `old_values` and
 *   `new_values` are raw JSON columns; `$hidden` does not apply to them, so a
 *   trait that diffed whole rows would persist password hashes and approval
 *   PINs. Only the keys named here are ever read.
 * - `activityStoreId()` is how *this* model reaches its store. Only `products`,
 *   `branches` and `stores` carry `store_id` themselves — `sales`,
 *   `store_expenses`, `stock_adjustments`, `cash_drawer_sessions` and
 *   `stock_transfers` reach it through a branch, `product_units` through a
 *   product. A default of `$this->store_id` would silently write null for most
 *   of them and quietly make the log's store filter useless.
 */
trait LogsActivity
{
    /**
     * The attributes worth recording, and the only ones ever read.
     *
     * @return list<string>
     */
    abstract public function activityAttributes(): array;

    /** The store this row belongs to, or null for a genuinely global record. */
    abstract public function activityStoreId(): ?int;

    public static function bootLogsActivity(): void
    {
        static::created(function ($model) {
            $model->recordActivity('created', [], $model->activityValues());
        });

        static::updated(function ($model) {
            $changed = array_intersect_key(
                $model->getChanges(),
                array_flip($model->activityAttributes()),
            );

            // A touch(), or a write that only moved columns nobody asked to
            // watch, is not an event. Without this every save writes an entry
            // with two empty objects.
            if ($changed === []) {
                return;
            }

            $model->recordActivity(
                'updated',
                array_intersect_key($model->getOriginal(), $changed),
                $changed,
            );
        });

        static::deleted(function ($model) {
            $model->recordActivity('deleted', $model->activityValues(), []);
        });
    }

    /** The allow-listed slice of the current attributes. */
    protected function activityValues(): array
    {
        return array_intersect_key(
            $this->getAttributes(),
            array_flip($this->activityAttributes()),
        );
    }

    /**
     * Resolved from the container rather than injected — a model cannot take
     * constructor dependencies. `ActivityLogger` is bound as a singleton in
     * `AppServiceProvider`, so this is the same instance a service holds, and
     * the suppression flag one sets is the flag the other reads.
     */
    protected function recordActivity(string $event, array $old, array $new): void
    {
        $logger = app(ActivityLogger::class);

        if (! $logger->modelLoggingEnabled()) {
            return;
        }

        $logger->recordModelChange(
            subject: $this,
            event: $event,
            old: $old,
            new: $new,
            storeId: $this->activityStoreId(),
        );
    }
}
