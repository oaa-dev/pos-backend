<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductBatch extends Model
{
    use HasFactory;

    protected $fillable = [
        'branch_id',
        'product_id',
        'batch_code',
        'expiry_date',
        'quantity_remaining',
        'unit_cost',
        'received_at',
        'status',
    ];

    protected $attributes = [
        'quantity_remaining' => 0,
        'unit_cost' => 0,
        'status' => 'open',
    ];

    protected function casts(): array
    {
        return [
            'expiry_date' => 'date',
            'received_at' => 'datetime',
            'quantity_remaining' => 'decimal:3',
            'unit_cost' => 'decimal:4',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', 'open')->where('quantity_remaining', '>', 0);
    }

    /**
     * FEFO order: earliest expiry first, with non-expiring batches last.
     *
     * MySQL sorts NULL before everything on ASC, which would drain
     * non-expiring stock before food that is about to turn — the exact
     * opposite of what FEFO means. The ISNULL() term forces them to the back.
     */
    public function scopeFefo(Builder $query): Builder
    {
        return $query
            ->orderByRaw('expiry_date IS NULL')
            ->orderBy('expiry_date')
            ->orderBy('received_at')
            ->orderBy('id');
    }

    public function scopeExpiringBefore(Builder $query, string $date): Builder
    {
        return $query->whereNotNull('expiry_date')->whereDate('expiry_date', '<=', $date);
    }
}
