<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credit_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type');

            // Always positive; the type carries the direction. Storing a signed
            // amount as well would let the two disagree.
            $table->decimal('amount', 12, 2);

            // What the customer owed immediately after this row. Lets a
            // statement print a running balance without re-summing the ledger
            // for every line.
            $table->decimal('balance_after', 12, 2);

            // How much of a charge is still unsettled. Only meaningful on
            // charges; FIFO allocation draws these down.
            $table->decimal('outstanding', 12, 2)->default(0);

            $table->foreignId('sale_id')->nullable()->constrained()->nullOnDelete();

            // An utang payment lands in the drawer, so it has to reconcile
            // against the shift that received it.
            $table->foreignId('cash_drawer_session_id')->nullable()->constrained()->nullOnDelete();

            $table->date('due_date')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('note')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['customer_id', 'occurred_at']);
            // Drives FIFO allocation and the aging report.
            $table->index(['customer_id', 'type', 'outstanding']);
            $table->index('cash_drawer_session_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_transactions');
    }
};
