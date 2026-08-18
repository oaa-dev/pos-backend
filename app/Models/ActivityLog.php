<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * One recorded action.
 *
 * Written two ways and read one way. The `LogsActivity` trait records
 * mechanical before/after diffs on the models it is applied to;
 * `ActivityLogger::record()` records the handful of decisions that carry a
 * human `reason`. Both go through `ActivityLogger`, which is the only writer.
 *
 * The read surface is superadmin-only — see `PermissionSeeder::PLATFORM_MODULES`.
 */
class ActivityLog extends Model
{
    /**
     * Explicit rather than `$guarded = []`.
     *
     * The logger is the only writer, so this list is documentation of the
     * shape as much as a guard — and `old_values` / `new_values` are raw JSON
     * that `$hidden` does not touch, so what may be written there deserves to
     * be spelt out rather than left open.
     */
    protected $fillable = [
        'user_id',
        'store_id',
        'action',
        'auditable_type',
        'auditable_id',
        'old_values',
        'new_values',
        'reason',
        'device',
        'ip_address',
    ];

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
        ];
    }

    /** Who did it. Null once the account is deleted — the entry outlives it. */
    public function causer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** What it was done to. Null for actions with no single subject. */
    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }

    /** Where. Null for a genuinely global action, not for an unknown one. */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
}
