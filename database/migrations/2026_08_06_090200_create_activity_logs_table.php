<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();

            // Nullable and nullOnDelete: an entry outlives the account that
            // made it. A deleted user reads as an unattributed action, which is
            // still evidence — deleting the trail with the user would not be.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            // The store the action happened in. **Nullable is meaningful**: null
            // is a genuinely global action — a role edit, a platform login —
            // not an unknown one. It is a filter for the operator reading
            // several customers' trails at once, never a scope: this log is
            // superadmin-only, and narrowing it would defeat the feature.
            //
            // Only `products`, `branches` and `stores` carry `store_id`
            // themselves; everything else resolves it through a branch or a
            // parent. See `LogsActivity::activityStoreId()`.
            //
            // **No foreign key, deliberately.** Two reasons, and the second is
            // the one that matters: this migration runs before `stores` is
            // created, and — more importantly — `nullOnDelete` would erase
            // which store an action happened in the moment that store is
            // deleted. An audit trail exists to survive the thing it describes,
            // so the id is kept as a historical fact rather than a live
            // reference.
            $table->unsignedBigInteger('store_id')->nullable()->index();

            $table->string('action');
            $table->nullableMorphs('auditable');
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('reason')->nullable();
            $table->string('device')->nullable();
            $table->ipAddress('ip_address')->nullable();
            $table->timestamps();

            // The listing's filters. `created_at` carries the default sort and
            // the date range, and is the one that decides whether this table
            // stays readable as it grows.
            $table->index('action');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
