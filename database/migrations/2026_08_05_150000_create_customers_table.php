<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('name');

            // The palayaw is how a tindera actually finds someone — "Aling
            // Nena", not "Elena Dela Cruz".
            $table->string('nickname')->nullable();

            $table->string('phone')->nullable();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('credit_limit', 12, 2)->default(0);

            // Cached. Phase 4 makes credit_transactions the source of truth and
            // this its running total; until then it is maintained directly.
            $table->decimal('current_balance', 12, 2)->default(0);

            // "Bawal muna umutang."
            $table->boolean('is_blocked')->default(false);

            $table->string('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['branch_id', 'is_blocked']);
            $table->index('name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
