<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();

            // The unit the operator actually entered, kept alongside the base
            // figure so a movement can be replayed and explained ("3 strips"),
            // not just summed.
            $table->foreignId('product_unit_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('quantity_entered', 14, 3);

            // Signed, always in the product's base unit. This is the ONLY
            // column that may be summed.
            $table->decimal('quantity_base', 14, 3);

            $table->string('type');
            $table->foreignId('batch_id')->nullable()->constrained('product_batches')->nullOnDelete();

            // Two costs for the same reason there are two quantities.
            // `unit_cost` is per *base* unit — a ₱11 dosena of 12 is ₱0.9167 a
            // piraso — because FIFO costing works in base units and would be
            // wrong by the conversion factor otherwise.
            //
            // `unit_cost_entered` is what was actually typed, kept because
            // deriving it back drifts on the rounding: ₱100 over 12 is ₱8.3333,
            // which multiplies back to ₱99.9996 rather than the ₱100 paid.
            // Reconciling a ledger row against an invoice needs the figure on
            // the invoice.
            $table->decimal('unit_cost', 12, 4)->nullable();
            $table->decimal('unit_cost_entered', 12, 4)->nullable();
            $table->nullableMorphs('reference');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('occurred_at');
            $table->string('note')->nullable();
            $table->timestamps();

            $table->index(['branch_id', 'product_id', 'occurred_at']);
            $table->index(['branch_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
