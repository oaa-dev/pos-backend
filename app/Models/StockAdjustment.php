<?php

namespace App\Models;

use App\Enums\StockAdjustmentReasonEnum;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockAdjustment extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'reference_no',
        'branch_id',
        'reason',
        'note',
        'adjusted_by',
        'approved_by',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'reason' => StockAdjustmentReasonEnum::class,
            'occurred_at' => 'datetime',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(StockAdjustmentItem::class);
    }

    public function adjustedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'adjusted_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * The audit allow-list. Only these keys reach `activity_logs`.
     */
    public function activityAttributes(): array
    {
        return [
            'reference_no',
            'branch_id',
            'reason',
            'note',
            'approved_by',
        ];
    }

    /**
     * Through the branch.
     */
    public function activityStoreId(): ?int
    {
        return $this->loadMissing('branch')->branch?->store_id;
    }
}
