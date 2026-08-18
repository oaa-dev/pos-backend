<?php

namespace App\Services;

use App\Data\StoreData;
use App\Data\StoreLogoData;
use App\Data\StoreOwnerData;
use App\Data\UserData;
use App\Enums\RoleEnum;
use App\Enums\StatusEnum;
use App\Models\Role;
use App\Models\Store;
use App\Models\User;
use App\Repositories\Contracts\StoreRepositoryInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use RuntimeException;
use Spatie\LaravelData\Optional;
use Throwable;

/**
 * Stores — both the acting user's own and, for the operator, everyone else's.
 *
 * These used to be two classes. `StoreManagementService` existed because
 * `Store` carried no tenant scope while everything around it did, so a method
 * taking a store id was a different *kind* of thing from one deriving the
 * store from the actor. That distinction is gone: nothing carries a scope now,
 * `store_id` arrives in the request everywhere, and the guard is `can:` on the
 * route in both cases.
 *
 * What remains is a plain split by argument. `currentFor()` and the methods
 * built on it resolve the store from the authenticated user and are reached
 * through `/store` behind `can:settings.*`. The rest take a `Store` bound from
 * the URL and are reached through `/stores` behind `can:stores.*`, which only
 * `superadmin` holds.
 */
class StoreService extends BaseService
{
    public function __construct(
        protected readonly StoreRepositoryInterface $stores,
        protected readonly StoreProvisioningService $provisioning,
        protected readonly UserService $userService,
    ) {
        parent::__construct($stores);
    }

    // ---------------------------------------------------------------- own

    /**
     * The store the given user belongs to.
     *
     * Resolved through `store_users`; `users` carries no `store_id`. An
     * account attached to no store — the platform operator, or a user whose
     * membership row was never written — has none, and a 404 is the honest
     * answer rather than falling back to some other customer's row.
     */
    public function currentFor(User $user): Store
    {
        $store = $user->store;

        if ($store === null) {
            throw new ModelNotFoundException('No store is associated with this account.');
        }

        return $store;
    }

    /**
     * Update the acting user's own store.
     *
     * Narrower than `updateStore()` on purpose: `status` is absent, so a store
     * cannot un-suspend itself, and `slug` is absent because it is the one
     * globally unique handle.
     */
    public function updateCurrent(User $user, StoreData $data): Store
    {
        $store = $this->currentFor($user);

        $values = collect([
            'name' => $data->name,
            'description' => $data->description,
            'owner_name' => $data->owner_name,
            'phone' => $data->phone,
        ])->reject(fn ($value) => $value instanceof Optional)->all();

        if ($values === []) {
            return $store;
        }

        return $this->stores->update($store, $values);
    }

    /** The acting user's own store. Delegates to `updateLogoFor()`. */
    public function updateLogo(User $user, StoreLogoData $data): Store
    {
        return $this->updateLogoFor($this->currentFor($user), $data);
    }

    /** The acting user's own store. Delegates to `deleteLogoFor()`. */
    public function deleteLogo(User $user): Store
    {
        return $this->deleteLogoFor($this->currentFor($user));
    }

    // ----------------------------------------------------------- operator

    /**
     * Replace a store's logo.
     *
     * Written once and shared by both paths — `/store/logo` resolves the store
     * from the actor and `/stores/{store}/logo` binds it — because the ordering
     * here is what makes a failure safe: the new file is stored first, the row
     * is updated second, and the previous file is deleted only once the new
     * path is durable. A failed update deletes the file it just wrote rather
     * than leaving an orphan.
     */
    public function updateLogoFor(Store $store, StoreLogoData $data): Store
    {
        $path = $data->logo->store("store-logos/{$store->id}", 'public');

        if ($path === false) {
            throw new RuntimeException('Could not store the store logo.');
        }

        $previous = $store->logo_path;

        try {
            $this->stores->update($store, ['logo_path' => $path]);
        } catch (Throwable $exception) {
            Storage::disk('public')->delete($path);

            throw $exception;
        }

        if ($previous !== null && $previous !== $path) {
            Storage::disk('public')->delete($previous);
        }

        return $store->refresh();
    }

    public function deleteLogoFor(Store $store): Store
    {
        $previous = $store->logo_path;

        $this->stores->update($store, ['logo_path' => null]);

        if ($previous !== null) {
            Storage::disk('public')->delete($previous);
        }

        return $store->refresh();
    }

    /** Every customer's store. Only legitimate behind `can:stores.view`. */
    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return $this->stores->paginate($perPage);
    }

    /**
     * Open a store and, when supplied, create the account that will run it.
     *
     * One transaction, so a duplicate email cannot leave a half-created store
     * behind. Without an account the store opens **inactive** and is completed
     * later through `addOwner()`.
     */
    public function open(StoreData $data, ?StoreOwnerData $account = null): Store
    {
        return DB::transaction(function () use ($data, $account) {
            $name = $data->name instanceof Optional ? '' : $data->name;

            $store = Store::create([
                'name' => $name,
                'slug' => $this->provisioning->uniqueSlug($name),
                'description' => $data->description instanceof Optional ? null : $data->description,
                'status' => $data->status instanceof Optional
                    ? ($account === null ? StatusEnum::INACTIVE->value : StatusEnum::ACTIVE->value)
                    : $data->status,
            ]);

            if ($account !== null) {
                $this->addOwner($store, $account);
            }

            return $store;
        });
    }

    /**
     * Create the account that will run an existing store.
     *
     * A store has exactly one owner. MySQL has no partial unique index, so
     * `unique(store_id) where is_owner` is not available and this check is the
     * only thing enforcing it.
     */
    public function addOwner(Store $store, StoreOwnerData $data): User
    {
        return DB::transaction(function () use ($store, $data) {
            if ($store->members()->wherePivot('is_owner', true)->exists()) {
                throw new InvalidArgumentException('This store already has an owner.');
            }

            $roleId = Role::where('slug', RoleEnum::OWNER->value)->value('id');

            if ($roleId === null) {
                throw new RuntimeException(
                    'The global `owner` role is missing — run SystemRoleSeeder before adding a store owner.'
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

            $store->members()->syncWithoutDetaching([$owner->id => ['is_owner' => true]]);

            // Keep the store's own contact details consistent with the login
            // account, matching what registration does.
            $this->stores->update($store, [
                'owner_name' => trim($data->lastname.' '.$data->firstname),
                'phone' => $data->phone_number,
            ]);

            return $owner->load(['profile', 'role.permissions']);
        });
    }

    /** Update any store, including its `status`. Operator only. */
    public function updateStore(Store $store, StoreData $data): Store
    {
        $values = collect([
            'name' => $data->name,
            'description' => $data->description,
            'owner_name' => $data->owner_name,
            'phone' => $data->phone,
            'status' => $data->status,
        ])->reject(fn ($value) => $value instanceof Optional)->all();

        if ($values === []) {
            return $store;
        }

        return $this->stores->update($store, $values);
    }

    /**
     * Close a store. Soft delete, so nothing the customer entered is lost.
     *
     * Note what this does **not** do: a closed store's staff can still log in
     * and still sell. Making "closed" mean something at the auth layer is
     * deliberately out of scope.
     */
    public function close(Store $store): void
    {
        $this->stores->delete($store);
    }
}
