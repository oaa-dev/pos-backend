<?php

namespace App\Models;

use App\Enums\StatusEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PermissionGroup extends Model
{
    use HasFactory;

    protected $fillable = [
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

    public function permissions(): HasMany
    {
        return $this->hasMany(Permission::class);
    }
}
