<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A selling unit — piraso, sachet, sako, kilo.
 *
 * Soft-deleted, because `products.base_unit_id` RESTRICTs and a product that
 * was sold in a retired unit still has to render its own history. Anything
 * reading a unit through a relation must use withTrashed(), or a retired unit
 * resolves to null and the product line loses its unit name silently.
 */
class Unit extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'abbreviation',
        'allows_fraction',
    ];

    protected function casts(): array
    {
        return ['allows_fraction' => 'boolean'];
    }
}
