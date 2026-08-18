<?php

use App\Http\Controllers\Api\V1\ActivityLogController;
use App\Http\Controllers\Api\V1\ApprovalPinController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BarangayController;
use App\Http\Controllers\Api\V1\BranchController;
use App\Http\Controllers\Api\V1\CashDrawerController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\CityController;
use App\Http\Controllers\Api\V1\CreditController;
use App\Http\Controllers\Api\V1\CustomerController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\DiscountTypeController;
use App\Http\Controllers\Api\V1\InventoryController;
use App\Http\Controllers\Api\V1\PermissionController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\ProvinceController;
use App\Http\Controllers\Api\V1\RegionController;
use App\Http\Controllers\Api\V1\ReportController;
use App\Http\Controllers\Api\V1\RoleController;
use App\Http\Controllers\Api\V1\SaleController;
use App\Http\Controllers\Api\V1\SaleReturnController;
use App\Http\Controllers\Api\V1\SaleSuspensionController;
use App\Http\Controllers\Api\V1\StockTransferController;
use App\Http\Controllers\Api\V1\StoreController;
use App\Http\Controllers\Api\V1\StoreExpenseController;
use App\Http\Controllers\Api\V1\SupplierController;
use App\Http\Controllers\Api\V1\SupportTicketController;
use App\Http\Controllers\Api\V1\UnitController;
use App\Http\Controllers\Api\V1\UserController;
use App\Http\Controllers\Api\V1\UserProfileController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('/auth/login', [AuthController::class, 'login']);
    Route::post('/auth/register', [UserController::class, 'register']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::post('/auth/change-password', [AuthController::class, 'changePassword']);
        Route::get('/auth/me', [AuthController::class, 'me']);

        // No `{store}` in the path, and there must never be one. This is the
        // acting user's own store, resolved from the token through
        // `store_users`. A bound id here would let anyone holding
        // `settings.update` edit any customer's store — `/stores` below is
        // where that is done deliberately, behind `can:stores.*`.
        Route::get('/store', [StoreController::class, 'show'])->middleware('can:settings.view');
        Route::patch('/store', [StoreController::class, 'update'])->middleware('can:settings.update');
        Route::post('/store/logo', [StoreController::class, 'updateLogo'])->middleware('can:settings.update');
        Route::delete('/store/logo', [StoreController::class, 'destroyLogo'])->middleware('can:settings.update');

        // Managing *other people's* stores — plural, and the only routes here
        // that take a store id. `Store` carries no scope, so binding one
        // resolves any customer's row and `can:stores.*` is the whole guard.
        // Only `superadmin` holds those permissions.
        Route::get('/stores', [StoreController::class, 'index'])->middleware('can:stores.view');
        Route::post('/stores', [StoreController::class, 'store'])->middleware('can:stores.create');
        Route::get('/stores/{store}', [StoreController::class, 'showStore'])->middleware('can:stores.view');
        Route::patch('/stores/{store}', [StoreController::class, 'updateStore'])->middleware('can:stores.update');
        Route::delete('/stores/{store}', [StoreController::class, 'destroy'])->middleware('can:stores.delete');
        Route::post('/stores/{store}/owner', [StoreController::class, 'addOwner'])->middleware('can:stores.update');
        Route::post('/stores/{store}/logo', [StoreController::class, 'updateStoreLogo'])->middleware('can:stores.update');
        Route::delete('/stores/{store}/logo', [StoreController::class, 'destroyStoreLogo'])->middleware('can:stores.update');

        Route::get('/users', [UserController::class, 'index'])->middleware('can:users.view');
        Route::get('/user/{user}', [UserController::class, 'show'])->middleware('can:users.view');
        Route::post('/user', [UserController::class, 'store'])->middleware('can:users.create');
        Route::patch('/user/{user}', [UserController::class, 'update'])->middleware('can:users.update');
        Route::patch('/user/{user}/password', [UserController::class, 'resetPassword'])->middleware('can:users.update');
        Route::post('/user/{user}/photo', [UserProfileController::class, 'updatePhoto'])->middleware('can:users.update');
        Route::delete('/user/{user}/photo', [UserProfileController::class, 'destroyPhoto'])->middleware('can:users.update');
        Route::patch('/user/{user}/status', [UserController::class, 'updateStatus'])->middleware('can:users.update');

        Route::get('/branches', [BranchController::class, 'index'])->middleware('can:branches.view');
        // The branch picker on the till, expenses, inventory, reports and the
        // stock dialogs. Unlike `/units/dropdown` and `/roles/dropdown`, whose
        // tables are global, branches are tenant data — so this one is safe
        // without a `can:` because the *rows* are narrowed to the caller, not
        // because the table is harmless. See BranchRepository::dropdown().
        Route::get('/branches/dropdown', [BranchController::class, 'dropdown']);
        Route::get('/branch/{branch}', [BranchController::class, 'show'])->middleware('can:branches.view');
        Route::post('/branch', [BranchController::class, 'store'])->middleware('can:branches.create');
        Route::patch('/branch/{branch}', [BranchController::class, 'update'])->middleware('can:branches.update');
        Route::post('/branch/{branch}/users', [BranchController::class, 'assignUser'])->middleware('can:branches.update');
        Route::delete('/branch/{branch}/users/{user}', [BranchController::class, 'unassignUser'])->middleware('can:branches.update');

        // Roles are fixed and global — superadmin/owner/tindera, seeded once.
        // There is no create or delete path: `roles.create` and `roles.delete`
        // are not in the catalogue at all. `update` tunes permissions only;
        // `RoleService` refuses to rename a system role, and all three are.
        Route::get('/roles', [RoleController::class, 'index'])->middleware('can:roles.view');
        // The picker the staff forms fill from — Users create/edit, and the
        // branch "create and assign a tindera" dialog. Carries no `can:` for
        // the same reason `/units/dropdown` does not: naming a role is part of
        // creating staff, not of administering roles, and an owner who may add
        // a tindera no longer holds `roles.view`. Returns id, name, slug and
        // status — no permission list and no count.
        Route::get('/roles/dropdown', [RoleController::class, 'dropdown']);
        Route::get('/role/{role}', [RoleController::class, 'show'])->middleware('can:roles.view');
        Route::patch('/role/{role}', [RoleController::class, 'update'])->middleware('can:roles.update');

        // The role editor is the only consumer of the permission catalogue.
        Route::get('/permissions', [PermissionController::class, 'index'])->middleware('can:roles.view');

        // Read-only and index-only: the log is written by the system, never by
        // a request. `activity-logs.view` is platform-level, so this is the
        // operator's view of every customer's trail — `filter[store_id]`
        // narrows it, nothing scopes it.
        Route::get('/activity-logs', [ActivityLogController::class, 'index'])->middleware('can:activity-logs.view');

        // Store-owned, and guarded twice. `can:` says whether you may do this
        // kind of thing; SupportTicketPolicy says whether you may do it to this
        // ticket — route-model binding never reaches the repository's store
        // rule. There is no delete: closing is a status, and a support trail
        // either party can erase is worth less than one they cannot.
        Route::get('/support-tickets', [SupportTicketController::class, 'index'])->middleware('can:support.view');
        Route::post('/support-ticket', [SupportTicketController::class, 'store'])->middleware('can:support.create');
        Route::get('/support-ticket/{ticket}', [SupportTicketController::class, 'show'])->middleware('can:support.view');
        Route::patch('/support-ticket/{ticket}', [SupportTicketController::class, 'update'])->middleware('can:support.update');
        Route::post('/support-ticket/{ticket}/reply', [SupportTicketController::class, 'reply'])->middleware('can:support.view');
        Route::post('/support-ticket/{ticket}/attachment', [SupportTicketController::class, 'attach'])->middleware('can:support.create');

        Route::get('/users/{user}/profile', [UserProfileController::class, 'show'])->middleware('can:users.view');
        Route::post('/users/{user}/profile', [UserProfileController::class, 'store'])->middleware('can:users.update');
        Route::match(['put', 'patch'], '/users/{user}/profile', [UserProfileController::class, 'update'])->middleware('can:users.update');

        Route::get('/shifts', [CashDrawerController::class, 'index'])->middleware('can:shifts.view');
        Route::get('/shifts/branch/{branch}/current', [CashDrawerController::class, 'current'])->middleware('can:shifts.view');
        Route::post('/shift/open', [CashDrawerController::class, 'open'])->middleware('can:shifts.open');
        Route::post('/shift/{session}/close', [CashDrawerController::class, 'close'])->middleware('can:shifts.close');
        Route::post('/shift/{session}/cash-movement', [CashDrawerController::class, 'recordMovement'])->middleware('can:shifts.open');

        // Assembled in one call: the dashboard is where load time is noticed,
        // and composing it from the per-branch report endpoints would be four
        // requests per branch.
        Route::get('/dashboard/overview', [DashboardController::class, 'overview'])->middleware('can:reports.view');

        Route::get('/reports/branch/{branch}/pos-daily', [ReportController::class, 'posDaily'])->middleware('can:reports.view');
        Route::get('/reports/branch/{branch}/daily', [ReportController::class, 'daily'])->middleware('can:reports.view');
        Route::get('/reports/branch/{branch}/daily-profit', [ReportController::class, 'profit'])->middleware('can:reports.view');
        Route::get('/reports/branch/{branch}/product-sales', [ReportController::class, 'products'])->middleware('can:reports.view');
        Route::post('/reports/branch/{branch}/z-read', [ReportController::class, 'zRead'])->middleware('can:reports.view');
        Route::get('/sales', [SaleController::class, 'index'])->middleware('can:sales.view');
        Route::get('/sale/{sale}', [SaleController::class, 'show'])->middleware('can:sales.view');
        Route::post('/sale', [SaleController::class, 'store'])->middleware('can:sales.create');
        Route::post('/sale/{sale}/void', [SaleController::class, 'void'])->middleware('can:sales.void');
        Route::get('/sale-returns', [SaleReturnController::class, 'index'])->middleware('can:sales.return');
        Route::post('/sale/{sale}/return', [SaleReturnController::class, 'store'])->middleware('can:sales.return');

        Route::get('/suspensions/branch/{branch}', [SaleSuspensionController::class, 'index'])->middleware('can:sales.suspend');
        Route::post('/suspension', [SaleSuspensionController::class, 'store'])->middleware('can:sales.suspend');
        Route::post('/suspension/{suspension}/resume', [SaleSuspensionController::class, 'resume'])->middleware('can:sales.suspend');
        Route::delete('/suspension/{suspension}', [SaleSuspensionController::class, 'destroy'])->middleware('can:sales.suspend');

        Route::get('/suppliers', [SupplierController::class, 'index'])->middleware('can:suppliers.view');
        Route::get('/supplier/{supplier}', [SupplierController::class, 'show'])->middleware('can:suppliers.view');
        Route::post('/supplier', [SupplierController::class, 'store'])->middleware('can:suppliers.create');
        Route::patch('/supplier/{supplier}', [SupplierController::class, 'update'])->middleware('can:suppliers.update');

        Route::get('/expenses', [StoreExpenseController::class, 'index'])->middleware('can:expenses.view');
        Route::get('/expense-categories', [StoreExpenseController::class, 'categories'])->middleware('can:expenses.view');
        Route::post('/expense', [StoreExpenseController::class, 'store'])->middleware('can:expenses.create');
        Route::post('/expense/{storeExpense}/approve', [StoreExpenseController::class, 'approve'])->middleware('can:expenses.approve');
        Route::get('/expenses/branch/{branch}/summary', [StoreExpenseController::class, 'summary'])->middleware('can:expenses.view');

        Route::get('/credit/transactions', [CreditController::class, 'index'])->middleware('can:credit.view');
        Route::get('/credit/customer/{customer}/statement', [CreditController::class, 'statement'])->middleware('can:credit.view');

        Route::post('/credit/customer/{customer}/collect', [CreditController::class, 'collect'])->middleware('can:credit.collect');
        Route::post('/credit/customer/{customer}/writeoff', [CreditController::class, 'writeOff'])->middleware('can:credit.writeoff');

        Route::get('/customers', [CustomerController::class, 'index'])->middleware('can:customers.view');
        Route::get('/customer/{customer}', [CustomerController::class, 'show'])->middleware('can:customers.view');
        Route::post('/customer', [CustomerController::class, 'store'])->middleware('can:customers.create');
        Route::patch('/customer/{customer}', [CustomerController::class, 'update'])->middleware('can:customers.update');

        Route::patch('/user/{user}/approval-pin', [ApprovalPinController::class, 'update'])->middleware('can:users.update');

        Route::get('/inventory/stocks', [InventoryController::class, 'stocks'])->middleware('can:inventory.view');
        Route::get('/inventory/movements', [InventoryController::class, 'movements'])->middleware('can:inventory.view');
        Route::get('/inventory/batches', [InventoryController::class, 'batches'])->middleware('can:inventory.view');
        Route::get('/inventory/branch/{branch}/low-stock', [InventoryController::class, 'lowStock'])->middleware('can:inventory.view');
        Route::get('/inventory/branch/{branch}/expiring', [InventoryController::class, 'expiring'])->middleware('can:inventory.view');
        // What to buy and how much — velocity joined to stock on hand.
        Route::get('/inventory/branch/{branch}/reorder', [InventoryController::class, 'reorder'])->middleware('can:inventory.view');
        // Setting a reorder point is adjusting inventory, so it reuses that
        // permission rather than adding one to a catalogue that has been left
        // red twice by count drift.
        Route::patch('/inventory/branch/{branch}/reorder-points', [InventoryController::class, 'updateReorderPoints'])->middleware('can:inventory.adjust');
        Route::post('/inventory/receive', [InventoryController::class, 'receive'])->middleware('can:inventory.adjust');
        Route::post('/inventory/adjust', [InventoryController::class, 'adjust'])->middleware('can:inventory.adjust');
        // Two routes, not four: creating a transfer moves the stock. There is no
        // send/receive pair to approve because there was never anyone to approve
        // it — see StockTransferService.
        Route::get('/stock-transfers', [StockTransferController::class, 'index'])->middleware('can:inventory.transfer');
        Route::post('/stock-transfer', [StockTransferController::class, 'store'])->middleware('can:inventory.transfer');

        // Barcode lookup and favorites sit before /product/{product} so the
        // literal segments are not swallowed by the model binding.
        Route::get('/products', [ProductController::class, 'index'])->middleware('can:products.view');
        Route::get('/products/favorites', [ProductController::class, 'favorites'])->middleware('can:products.view');
        Route::get('/products/barcode/{barcode}', [ProductController::class, 'findByBarcode'])->middleware('can:products.view');
        Route::get('/product/{product}', [ProductController::class, 'show'])->middleware('can:products.view');
        Route::post('/product', [ProductController::class, 'store'])->middleware('can:products.create');
        Route::patch('/product/{product}', [ProductController::class, 'update'])->middleware('can:products.update');
        Route::post('/product/{product}/image', [ProductController::class, 'updateImage'])->middleware('can:products.update');
        Route::delete('/product/{product}/image', [ProductController::class, 'destroyImage'])->middleware('can:products.update');

        // These three support the product catalogue without being it, and they
        // now hold their own permissions. They used to ride on `products.*`,
        // which a tindera must hold to work the till — so there was nothing an
        // owner could untick to keep her out of them.
        Route::get('/units', [UnitController::class, 'index'])->middleware('can:units.view');
        // The dropdown the product form and the selling-units dialog fill
        // "Base unit" from. Carries no `can:` on purpose: naming a unit is
        // part of pricing a product, not of administering the unit list, and
        // `owner` no longer holds `units.view`. It sits before `/unit/{unit}`
        // in spirit — a literal segment under the plural prefix, like
        // `/products/favorites` — and returns id, name and abbreviation only,
        // so widening the audience widens nothing else.
        Route::get('/units/dropdown', [UnitController::class, 'dropdown']);
        Route::get('/unit/{unit}', [UnitController::class, 'show'])->middleware('can:units.view');
        Route::post('/unit', [UnitController::class, 'store'])->middleware('can:units.create');
        Route::patch('/unit/{unit}', [UnitController::class, 'update'])->middleware('can:units.update');
        // Retires rather than erases: base_unit_id RESTRICTs and a product
        // sold in this unit still has to render its own history.
        Route::delete('/unit/{unit}', [UnitController::class, 'destroy'])->middleware('can:units.delete');

        Route::get('/categories', [CategoryController::class, 'index'])->middleware('can:categories.view');
        Route::get('/category/{category}', [CategoryController::class, 'show'])->middleware('can:categories.view');
        // Was `products.update`, which meant update-but-not-create could create.
        Route::post('/category', [CategoryController::class, 'store'])->middleware('can:categories.create');
        Route::patch('/category/{category}', [CategoryController::class, 'update'])->middleware('can:categories.update');
        Route::delete('/category/{category}', [CategoryController::class, 'destroy'])->middleware('can:categories.delete');

        // Discount types are configuration, not a sale action — defining which
        // discounts exist is the owner's job, applying one is the tindera's.
        // The till does not read these: `pos/discount-dialog.tsx` carries its
        // own fixed list of senior/PWD/etc., so moving the read off
        // `sales.view` costs the tindera nothing.
        Route::get('/discount-types', [DiscountTypeController::class, 'index'])->middleware('can:discount-types.view');
        Route::get('/discount-type/{discountType}', [DiscountTypeController::class, 'show'])->middleware('can:discount-types.view');
        Route::post('/discount-type', [DiscountTypeController::class, 'store'])->middleware('can:discount-types.create');
        Route::patch('/discount-type/{discountType}', [DiscountTypeController::class, 'update'])->middleware('can:discount-types.update');
        Route::delete('/discount-type/{discountType}', [DiscountTypeController::class, 'destroy'])->middleware('can:discount-types.delete');

        // PSGC reference data is left open to any authenticated user: every
        // address form in the app reads it, and it carries nothing private.
        Route::apiResource('regions', RegionController::class)->only(['index', 'show']);
        Route::apiResource('provinces', ProvinceController::class)->only(['index', 'show']);
        Route::apiResource('cities', CityController::class)->only(['index', 'show']);
        Route::apiResource('barangays', BarangayController::class)->only(['index', 'show']);
    });
});
