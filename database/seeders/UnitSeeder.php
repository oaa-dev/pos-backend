<?php

namespace Database\Seeders;

use App\Models\Unit;
use Illuminate\Database\Seeder;

class UnitSeeder extends Seeder
{
    /**
     * The units a sari-sari actually sells in. `piraso` is the usual base unit
     * a product's stock is held in; the rest become sellable units with a
     * conversion factor once products land in Phase 1.
     *
     * Not pruned, unlike the permission catalogue: a unit may already be
     * referenced by a product, and deleting it would orphan that reference.
     *
     * @var array<string, string> name => abbreviation
     */
    private const UNITS = [
        'piraso' => 'pc',
        'sachet' => 'sct',
        'pack' => 'pck',
        'kaha' => 'kha',
        'dosena' => 'dz',
        'kilo' => 'kg',
        'gramo' => 'g',
        'litro' => 'L',
        'sako' => 'sk',
        'bote' => 'btl',
    ];

    /**
     * The measured units — half of one is a real quantity.
     *
     * Everything else is counted, and a fractional reorder point for it
     * ("1.806 piraso") cannot be acted on at a shelf.
     *
     * @var list<string>
     */
    private const FRACTIONAL = ['kilo', 'gramo', 'litro'];

    public function run(): void
    {
        foreach (self::UNITS as $name => $abbreviation) {
            // firstOrCreate, not create: db:seed must stay re-runnable and
            // units.name is unique.
            Unit::firstOrCreate(['name' => $name], [
                'abbreviation' => $abbreviation,
                'allows_fraction' => in_array($name, self::FRACTIONAL, true),
            ]);
        }
    }
}
