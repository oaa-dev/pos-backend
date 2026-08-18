<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_barcodes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();

            // Nullable: a barcode printed on the box identifies the box unit,
            // but a product may also carry a generic code that maps to no
            // particular selling unit.
            $table->foreignId('product_unit_id')->nullable()->constrained()->nullOnDelete();

            $table->string('barcode')->unique();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->index('product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_barcodes');
    }
};
