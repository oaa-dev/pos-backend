<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_sales_summaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->date('business_date');
            $table->unsignedInteger('sale_count')->default(0);
            $table->decimal('gross_sales', 12, 2)->default(0);
            $table->decimal('returns_total', 12, 2)->default(0);
            $table->decimal('net_sales', 12, 2)->default(0);
            $table->decimal('cash_total', 12, 2)->default(0);
            $table->timestamp('z_read_at');
            $table->foreignId('z_read_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['branch_id', 'business_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_sales_summaries');
    }
};
