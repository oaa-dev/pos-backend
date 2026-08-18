<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A barcode belongs to the selling unit it is printed on. A box of
        // Kopiko and a loose sachet carry different codes, and scanning the
        // box has to sell a box.
        //
        // Nullable because most units genuinely have nothing printed on them —
        // a sachet pulled from an opened box has no code of its own. Unique so
        // a scan resolves to exactly one row, which is what removes the
        // default-unit fallback the old nullable link needed.
        Schema::table('product_units', function (Blueprint $table) {
            $table->string('barcode')->nullable()->unique()->after('unit_id');
        });

        $this->carryExistingBarcodesOver();

        Schema::dropIfExists('product_barcodes');

        Schema::table('products', function (Blueprint $table) {
            // Used only by the product form and the free-text search filter.
            // findByPlu() was declared, implemented, and called by nothing.
            $table->dropColumn(['brand', 'plu_code', 'keywords']);
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('brand')->nullable()->after('category_id');
            $table->string('plu_code', 16)->nullable()->unique()->after('base_unit_id');
            $table->text('keywords')->nullable()->after('plu_code');
        });

        Schema::create('product_barcodes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_unit_id')->nullable()->constrained()->nullOnDelete();
            $table->string('barcode')->unique();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->index('product_id');
        });

        // Codes go back to the table, but which unit they belonged to is the
        // one thing this cannot restore beyond what the column holds.
        DB::table('product_units')->whereNotNull('barcode')->orderBy('id')
            ->each(function ($unit) {
                DB::table('product_barcodes')->insert([
                    'product_id' => $unit->product_id,
                    'product_unit_id' => $unit->id,
                    'barcode' => $unit->barcode,
                    'is_primary' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });

        Schema::table('product_units', function (Blueprint $table) {
            $table->dropUnique(['barcode']);
            $table->dropColumn('barcode');
        });
    }

    /**
     * Move existing codes onto a unit before the table goes.
     *
     * A row that already names a unit keeps it. One that does not — every row
     * in this database today — lands on the product's default sale unit, which
     * is precisely what the old fallback resolved it to at scan time. So
     * nothing changes about what those codes sell; the guess simply becomes
     * explicit and editable.
     *
     * First code wins per unit, since the column holds one.
     */
    private function carryExistingBarcodesOver(): void
    {
        if (! Schema::hasTable('product_barcodes')) {
            return;
        }

        $taken = [];

        DB::table('product_barcodes')->orderBy('id')->each(function ($row) use (&$taken) {
            $unitId = $row->product_unit_id ?? DB::table('product_units')
                ->where('product_id', $row->product_id)
                ->orderByDesc('is_default_sale_unit')
                ->orderBy('sort_order')
                ->value('id');

            if ($unitId === null || isset($taken[$unitId])) {
                return;
            }

            DB::table('product_units')->where('id', $unitId)->update(['barcode' => $row->barcode]);
            $taken[$unitId] = true;
        });
    }
};
