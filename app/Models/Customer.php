<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'store_id',
        'name',
        'nickname',
        'phone',
        'branch_id',
        'current_balance',
        'is_blocked',
        'notes',
    ];

    protected $attributes = [
        'current_balance' => 0,
        'is_blocked' => false,
    ];

    protected function casts(): array
    {
        return [
            'current_balance' => 'decimal:2',
            'is_blocked' => 'boolean',
        ];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function scopeOwing(Builder $query): Builder
    {
        return $query->where('current_balance', '>', 0);
    }
}
