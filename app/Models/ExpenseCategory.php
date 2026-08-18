<?php

namespace App\Models;

use App\Enums\StatusEnum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Shared across every store, like `units` and `discount_types` — "Kuryente"
 * and "Tubig" are the same line items in any sari-sari, so there is no
 * `store_id` here and `DatabaseSeeder` seeds the list once.
 */
class ExpenseCategory extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'description', 'status'];

    protected $attributes = ['status' => StatusEnum::ACTIVE];

    protected function casts(): array
    {
        return ['status' => StatusEnum::class];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', StatusEnum::ACTIVE);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(StoreExpense::class);
    }
}
