<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductUnit extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'product_id',
        'unit_id',
        'barcode',
        'conversion_factor',
        'selling_price',
        'is_base',
        'is_default_sale_unit',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            // Cast as string, not float: these are decimal columns and money
            // and stock arithmetic must not run through binary floating point.
            'conversion_factor' => 'decimal:4',
            'selling_price' => 'decimal:2',
            'is_base' => 'boolean',
            'is_default_sale_unit' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * withTrashed is load-bearing.
     *
     * Units are soft-deleted, and a trashed unit would resolve to null here.
     * `ProductUnitResource` emits the relation through the callback form of
     * whenLoaded, so null becomes an explicit null rather than an error — the
     * POS would render a sellable line with no unit name and nothing would
     * complain. Retiring a unit must not rewrite what a product is sold in.
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class)->withTrashed();
    }

    public function priceHistory(): HasMany
    {
        return $this->hasMany(ProductPriceHistory::class)->latest('effective_at');
    }

    /**
     * Convert a quantity entered in this unit into base units.
     *
     * Selling 3 strips of coffee (factor 10) removes 30 sachets from stock.
     * bcmath rather than float multiplication — 0.1 * 3 is not 0.3 in binary
     * floating point, and this figure is what the stock ledger stores.
     */
    public function toBaseQuantity(string|float|int $quantity): string
    {
        return bcmul((string) $quantity, (string) $this->conversion_factor, 3);
    }

    /**
     * Convert base units back into this unit — for display, never for storage.
     */
    public function fromBaseQuantity(string|float|int $baseQuantity): string
    {
        return bcdiv((string) $baseQuantity, (string) $this->conversion_factor, 3);
    }

    /**
     * The audit allow-list. Only these keys reach `activity_logs`.
     */
    public function activityAttributes(): array
    {
        return [
            'barcode',
            'conversion_factor',
            'selling_price',
            'is_base',
            'is_default_sale_unit',
        ];
    }

    /**
     * Reaches its store through the product — there is no `branch` here, and no
     * `store_id` either. Price changes are the entry this log is most often
     * opened to find, so a null store would be felt immediately.
     */
    public function activityStoreId(): ?int
    {
        return $this->loadMissing('product')->product?->store_id;
    }
}
