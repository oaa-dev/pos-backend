<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branch_product_stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();

            // A cache of SUM(stock_movements.quantity_base). Always
            // recomputable; `inventory:reconcile` reports any drift.
            $table->decimal('quantity_on_hand', 14, 3)->default(0);

            // Display figure only — actual cost of goods sold comes from the
            // batch a movement drew from.
            $table->decimal('average_cost', 12, 4)->default(0);

            $table->decimal('reorder_point', 14, 3)->default(0);
            $table->decimal('reorder_quantity', 14, 3)->default(0);
            $table->timestamp('last_counted_at')->nullable();
            $table->timestamps();

            $table->unique(['branch_id', 'product_id']);
            $table->index(['branch_id', 'quantity_on_hand']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('branch_product_stocks');
    }
};
