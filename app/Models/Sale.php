<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sale extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'sale_number',
        'branch_id',
        'cash_drawer_session_id',
        'user_id',
        'customer_id',
        'status',
        'subtotal',
        'discount_total',
        'total',
        'amount_tendered',
        'change_due',
        'credit_amount',
        'sold_at',
        'voided_at',
        'voided_by',
        'void_reason',
        'approved_by',
    ];

    protected $attributes = [
        'status' => 'completed',
        'subtotal' => 0,
        'discount_total' => 0,
        'total' => 0,
        'amount_tendered' => 0,
        'change_due' => 0,
        'credit_amount' => 0,
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'discount_total' => 'decimal:2',
            'total' => 'decimal:2',
            'amount_tendered' => 'decimal:2',
            'change_due' => 'decimal:2',
            'credit_amount' => 'decimal:2',
            'sold_at' => 'datetime',
            'voided_at' => 'datetime',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(CashDrawerSession::class, 'cash_drawer_session_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SalePayment::class);
    }

    public function discounts(): HasMany
    {
        return $this->hasMany(SaleDiscount::class);
    }

    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', 'completed');
    }

    public function isVoided(): bool
    {
        return $this->status === 'voided';
    }
}
