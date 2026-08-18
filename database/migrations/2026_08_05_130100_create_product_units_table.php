<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('unit_id')->constrained();

            // How many base units this one is worth. Coffee based in sachet:
            // sachet = 1, strip = 10, box = 100. Decimal because a kilo of
            // bigas out of a sako is not a whole number of base units.
            $table->decimal('conversion_factor', 12, 4);

            $table->decimal('selling_price', 12, 2);

            // Exactly one row per product carries is_base with factor 1.
            $table->boolean('is_base')->default(false);

            // What the POS preselects when the item is added to the cart.
            $table->boolean('is_default_sale_unit')->default(false);

            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['product_id', 'unit_id']);
            $table->index(['product_id', 'is_default_sale_unit']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_units');
    }
};
