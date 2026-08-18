<?php

namespace App\Http\Controllers\Api\V1;

use App\Data\StoreData;
use App\Data\StoreLogoData;
use App\Data\StoreOwnerData;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreManagedStoreRequest;
use App\Http\Requests\StoreOwnerRequest;
use App\Http\Requests\UpdateManagedStoreRequest;
use App\Http\Requests\UpdateStoreLogoRequest;
use App\Http\Requests\UpdateStoreRequest;
use App\Http\Resources\StoreResource;
use App\Http\Resources\UserResource;
use App\Models\Store;
use App\Services\StoreService;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;

/**
 * Stores, own and managed.
 *
 * Two route groups land here and they are not interchangeable:
 *
 * - **`/store`** (singular, no id) — the acting user's own, behind
 *   `can:settings.*`. The store comes from the token; there is nothing to
 *   choose and nothing to authorise beyond the permission.
 * - **`/stores`** (plural, takes an id) — every customer's, behind
 *   `can:stores.*`, which only `superadmin` holds. `Store` carries no scope,
 *   so route-model binding resolves any row and that middleware is the whole
 *   guard.
 *
 * These used to be two controllers, on the argument that a method taking a
 * store id was categorically different from one deriving it from the actor.
 * Nothing carries a tenant scope any more, so the distinction reduced to which
 * permission guards the route — which is visible in `routes/api.php` and does
 * not need a second class to express.
 */
class StoreController extends Controller
{
    use ApiResponse;

    public function __construct(protected StoreService $storeService) {}

    // ---------------------------------------------------------------- own

    public function show(Request $request)
    {
        return $this->successResponse(
            new StoreResource($this->storeService->currentFor($request->user())),
        );
    }

    public function update(UpdateStoreRequest $request)
    {
        $store = $this->storeService->updateCurrent(
            $request->user(),
            StoreData::from($request->validated()),
        );

        return $this->successResponse(new StoreResource($store), 'Store updated successfully');
    }

    public function updateLogo(UpdateStoreLogoRequest $request)
    {
        $store = $this->storeService->updateLogo(
            $request->user(),
            StoreLogoData::from(['logo' => $request->file('logo')]),
        );

        return $this->successResponse(new StoreResource($store), 'Store logo updated successfully');
    }

    public function destroyLogo(Request $request)
    {
        return $this->successResponse(
            new StoreResource($this->storeService->deleteLogo($request->user())),
            'Store logo removed successfully',
        );
    }

    // ----------------------------------------------------------- operator

    public function index(Request $request)
    {
        return $this->paginatedResponse(
            StoreResource::collection($this->storeService->paginate($request->input('per_page', 15))),
        );
    }

    public function store(StoreManagedStoreRequest $request)
    {
        $validated = $request->validated();

        $account = isset($validated['account'])
            ? StoreOwnerData::from($validated['account'])
            : null;

        unset($validated['account']);

        $store = $this->storeService->open(StoreData::from($validated), $account);

        return $this->successResponse(
            new StoreResource($this->withOwnerProfile($store->fresh())),
            'Store opened successfully',
            201,
        );
    }

    public function showStore(Store $store)
    {
        return $this->successResponse(new StoreResource($this->withOwnerProfile($store)));
    }

    public function updateStore(UpdateManagedStoreRequest $request, Store $store)
    {
        $store = $this->storeService->updateStore($store, StoreData::from($request->validated()));

        return $this->successResponse(
            new StoreResource($this->withOwnerProfile($store)),
            'Store updated successfully',
        );
    }

    public function destroy(Store $store)
    {
        $this->storeService->close($store);

        return $this->successResponse(null, 'Store closed successfully');
    }

    /**
     * Attach the account that will run this store.
     *
     * The owner comes back alongside the store so the dialog can close straight
     * into a refreshed row.
     */
    public function addOwner(StoreOwnerRequest $request, Store $store)
    {
        $owner = $this->storeService->addOwner($store, StoreOwnerData::from($request->validated()));

        return $this->successResponse(
            [
                'store' => new StoreResource($this->withOwnerProfile($store->fresh())),
                'owner' => new UserResource($owner),
            ],
            'Store owner added successfully',
            201,
        );
    }

    public function updateStoreLogo(UpdateStoreLogoRequest $request, Store $store)
    {
        $store = $this->storeService->updateLogoFor(
            $store,
            StoreLogoData::from(['logo' => $request->file('logo')]),
        );

        return $this->successResponse(
            new StoreResource($this->withOwnerProfile($store)),
            'Store logo updated successfully',
        );
    }

    public function destroyStoreLogo(Store $store)
    {
        return $this->successResponse(
            new StoreResource($this->withOwnerProfile($this->storeService->deleteLogoFor($store))),
            'Store logo removed successfully',
        );
    }

    /** Load the owner and their profile before the resource serialises it. */
    private function withOwnerProfile(Store $store): Store
    {
        return $store->load('owner.profile');
    }
}
