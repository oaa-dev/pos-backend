<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credit_allocations', function (Blueprint $table) {
            $table->id();

            // The payment (or writeoff) doing the settling.
            $table->foreignId('payment_transaction_id')
                ->constrained('credit_transactions')
                ->cascadeOnDelete();

            // The charge being settled.
            $table->foreignId('charge_transaction_id')
                ->constrained('credit_transactions')
                ->cascadeOnDelete();

            $table->decimal('amount', 12, 2);
            $table->timestamps();

            $table->unique(['payment_transaction_id', 'charge_transaction_id'], 'credit_allocations_pair_unique');
            $table->index('charge_transaction_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_allocations');
    }
};
