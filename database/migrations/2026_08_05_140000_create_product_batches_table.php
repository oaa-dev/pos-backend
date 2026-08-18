<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('batch_code')->nullable();

            // Null for goods that do not expire. Those batches still exist so
            // FIFO costing works uniformly across the catalogue.
            $table->date('expiry_date')->nullable();

            $table->decimal('quantity_remaining', 14, 3)->default(0);
            $table->decimal('unit_cost', 12, 4)->default(0);
            $table->timestamp('received_at');
            $table->string('status')->default('open');
            $table->timestamps();

            // Drives FEFO: earliest expiry first, nulls last.
            $table->index(['branch_id', 'product_id', 'expiry_date']);
            $table->index(['branch_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_batches');
    }
};
