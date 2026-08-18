<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sale_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained()->cascadeOnDelete();

            // Several rows per sale: bahagya cash, bahagya utang is common.
            $table->string('method');
            $table->decimal('amount', 12, 2);
            $table->string('reference_no')->nullable();
            $table->timestamps();

            $table->index(['sale_id', 'method']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_payments');
    }
};
