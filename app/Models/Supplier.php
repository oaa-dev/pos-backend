<?php

namespace App\Models;

use App\Enums\StatusEnum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Supplier extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'store_id',
        'name',
        'contact_person',
        'phone',
        'email',
        'notes',
        'status',
    ];

    protected $attributes = [
        'status' => StatusEnum::ACTIVE,
    ];

    protected function casts(): array
    {
        return [
            'status' => StatusEnum::class,
        ];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /** Required by AddressService::upsertFor(). */
    public function address(): MorphOne
    {
        return $this->morphOne(Address::class, 'addressable');
    }

    public function products(): HasMany
    {
        return $this->hasMany(SupplierProduct::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', StatusEnum::ACTIVE);
    }
}
