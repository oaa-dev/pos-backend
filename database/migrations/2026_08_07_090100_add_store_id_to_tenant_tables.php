<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Every table that belongs to exactly one store.
     *
     * The column is set from the request and filtered on by the request; there
     * is no global scope behind it. A repository that forgets to expose
     * `store_id` in `allowedFilters()` returns every customer's rows, and
     * nothing downstream will refuse them.
     *
     * Deliberately absent, and shared across every store: `units` (piraso and
     * kilo mean the same thing in every sari-sari), `discount_types` (`senior`
     * and `pwd` are what the law says, not what a shop decides),
     * `expense_categories`, `roles` and `users` (a person has one login and
     * one of three fixed roles), `permissions` / `permission_groups` (one
     * catalogue, pruned globally), and the PSGC address hierarchy.
     *
     * `product_units` is absent too, which is a known defect rather than a
     * decision: `barcode` stays globally unique, so the second store to try
     * registering Coke 1.5L is refused. Barcodes are universal product codes,
     * so that unique wants to be composite on `store_id`.
     *
     * @var list<string>
     */
    private const TABLES = [
        'branches',
        'products',
        'categories',
        'suppliers',
        'customers',
    ];

    public function up(): void
    {
        // Nullable first, backfill, then NOT NULL. The column cannot land
        // NOT NULL on a table that already has rows, and it must not stay
        // nullable afterwards: a null store_id is a row the tenant scope
        // cannot place, visible to nobody or — worse, if the scope is ever
        // relaxed — to everybody.
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->unsignedBigInteger('store_id')->nullable()->after('id');
            });
        }

        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->unsignedBigInteger('store_id')->nullable(false)->change();

                // No cascade on purpose. Force-deleting a store that still
                // holds data should fail loudly rather than silently take a
                // customer's entire history with it; the supported path is a
                // soft delete, which leaves the rows in place.
                $blueprint->foreign('store_id')->references('id')->on('stores');
                $blueprint->index('store_id');
            });
        }
    }

    public function down(): void
    {
        foreach (array_reverse(self::TABLES) as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                // Column arrays, not index names: passing a name inside an
                // array makes Laravel derive a name *from* it, which yields
                // `..._store_id_foreign_foreign` and fails to drop anything.
                $blueprint->dropForeign(['store_id']);
                $blueprint->dropIndex(['store_id']);
                $blueprint->dropColumn('store_id');
            });
        }
    }
};
