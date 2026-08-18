<?php

namespace App\Models;

use App\Enums\AccountStatusEnum;
use App\Traits\HasPermissions;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, HasPermissions, LogsActivity, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'phone_number',
        'password',
        'status',
        'role_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        // Deliberately absent from $fillable too — only ApprovalService sets
        // it, and nothing may ever serialise it.
        'approval_pin',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'approval_pin' => 'hashed',
            'status' => AccountStatusEnum::class,
        ];
    }

    public function profile(): HasOne
    {
        return $this->hasOne(UserProfile::class);
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * A tindera is assigned to one branch; an owner is assigned to none and
     * relies on the `branches.view-all` permission instead.
     */
    public function branches(): BelongsToMany
    {
        return $this->belongsToMany(Branch::class)
            ->withPivot('is_primary')
            ->withTimestamps();
    }

    public function stores(): BelongsToMany
    {
        return $this->belongsToMany(Store::class, 'store_users')
            ->withPivot('is_owner')
            ->withTimestamps();
    }

    /**
     * The store this account belongs to.
     *
     * `users` has no `store_id`; `store_users` is the whole record of it. A
     * user with no pivot row resolves to null and `StoreService::currentFor()`
     * turns that into a 404 rather than leaking another store's row.
     *
     * Singular because a person works at one sari-sari. `stores()` is the
     * honest shape of the table and stays available for the operator case.
     */
    public function store(): HasOneThrough
    {
        return $this->hasOneThrough(
            Store::class,
            StoreUser::class,
            'user_id',
            'id',
            'id',
            'store_id',
        )->oldest('stores.id');
    }

    /** Whether this account owns the store it belongs to. */
    public function ownsStore(): bool
    {
        return $this->stores()->wherePivot('is_owner', true)->exists();
    }

    /**
     * The audit allow-list. Only these keys reach `activity_logs`.
     */
    public function activityAttributes(): array
    {
        return [
            'name',
            'email',
            'phone_number',
            'status',
            'role_id',
        ];
    }

    /**
     * Global: `users` has no `store_id`, and membership lives on the `store_users` pivot — a user can predate any store row.
     *
     * **`password` and `approval_pin` are absent from the allow-list and must stay
     * absent.** Both are in `$fillable`, so a diff built from that instead of
     * from this list would persist the hash into a raw JSON column that
     * `$hidden` does not reach.
     */
    public function activityStoreId(): ?int
    {
        return null;
    }
}
