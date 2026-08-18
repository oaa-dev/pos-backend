<?php

namespace App\Models;

use App\Enums\CreditTransactionTypeEnum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Append-only. A mistaken charge is reversed by an adjustment, never deleted —
 * so the statement a suki disputes always explains itself.
 */
class CreditTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'branch_id',
        'type',
        'amount',
        'balance_after',
        'outstanding',
        'sale_id',
        'cash_drawer_session_id',
        'due_date',
        'user_id',
        'approved_by',
        'note',
        'occurred_at',
    ];

    protected $attributes = [
        'outstanding' => 0,
    ];

    protected function casts(): array
    {
        return [
            'type' => CreditTransactionTypeEnum::class,
            'amount' => 'decimal:2',
            'balance_after' => 'decimal:2',
            'outstanding' => 'decimal:2',
            'due_date' => 'date',
            'occurred_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Allocations where this row is the payment doing the settling. */
    public function allocations(): HasMany
    {
        return $this->hasMany(CreditAllocation::class, 'payment_transaction_id');
    }

    /** Allocations where this row is the charge being settled. */
    public function settlements(): HasMany
    {
        return $this->hasMany(CreditAllocation::class, 'charge_transaction_id');
    }

    /** Charges still carrying a balance, oldest first — the FIFO queue. */
    public function scopeUnsettledCharges(Builder $query): Builder
    {
        return $query
            ->where('type', CreditTransactionTypeEnum::CHARGE)
            ->where('outstanding', '>', 0)
            ->orderBy('occurred_at')
            ->orderBy('id');
    }

    public function isOverdue(): bool
    {
        return $this->due_date !== null
            && bccomp((string) $this->outstanding, '0', 2) > 0
            && $this->due_date->isPast();
    }

    /** Whole days a charge has been outstanding, for the aging report. */
    public function ageInDays(): int
    {
        return (int) $this->occurred_at->startOfDay()->diffInDays(now()->startOfDay());
    }
}
