<?php

namespace App\Models;

use App\Enums\StatusEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Permission extends Model
{
    use HasFactory;

    protected $fillable = [
        'permission_group_id',
        'name',
        'description',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'status' => StatusEnum::class,
        ];
    }

    public function permissionGroup(): BelongsTo
    {
        return $this->belongsTo(PermissionGroup::class);
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_permissions')->withTimestamps();
    }
}
