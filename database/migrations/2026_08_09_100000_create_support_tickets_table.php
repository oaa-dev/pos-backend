<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_tickets', function (Blueprint $table) {
            $table->id();

            // Plain indexed column, no foreign key. A ticket is the record of a
            // conversation and should outlive the rows it points at — the same
            // reasoning `activity_logs.store_id` carries.
            $table->unsignedBigInteger('store_id')->index();

            // The reporter. Nullable and nullOnDelete: staff leave, and the
            // ticket they filed is still the operator's to answer.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            $table->string('subject');
            $table->text('body');
            $table->string('category')->default('bug');
            $table->string('priority')->default('normal');
            $table->string('status')->default('open');

            // One screenshot. A bug report describing a visual problem in words
            // is usually a round trip that an image would have saved.
            $table->string('attachment_path')->nullable();

            // Stamped by every reply. Nothing reads it yet — it exists so an
            // unread badge is a frontend change later rather than a migration.
            $table->timestamp('last_replied_at')->nullable();
            $table->timestamp('resolved_at')->nullable();

            $table->timestamps();

            $table->index('status');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_tickets');
    }
};
