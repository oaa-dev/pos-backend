<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductPriceHistory extends Model
{
    use HasFactory;

    protected $table = 'product_price_history';

    protected $fillable = [
        'product_unit_id',
        'old_price',
        'new_price',
        'changed_by',
        'reason',
        'effective_at',
    ];

    protected function casts(): array
    {
        return [
            'old_price' => 'decimal:2',
            'new_price' => 'decimal:2',
            'effective_at' => 'datetime',
        ];
    }

    public function productUnit(): BelongsTo
    {
        return $this->belongsTo(ProductUnit::class);
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
