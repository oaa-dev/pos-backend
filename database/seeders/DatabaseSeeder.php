<?php

namespace Database\Seeders;

use App\Enums\RoleEnum;
use App\Models\Role;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Everything here is global. Nothing is seeded per store.
     *
     * Order matters in one place: `SystemRoleSeeder` reads the catalogue, so
     * running it before `PermissionSeeder` syncs every role to zero
     * permissions and raises no error.
     */
    public function run(): void
    {
        $this->call([
            PsgcSeeder::class,
            PermissionSeeder::class,
            UnitSeeder::class,
            ExpenseCategorySeeder::class,
            DiscountTypeSeeder::class,
            SystemRoleSeeder::class,
        ]);

        $this->createSuperadmin();
    }

    /**
     * The platform operator account.
     *
     * Belongs to no store — it holds `stores.*` and works across all of them.
     * The role is looked up by slug rather than by `RoleEnum->value` as an id:
     * roles are upserted on slug, so their ids are not stable across reseeds.
     */
    private function createSuperadmin(): void
    {
        $user = User::updateOrCreate(
            ['email' => 'test@example.com'],
            [
                'name' => 'Super Admin',
                'password' => bcrypt('password'),
                'role_id' => Role::where('slug', RoleEnum::SUPERADMIN->value)->value('id'),
            ],
        );

        UserProfile::updateOrCreate(
            ['user_id' => $user->id],
            ['firstname' => 'Super', 'lastname' => 'Admin'],
        );
    }
}
