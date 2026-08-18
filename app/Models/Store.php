<?php

namespace App\Models;

use App\Enums\StatusEnum;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Database\Eloquent\SoftDeletes;

class Store extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'slug',
        'owner_name',
        'phone',
        'logo_path',
        'status',
    ];

    protected $attributes = [
        'status' => 'active',
    ];

    protected function casts(): array
    {
        return [
            'status' => StatusEnum::class,
        ];
    }

    /**
     * Everyone attached to this store, with `is_owner` on the pivot.
     *
     * `store_users` is the *only* record of membership — `users` carries no
     * `store_id`. Forgetting to write the pivot row leaves a user attached to
     * no store, with no error anywhere.
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'store_users')
            ->withPivot('is_owner')
            ->withTimestamps();
    }

    /**
     * The account that owns this store.
     *
     * Reads the pivot rather than inferring ownership from a role slug. The
     * old form matched `role.slug = 'owner'`, which made ownership a fact the
     * owner could change by renaming her own role.
     *
     * Reaches through `StoreUser` rather than constraining a `hasOne` with a
     * `whereExists`, because a subquery bound to `$this->getKey()` resolves
     * every row against whichever store defined the relation — eager-loading
     * the `/stores` index would hand back one store's owner for all of them.
     * `hasOneThrough` builds a proper `whereIn` over the parent keys.
     *
     * `StoreService::addOwner()` refuses a second owner, so the `oldest()` tie
     * break should never actually be needed.
     */
    public function owner(): HasOneThrough
    {
        return $this->hasOneThrough(
            User::class,
            StoreUser::class,
            'store_id',
            'id',
            'id',
            'user_id',
        )->where('store_users.is_owner', true)->oldest('users.id');
    }

    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function categories(): HasMany
    {
        return $this->hasMany(Category::class);
    }

    public function suppliers(): HasMany
    {
        return $this->hasMany(Supplier::class);
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    /**
     * The audit allow-list. Only these keys reach `activity_logs`.
     */
    public function activityAttributes(): array
    {
        return [
            'name',
            'slug',
            'owner_name',
            'phone',
            'status',
        ];
    }

    /**
     * It is the store.
     */
    public function activityStoreId(): ?int
    {
        return $this->getKey();
    }
}
