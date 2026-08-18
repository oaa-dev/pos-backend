<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The three seeded system roles map one-to-one onto the POS ones, so they
     * are renamed in place rather than pruned and reseeded.
     *
     * `users.role_id` is `nullOnDelete()`, so deleting a stale role would
     * silently unassign every user holding it and raise no error. Renaming
     * preserves the row id, and with it every `role_permissions` entry and
     * every user assignment.
     *
     * @var list<array{string, string, string, string}> [old slug, old name, new slug, new name]
     */
    private const RENAMES = [
        ['admin', 'Admin', 'owner', 'Owner'],
        ['agent', 'Agent', 'tindera', 'Tindera'],
    ];

    public function up(): void
    {
        foreach (self::RENAMES as [$oldSlug, $oldName, $newSlug, $newName]) {
            $this->rename($oldSlug, $newSlug, $newName);
        }
    }

    public function down(): void
    {
        foreach (self::RENAMES as [$oldSlug, $oldName, $newSlug, $newName]) {
            $this->rename($newSlug, $oldSlug, $oldName);
        }
    }

    /**
     * Guarded on both sides: the source row is absent on a fresh database
     * (nothing seeded yet) and the target may already exist if the seeder ran
     * first. `roles.slug` is globally unique, so writing into an occupied slug
     * would fail the migration.
     */
    private function rename(string $from, string $to, string $name): void
    {
        $exists = DB::table('roles')->where('slug', $from)->exists();
        $taken = DB::table('roles')->where('slug', $to)->exists();

        if (! $exists || $taken) {
            return;
        }

        DB::table('roles')
            ->where('slug', $from)
            ->update(['slug' => $to, 'name' => $name, 'updated_at' => now()]);
    }
};
