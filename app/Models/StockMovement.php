<?php

namespace App\Models;

use App\Enums\StockMovementTypeEnum;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Append-only. Nothing updates or deletes a movement — corrections are new
 * movements in the opposite direction, so the ledger always explains itself.
 */
class StockMovement extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = [
        'ulid',
        'branch_id',
        'product_id',
        'product_unit_id',
        'quantity_entered',
        'quantity_base',
        'type',
        'batch_id',
        'unit_cost',
        'unit_cost_entered',
        'reference_type',
        'reference_id',
        'user_id',
        'occurred_at',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'type' => StockMovementTypeEnum::class,
            'quantity_entered' => 'decimal:3',
            'quantity_base' => 'decimal:3',
            'unit_cost' => 'decimal:4',
            'unit_cost_entered' => 'decimal:4',
            'occurred_at' => 'datetime',
        ];
    }

    /**
     * HasUlids would otherwise treat `ulid` as the primary key.
     */
    public function uniqueIds(): array
    {
        return ['ulid'];
    }

    public function getKeyName(): string
    {
        return 'id';
    }

    public function getIncrementing(): bool
    {
        return true;
    }

    public function getKeyType(): string
    {
        return 'int';
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function productUnit(): BelongsTo
    {
        return $this->belongsTo(ProductUnit::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(ProductBatch::class, 'batch_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }
}
