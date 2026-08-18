<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CashDrawerSession extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'branch_id',
        'user_id',
        'opened_at',
        'opening_float',
        'closed_at',
        'closing_counted',
        'expected_cash',
        'variance',
        'status',
        'closing_notes',
        'closed_by',
    ];

    protected $attributes = [
        'opening_float' => 0,
        'status' => 'open',
    ];

    protected function casts(): array
    {
        return [
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
            'opening_float' => 'decimal:2',
            'closing_counted' => 'decimal:2',
            'expected_cash' => 'decimal:2',
            'variance' => 'decimal:2',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function cashMovements(): HasMany
    {
        return $this->hasMany(CashMovement::class);
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', 'open');
    }

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }

    /**
     * The audit allow-list. Only these keys reach `activity_logs`.
     */
    public function activityAttributes(): array
    {
        return [
            'branch_id',
            'opening_float',
            'closing_counted',
            'expected_cash',
            'variance',
            'status',
        ];
    }

    /**
     * Through the branch. `variance` is the number an owner opens this log to find.
     */
    public function activityStoreId(): ?int
    {
        return $this->loadMissing('branch')->branch?->store_id;
    }
}
