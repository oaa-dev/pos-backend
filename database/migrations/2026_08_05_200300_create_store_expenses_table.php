<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expense_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('description')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();
        });

        Schema::create('store_expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained();
            $table->foreignId('expense_category_id')->constrained();
            $table->decimal('amount', 12, 2);
            $table->string('description')->nullable();

            // Where the money came from. Only `drawer` affects the shift count —
            // an expense from the owner's own pocket reduces profit without
            // touching the till.
            $table->string('paid_from')->default('drawer');

            $table->foreignId('cash_drawer_session_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->string('receipt_path')->nullable();
            $table->date('incurred_at');
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['branch_id', 'incurred_at']);
            $table->index('expense_category_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_expenses');
        Schema::dropIfExists('expense_categories');
    }
};
