<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sale_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained();
            $table->foreignId('product_unit_id')->constrained();

            $table->decimal('quantity', 14, 3);
            $table->decimal('quantity_base', 14, 3);
            $table->decimal('unit_price', 12, 2);

            // Quantity-weighted average of the batches this line drew from. A
            // line spanning two batches has two costs, and taking only the
            // first would misprice every sale that crosses a batch boundary.
            $table->decimal('unit_cost', 12, 4)->default(0);

            // Snapshots: a receipt reprinted next year must read the same even
            // if the product has since been renamed or repriced.
            $table->string('product_name_snapshot');
            $table->string('unit_name_snapshot');

            $table->decimal('line_discount', 12, 2)->default(0);
            $table->decimal('line_total', 12, 2);
            $table->timestamps();

            $table->index('product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_items');
    }
};
