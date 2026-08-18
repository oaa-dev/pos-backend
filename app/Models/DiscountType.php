<?php

namespace App\Models;

use App\Enums\StatusEnum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A kind of discount the store can apply.
 *
 * Replaces the old `SaleDiscountTypeEnum`, whose six cases carried behaviour
 * in methods — `statutoryPercentage()`, `requiresIdentification()` — that are
 * now columns. The point is that the owner can add a promo without a
 * migration, while `senior` and `pwd` stay fixed at what the law says.
 */
class DiscountType extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'percentage',
        'requires_identification',
        'is_system',
        'status',
    ];

    protected $attributes = [
        'requires_identification' => false,
        'is_system' => false,
        'status' => StatusEnum::ACTIVE,
    ];

    protected function casts(): array
    {
        return [
            'percentage' => 'decimal:2',
            'requires_identification' => 'boolean',
            'is_system' => 'boolean',
            'status' => StatusEnum::class,
        ];
    }

    public function saleDiscounts(): HasMany
    {
        return $this->hasMany(SaleDiscount::class);
    }

    /**
     * The fields a statutory row refuses to change.
     *
     * Renaming `senior` to "Senior Citizen (RA 9994)" is housekeeping.
     * Changing its rate or its slug is not, and neither is switching off the
     * logbook — every sale after that point would be wrong in a way no later
     * report could detect.
     */
    public const PROTECTED_FIELDS = ['slug', 'percentage', 'requires_identification'];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', StatusEnum::ACTIVE);
    }
}
