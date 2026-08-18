<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaleDiscount extends Model
{
    use HasFactory;

    protected $fillable = [
        'sale_id',
        'sale_item_id',
        'discount_type_id',
        'type',
        'percentage',
        'amount',
        'amount_before',
        'amount_after',
        'id_number',
        'customer_name',
        'signature_path',
        'reason',
        'cashier_id',
        'approved_by',
    ];

    protected function casts(): array
    {
        return [
            'percentage' => 'decimal:2',
            'amount' => 'decimal:2',
            'amount_before' => 'decimal:2',
            'amount_after' => 'decimal:2',
        ];
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function saleItem(): BelongsTo
    {
        return $this->belongsTo(SaleItem::class);
    }
}
