<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales', function (Blueprint $table) {
            $table->id();

            // Client-generated, unique — the idempotency key. A retried submit
            // returns the original sale instead of charging a suki twice.
            $table->uuid('uuid')->unique();

            $table->string('sale_number')->unique();
            $table->foreignId('branch_id')->constrained();
            $table->foreignId('cash_drawer_session_id')->constrained();
            $table->foreignId('user_id')->constrained();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();

            $table->string('status')->default('completed');
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('discount_total', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->decimal('amount_tendered', 12, 2)->default(0);
            $table->decimal('change_due', 12, 2)->default(0);
            $table->decimal('credit_amount', 12, 2)->default(0);

            $table->timestamp('sold_at');
            $table->timestamp('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('void_reason')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['branch_id', 'sold_at']);
            $table->index(['cash_drawer_session_id', 'status']);
            $table->index('customer_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales');
    }
};
