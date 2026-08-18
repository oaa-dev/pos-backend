<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('sku')->nullable()->unique();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('brand')->nullable();

            // Stock is always held in this unit. product_units carries the
            // sellable units and their conversion factor back to it.
            $table->foreignId('base_unit_id')->constrained('units');

            // What the tindera types when there is no barcode to scan — most
            // of the shelf. Short and numeric on purpose.
            $table->string('plu_code', 16)->nullable()->unique();

            // Free-text aliases: she searches "kopiko", not "Kopiko Blanca
            // 3-in-1 Coffee Mix 25g".
            $table->text('keywords')->nullable();

            $table->boolean('is_perishable')->default(false);
            $table->boolean('is_service')->default(false);
            $table->boolean('track_stock')->default(true);

            // Senior/PWD eligibility is per product, not blanket — the
            // discount applies to qualified goods, not to every retail item.
            $table->string('discount_eligibility')->default('eligible');

            // Drives the POS quick-button grid (yelo, itlog, load, softdrinks).
            $table->boolean('is_favorite')->default(false);
            $table->unsignedSmallInteger('favorite_sort')->default(0);

            $table->string('status')->default('active');
            $table->string('image_path')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'is_favorite']);
            $table->index('category_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
