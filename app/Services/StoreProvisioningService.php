<?php

namespace App\Services;

use App\Data\StoreRegistrationData;
use App\Data\UserData;
use App\Enums\RoleEnum;
use App\Enums\StatusEnum;
use App\Models\Role;
use App\Models\Store;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Stands up a new store and its owner.
 *
 * This is the first five minutes of every future customer's experience, and a
 * half-provisioned store is worse than none: an owner with no `owner` role is
 * locked out of an application that is default-deny, and an owner with no
 * `store_users` row belongs to no store. So it is one transaction.
 *
 * **Nothing is seeded per store.** Roles, units, categories, discount types
 * and expense categories are all global and seeded once by `DatabaseSeeder`;
 * a new store simply references them.
 */
class StoreProvisioningService
{
    public function __construct(
        private readonly UserService $userService,
    ) {}

    /**
     * Create a store and its owner — all or nothing.
     */
    public function provision(StoreRegistrationData $data): Store
    {
        return DB::transaction(function () use ($data) {
            $name = $this->resolveName($data);

            $store = Store::create([
                'name' => $name,
                'slug' => $this->uniqueSlug($name),
                'owner_name' => $this->fullName($data),
                'phone' => $data->phone_number,
                'status' => StatusEnum::ACTIVE->value,
            ]);

            // Global now, not seeded per store. Resolved up front and asserted
            // rather than passed through: a null `role_id` is accepted
            // silently by a nullable column, and the registrant would arrive
            // holding no permissions in a default-deny API — a broken signup
            // with no error anywhere near the cause.
            $roleId = Role::where('slug', RoleEnum::OWNER->value)->value('id');

            if ($roleId === null) {
                throw new RuntimeException(
                    'The global `owner` role is missing — run SystemRoleSeeder before provisioning a store.'
                );
            }

            $owner = $this->userService->store(UserData::from([
                'firstname' => $data->firstname,
                'lastname' => $data->lastname,
                'email' => $data->email,
                'password' => $data->password,
                'phone_number' => $data->phone_number,
                'role_id' => $roleId,
            ]));

            // The only record that this account belongs to this store —
            // `users` carries no `store_id`. Forgetting it leaves the owner
            // attached to nothing and `$store->owner` null, with no error.
            $store->members()->syncWithoutDetaching([$owner->id => ['is_owner' => true]]);

            // Loaded *after* the pivot is written and *before* returning.
            // `role.permissions` and `store` both resolve through queries of
            // their own; handing back an unloaded model and letting the caller
            // read them is how a brand-new owner arrives with `role: null` in
            // a default-deny API.
            return $store->setRelation(
                'owner',
                $owner->load(['profile', 'profile.address', 'role.permissions', 'store'])
            );
        });
    }

    private function resolveName(StoreRegistrationData $data): string
    {
        $name = trim((string) $data->store_name);

        // Registration does not require a store name yet — signup proper is a
        // later phase. Until then the shop is named after the person who
        // opened it, which is what a sari-sari is called anyway.
        return $name !== ''
            ? $name
            : trim($data->lastname).' Sari-Sari Store';
    }

    private function fullName(StoreRegistrationData $data): string
    {
        return trim($data->lastname.' '.$data->firstname);
    }

    /**
     * `stores.slug` is one of the few globally unique columns left — it is the
     * identifier that has to resolve without already knowing a store — so two
     * shops with the same name need distinguishing.
     */
    public function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'store';
        $slug = $base;
        $suffix = 2;

        // withTrashed: the unique index counts soft-deleted rows, so a closed
        // store still holds its slug.
        while (Store::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }
}
