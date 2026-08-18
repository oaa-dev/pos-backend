<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BranchProductStock extends Model
{
    use HasFactory;

    protected $fillable = [
        'branch_id',
        'product_id',
        'quantity_on_hand',
        'average_cost',
        'reorder_point',
        'reorder_quantity',
        'last_counted_at',
    ];

    protected $attributes = [
        'quantity_on_hand' => 0,
        'average_cost' => 0,
        'reorder_point' => 0,
        'reorder_quantity' => 0,
    ];

    protected function casts(): array
    {
        return [
            'quantity_on_hand' => 'decimal:3',
            'average_cost' => 'decimal:4',
            'reorder_point' => 'decimal:3',
            'reorder_quantity' => 'decimal:3',
            'last_counted_at' => 'datetime',
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

    /**
     * Low stock is relative to a per-product reorder point, so a product with
     * no reorder point set is never "low" rather than always low.
     */
    public function scopeLow(Builder $query): Builder
    {
        return $query->where('reorder_point', '>', 0)
            ->whereColumn('quantity_on_hand', '<=', 'reorder_point');
    }
}
