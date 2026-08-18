<?php

namespace App\Models;

use App\Enums\StatusEnum;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Role extends Model
{
    /**
     * Roles are global and fixed: `superadmin`, `owner`, `tindera`, seeded
     * once by `SystemRoleSeeder` and not creatable through the API.
     * `Gate::before` resolves abilities through `users.role_id`, which is
     * unaffected by any of that.
     */
    use HasFactory;

    use LogsActivity;

    protected $fillable = [
        'name',
        'slug',
        'is_system',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
            'status' => StatusEnum::class,
        ];
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'role_permissions')->withTimestamps();
    }

    public function scopeSystem(Builder $query): Builder
    {
        return $query->where('is_system', true);
    }

    /**
     * The audit allow-list. Only these keys reach `activity_logs`.
     */
    public function activityAttributes(): array
    {
        return [
            'name',
            'slug',
            'status',
        ];
    }

    /**
     * Global — the three roles are shared by every store.
     */
    public function activityStoreId(): ?int
    {
        return null;
    }
}
