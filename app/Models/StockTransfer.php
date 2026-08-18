<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Stock moving between two branches of the same store.
 *
 * Three states, and the split matters: stock leaves the origin when the
 * transfer is **sent** and arrives when it is **received**, so goods in transit
 * belong to neither branch and cannot be sold twice.
 */
class StockTransfer extends Model
{
    use LogsActivity;

    public const PENDING = 'pending';

    public const SENT = 'sent';

    public const RECEIVED = 'received';

    protected $fillable = [
        'from_branch_id',
        'to_branch_id',
        'created_by',
        'transfer_number',
        'status',
        'notes',
        'sent_at',
        'received_at',
    ];

    /**
     * Mirrors the column default.
     *
     * Without this the insert takes the database's `pending` but the in-memory
     * instance never learns it — so `send()` on the object `create()` just
     * returned saw `status = null` and refused it. The same trap
     * `Product::$attributes` documents.
     */
    protected $attributes = [
        'status' => self::PENDING,
    ];

    protected function casts(): array
    {
        return ['sent_at' => 'datetime', 'received_at' => 'datetime'];
    }

    public function fromBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'from_branch_id');
    }

    public function toBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'to_branch_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(StockTransferItem::class);
    }

    /** The movements this transfer wrote, both legs. */
    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class, 'reference_id')
            ->where('reference_type', self::class);
    }

    public function scopeStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    /**
     * The audit allow-list. Only these keys reach `activity_logs`.
     */
    public function activityAttributes(): array
    {
        return [
            'from_branch_id',
            'to_branch_id',
            'status',
            'notes',
        ];
    }

    /**
     * Through the origin branch — both ends are in the same store by construction, so either would do.
     */
    public function activityStoreId(): ?int
    {
        return $this->loadMissing('fromBranch')->fromBranch?->store_id;
    }
}
