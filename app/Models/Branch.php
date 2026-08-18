<?php

namespace App\Models;

use App\Enums\StatusEnum;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Branch extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    /**
     * `store_id` is fillable on purpose: it arrives in the request and no
     * scope narrows it. Whichever store the caller names is the store the row
     * lands in.
     */
    protected $fillable = [
        'store_id',
        'name',
        'code',
        'phone',
        'status',
        'opened_at',
    ];

    protected $attributes = [
        'status' => StatusEnum::ACTIVE,
    ];

    protected function casts(): array
    {
        return [
            'status' => StatusEnum::class,
            'opened_at' => 'date',
        ];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', StatusEnum::ACTIVE);
    }

    /**
     * Required by AddressService::upsertFor(), which refuses any owner that
     * does not declare this relation.
     */
    public function address(): MorphOne
    {
        return $this->morphOne(Address::class, 'addressable');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot('is_primary')
            ->withTimestamps();
    }

    public function productStocks(): HasMany
    {
        return $this->hasMany(BranchProductStock::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    /**
     * The audit allow-list. Only these keys reach `activity_logs`.
     */
    public function activityAttributes(): array
    {
        return [
            'store_id',
            'name',
            'code',
            'phone',
            'status',
        ];
    }

    /**
     * Carries `store_id` itself.
     */
    public function activityStoreId(): ?int
    {
        return $this->store_id;
    }
}
