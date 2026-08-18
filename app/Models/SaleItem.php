<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SaleItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'sale_id',
        'product_id',
        'product_unit_id',
        'quantity',
        'quantity_base',
        'unit_price',
        'unit_cost',
        'product_name_snapshot',
        'unit_name_snapshot',
        'line_discount',
        'line_total',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'quantity_base' => 'decimal:3',
            'unit_price' => 'decimal:2',
            'unit_cost' => 'decimal:4',
            'line_discount' => 'decimal:2',
            'line_total' => 'decimal:2',
        ];
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function productUnit(): BelongsTo
    {
        return $this->belongsTo(ProductUnit::class);
    }

    public function discounts(): HasMany
    {
        return $this->hasMany(SaleDiscount::class);
    }

    /** Gross margin on this line, using the FIFO cost actually issued. */
    public function grossProfit(): string
    {
        $cost = bcmul((string) $this->quantity_base, (string) $this->unit_cost, 2);

        return bcsub((string) $this->line_total, $cost, 2);
    }
}
