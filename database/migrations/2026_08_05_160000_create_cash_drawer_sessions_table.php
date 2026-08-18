<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_drawer_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained();
            $table->timestamp('opened_at');
            $table->decimal('opening_float', 12, 2)->default(0);
            $table->timestamp('closed_at')->nullable();

            // What the tindera physically counted at close.
            $table->decimal('closing_counted', 12, 2)->nullable();

            // What the ledger says should be there.
            $table->decimal('expected_cash', 12, 2)->nullable();

            // counted - expected. Negative is shortage, positive overage.
            $table->decimal('variance', 12, 2)->nullable();

            $table->string('status')->default('open');
            $table->string('closing_notes')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // MySQL cannot express "one *open* row per branch" as a partial
            // unique index, so the guard is a locked read in CashDrawerService.
            $table->index(['branch_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_drawer_sessions');
    }
};
