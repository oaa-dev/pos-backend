<?php

namespace App\Models;

use App\Enums\StatusEnum;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'store_id',
        'name',
        'sku',
        'category_id',
        'base_unit_id',
        'is_perishable',
        'is_service',
        'track_stock',
        'is_favorite',
        'favorite_sort',
        'status',
        'image_path',
    ];

    /**
     * Mirrors the column defaults. Without these, a model created without the
     * field returns null from the API until it is re-read — the insert takes
     * the database default, but the in-memory instance never learns it.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_perishable' => false,
        'is_service' => false,
        'track_stock' => true,
        'is_favorite' => false,
        'favorite_sort' => 0,
        'status' => StatusEnum::ACTIVE,
    ];

    protected function casts(): array
    {
        return [
            'is_perishable' => 'boolean',
            'is_service' => 'boolean',
            'track_stock' => 'boolean',
            'is_favorite' => 'boolean',
            'favorite_sort' => 'integer',
            'status' => StatusEnum::class,
        ];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /** withTrashed: a retired category must not blank an existing product's. */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class)->withTrashed();
    }

    /** withTrashed: see ProductUnit::unit() — a retired unit must still resolve. */
    public function baseUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'base_unit_id')->withTrashed();
    }

    /** What each supplier last charged, and in which unit they sell it. */
    public function supplierProducts(): HasMany
    {
        return $this->hasMany(SupplierProduct::class);
    }

    public function units(): HasMany
    {
        return $this->hasMany(ProductUnit::class)->orderBy('sort_order');
    }

    public function baseProductUnit(): HasOne
    {
        return $this->hasOne(ProductUnit::class)->where('is_base', true);
    }

    public function defaultSaleUnit(): HasOne
    {
        return $this->hasOne(ProductUnit::class)->where('is_default_sale_unit', true);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', StatusEnum::ACTIVE);
    }

    public function scopeFavorites(Builder $query): Builder
    {
        return $query->where('is_favorite', true)->orderBy('favorite_sort');
    }

    /**
     * The audit allow-list. Only these keys reach `activity_logs`.
     */
    public function activityAttributes(): array
    {
        return [
            'name',
            'sku',
            'category_id',
            'base_unit_id',
            'status',
            'track_stock',
        ];
    }

    /**
     * Carries `store_id` itself — one of only three that do.
     */
    public function activityStoreId(): ?int
    {
        return $this->store_id;
    }
}
