<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_ticket_replies', function (Blueprint $table) {
            $table->id();

            // Cascades: a reply has no meaning without its ticket. This is the
            // one place a hard delete is right, and only because the parent
            // cannot be deleted through the API either.
            $table->foreignId('support_ticket_id')->constrained()->cascadeOnDelete();

            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('body');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_ticket_replies');
    }
};
