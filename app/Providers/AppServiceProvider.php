<?php

namespace App\Providers;

use App\Models\User;
use App\Repositories\ActivityLogRepository;
use App\Repositories\AddressRepository;
use App\Repositories\BarangayRepository;
use App\Repositories\BranchProductStockRepository;
use App\Repositories\BranchRepository;
use App\Repositories\CashDrawerSessionRepository;
use App\Repositories\CategoryRepository;
use App\Repositories\CityRepository;
use App\Repositories\Contracts\ActivityLogRepositoryInterface;
use App\Repositories\Contracts\AddressRepositoryInterface;
use App\Repositories\Contracts\BarangayRepositoryInterface;
use App\Repositories\Contracts\BranchProductStockRepositoryInterface;
use App\Repositories\Contracts\BranchRepositoryInterface;
use App\Repositories\Contracts\CashDrawerSessionRepositoryInterface;
use App\Repositories\Contracts\CategoryRepositoryInterface;
use App\Repositories\Contracts\CityRepositoryInterface;
use App\Repositories\Contracts\CreditTransactionRepositoryInterface;
use App\Repositories\Contracts\CustomerRepositoryInterface;
use App\Repositories\Contracts\DiscountTypeRepositoryInterface;
use App\Repositories\Contracts\PermissionGroupRepositoryInterface;
use App\Repositories\Contracts\ProductBatchRepositoryInterface;
use App\Repositories\Contracts\ProductRepositoryInterface;
use App\Repositories\Contracts\ProvinceRepositoryInterface;
use App\Repositories\Contracts\RegionRepositoryInterface;
use App\Repositories\Contracts\ReportRepositoryInterface;
use App\Repositories\Contracts\RoleRepositoryInterface;
use App\Repositories\Contracts\SaleRepositoryInterface;
use App\Repositories\Contracts\SaleSuspensionRepositoryInterface;
use App\Repositories\Contracts\StockAdjustmentRepositoryInterface;
use App\Repositories\Contracts\StockMovementRepositoryInterface;
use App\Repositories\Contracts\StockTransferRepositoryInterface;
use App\Repositories\Contracts\StoreExpenseRepositoryInterface;
use App\Repositories\Contracts\StoreRepositoryInterface;
use App\Repositories\Contracts\SupplierRepositoryInterface;
use App\Repositories\Contracts\SupportTicketRepositoryInterface;
use App\Repositories\Contracts\UnitRepositoryInterface;
use App\Repositories\Contracts\UserProfileRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Repositories\CreditTransactionRepository;
use App\Repositories\CustomerRepository;
use App\Repositories\DiscountTypeRepository;
use App\Repositories\PermissionGroupRepository;
use App\Repositories\ProductBatchRepository;
use App\Repositories\ProductRepository;
use App\Repositories\ProvinceRepository;
use App\Repositories\RegionRepository;
use App\Repositories\ReportRepository;
use App\Repositories\RoleRepository;
use App\Repositories\SaleRepository;
use App\Repositories\SaleSuspensionRepository;
use App\Repositories\StockAdjustmentRepository;
use App\Repositories\StockMovementRepository;
use App\Repositories\StockTransferRepository;
use App\Repositories\StoreExpenseRepository;
use App\Repositories\StoreRepository;
use App\Repositories\SupplierRepository;
use App\Repositories\SupportTicketRepository;
use App\Repositories\UnitRepository;
use App\Repositories\UserProfileRepository;
use App\Repositories\UserRepository;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public array $bindings = [
        UserRepositoryInterface::class => UserRepository::class,
        UserProfileRepositoryInterface::class => UserProfileRepository::class,
        RegionRepositoryInterface::class => RegionRepository::class,
        ProvinceRepositoryInterface::class => ProvinceRepository::class,
        CityRepositoryInterface::class => CityRepository::class,
        BarangayRepositoryInterface::class => BarangayRepository::class,
        RoleRepositoryInterface::class => RoleRepository::class,
        PermissionGroupRepositoryInterface::class => PermissionGroupRepository::class,
        AddressRepositoryInterface::class => AddressRepository::class,
        BranchRepositoryInterface::class => BranchRepository::class,
        UnitRepositoryInterface::class => UnitRepository::class,
        CategoryRepositoryInterface::class => CategoryRepository::class,
        ProductRepositoryInterface::class => ProductRepository::class,
        StockMovementRepositoryInterface::class => StockMovementRepository::class,
        StockTransferRepositoryInterface::class => StockTransferRepository::class,
        ActivityLogRepositoryInterface::class => ActivityLogRepository::class,
        SupportTicketRepositoryInterface::class => SupportTicketRepository::class,
        BranchProductStockRepositoryInterface::class => BranchProductStockRepository::class,
        ProductBatchRepositoryInterface::class => ProductBatchRepository::class,
        StockAdjustmentRepositoryInterface::class => StockAdjustmentRepository::class,
        CashDrawerSessionRepositoryInterface::class => CashDrawerSessionRepository::class,
        CustomerRepositoryInterface::class => CustomerRepository::class,
        DiscountTypeRepositoryInterface::class => DiscountTypeRepository::class,
        SaleRepositoryInterface::class => SaleRepository::class,
        SaleSuspensionRepositoryInterface::class => SaleSuspensionRepository::class,
        CreditTransactionRepositoryInterface::class => CreditTransactionRepository::class,
        SupplierRepositoryInterface::class => SupplierRepository::class,
        StoreExpenseRepositoryInterface::class => StoreExpenseRepository::class,
        StoreRepositoryInterface::class => StoreRepository::class,
        ReportRepositoryInterface::class => ReportRepository::class,
    ];

    /**
     * `ActivityLogger` must be a **singleton**, and that is load-bearing.
     *
     * `LogsActivity` resolves it from the container inside a model event, while
     * a service calling `withoutModelLogging()` holds its own injected copy.
     * Two instances mean two suppression flags, the trait never sees the one
     * that was set, and every explicitly-logged action is recorded twice — once
     * with its reason and once without.
     */
    public array $singletons = [
        ActivityLogger::class => ActivityLogger::class,
    ];

    public function register(): void {}

    public function boot(): void
    {
        // Every permission in the catalogue is an ability, so routes can use
        // Laravel's own `can:` middleware and controllers `$this->authorize()`
        // without a permission-specific middleware.
        //
        // Returning `false` here would deny every ability outright and
        // short-circuit any Policy registered later — only `true` (granted)
        // and `null` (undecided, fall through) are safe. A user whose
        // `role_id` is null resolves to no permissions and is denied
        // everything, which is the intended default.
        Gate::before(function (User $user, string $ability) {
            return $user->hasPermissionTo($ability) ? true : null;
        });
    }
}
