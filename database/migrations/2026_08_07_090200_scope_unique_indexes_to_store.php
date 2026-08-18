<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Uniques that were correct for one store and wrong for two.
     *
     * The barcode is the one that decides whether a second customer can use
     * the product screen at all: barcodes are universal product codes, so a
     * globally unique column means the first store to register Coke 1.5L
     * prevents every other store from ever doing so. The rest are the same
     * mistake in a quieter form — two shops both wanting a branch coded MAIN,
     * a role `owner`, a category `Inumin`, a `senior` discount.
     *
     * NULLs stay distinct under MySQL's composite uniques, so products without
     * a SKU is unaffected.
     *
     * `discount_types.slug` and `roles.slug` are **not** here: both tables are
     * shared across every store, so their global uniques are correct as
     * created. `product_units.barcode` is not here either, and that one is a
     * known defect — see `add_store_id_to_tenant_tables`.
     *
     * @var array<string, string> table => column
     */
    private const SCOPED = [
        'branches' => 'code',
        'categories' => 'slug',
        'products' => 'sku',
    ];

    public function up(): void
    {
        foreach (self::SCOPED as $table => $column) {
            Schema::table($table, function (Blueprint $blueprint) use ($column) {
                $blueprint->dropUnique([$column]);
                $blueprint->unique(['store_id', $column]);
            });
        }
    }

    public function down(): void
    {
        // Reversible only while one store exists. With two, the rows that this
        // migration made legal are exactly the rows a global unique rejects,
        // and the index creation fails — which is the correct outcome: there
        // is no single-tenant shape for multi-tenant data.
        foreach (array_reverse(self::SCOPED) as $table => $column) {
            Schema::table($table, function (Blueprint $blueprint) use ($column) {
                $blueprint->dropUnique(['store_id', $column]);
                $blueprint->unique([$column]);
            });
        }
    }
};
