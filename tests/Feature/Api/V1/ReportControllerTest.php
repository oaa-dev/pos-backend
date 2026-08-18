<?php

use App\Models\Branch;
use App\Models\CashDrawerSession;
use App\Models\Customer;
use App\Models\ExpenseCategory;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\Sale;
use App\Models\SaleReturn;
use App\Models\StoreExpense;
use Illuminate\Support\Str;

function reportFixture(): array
{
    $user = actingAsUserWith(['reports.view']);
    $branch = Branch::factory()->create();
    $branch->users()->attach($user->id, ['is_primary' => true]);
    $session = CashDrawerSession::factory()->create([
        'branch_id' => $branch->id, 'user_id' => $user->id,
        'opened_at' => '2026-08-05 08:00:00', 'opening_float' => 1000,
        'closed_at' => '2026-08-05 18:00:00', 'expected_cash' => 1100,
        'closing_counted' => 1080, 'variance' => -20, 'status' => 'closed',
    ]);
    $product = Product::factory()->create();
    $customer = Customer::factory()->create(['branch_id' => $branch->id]);
    $unit = ProductUnit::factory()->create(['product_id' => $product->id, 'conversion_factor' => 1]);
    $sale = Sale::create([
        'uuid' => (string) Str::uuid(), 'sale_number' => 'REPORT-000001', 'branch_id' => $branch->id,
        'cash_drawer_session_id' => $session->id, 'user_id' => $user->id, 'customer_id' => $customer->id, 'status' => 'completed',
        'subtotal' => 120, 'discount_total' => 20, 'total' => 100, 'amount_tendered' => 100,
        'change_due' => 0, 'credit_amount' => 0, 'sold_at' => '2026-08-05 10:00:00',
    ]);
    $item = $sale->items()->create([
        'product_id' => $product->id, 'product_unit_id' => $unit->id, 'quantity' => 2,
        'quantity_base' => 2, 'unit_price' => 60, 'unit_cost' => 30,
        'product_name_snapshot' => $product->name, 'unit_name_snapshot' => 'Piece',
        'line_discount' => 20, 'line_total' => 100,
    ]);
    $sale->payments()->create(['method' => 'cash', 'amount' => 100]);
    $return = SaleReturn::create([
        'sale_id' => $sale->id, 'branch_id' => $branch->id, 'user_id' => $user->id,
        'return_number' => 'RET-000001', 'reason' => 'Wrong item', 'refund_method' => 'cash',
        'refund_total' => 20, 'returned_at' => '2026-08-05 11:00:00',
    ]);
    $return->items()->create(['sale_item_id' => $item->id, 'quantity' => 1, 'refund_amount' => 20, 'restock' => true]);
    StoreExpense::create([
        'branch_id' => $branch->id, 'expense_category_id' => ExpenseCategory::factory()->create()->id,
        'amount' => 10, 'description' => 'Ice', 'paid_from' => 'drawer',
        'incurred_at' => '2026-08-05', 'recorded_by' => $user->id,
    ]);

    return [$branch, $product, $customer, $user];
}

it('reports a complete POS daily summary for one branch and business date', function () {
    [$branch, , , $user] = reportFixture();

    $this->getJson("/api/v1/reports/branch/{$branch->id}/pos-daily?date=2026-08-05")
        ->assertOk()
        ->assertJsonPath('data.date', '2026-08-05')
        ->assertJsonPath('data.summary.sale_count', 1)
        ->assertJsonPath('data.summary.items_sold', '2.000')
        ->assertJsonPath('data.summary.return_count', 1)
        ->assertJsonPath('data.summary.net_sales', '80.00')
        ->assertJsonPath('data.payments.0.method', 'cash')
        ->assertJsonPath('data.payments.0.amount', '100.00')
        ->assertJsonPath('data.payments.0.share_percent', '100.00')
        ->assertJsonPath('data.cash_reconciliation.initial_cash', '1000.00')
        ->assertJsonPath('data.cash_reconciliation.expected_cash', '1100.00')
        ->assertJsonPath('data.cash_reconciliation.counted_cash', '1080.00')
        ->assertJsonPath('data.cash_reconciliation.variance', '-20.00')
        ->assertJsonPath('data.cash_reconciliation.uncounted_session_count', 0)
        ->assertJsonPath('data.drawer_sessions.0.cashier_name', $user->name)
        ->assertJsonPath('data.drawer_sessions.0.initial_cash', '1000.00')
        ->assertJsonPath('data.drawer_sessions.0.counted_cash', '1080.00')
        ->assertJsonPath('data.cashiers.0.sale_count', 1)
        ->assertJsonPath('data.transactions.0.sale_number', 'REPORT-000001');
});

it('reports daily sales and profit for one branch and date', function () {
    [$branch] = reportFixture();

    $this->getJson("/api/v1/reports/branch/{$branch->id}/daily?from=2026-08-05&to=2026-08-05")
        ->assertOk()->assertJsonPath('data.gross_sales', '120.00')
        ->assertJsonPath('data.discount_total', '20.00')->assertJsonPath('data.net_sales', '80.00')
        ->assertJsonPath('data.payments.cash', '100.00')
        ->assertJsonPath('data.transactions.0.sale_number', 'REPORT-000001')
        ->assertJsonPath('data.transactions.0.returns', '20.00');

    $this->getJson("/api/v1/reports/branch/{$branch->id}/daily-profit?from=2026-08-05&to=2026-08-05")
        ->assertOk()->assertJsonPath('data.cost_of_goods_sold', '30.00')
        ->assertJsonPath('data.gross_profit', '50.00')->assertJsonPath('data.operating_expenses', '10.00')
        ->assertJsonPath('data.net_profit', '40.00')
        ->assertJsonPath('data.daily.0.business_date', '2026-08-05')
        ->assertJsonPath('data.daily.0.net_profit', '40.00');
});

it('reports product sales for one branch and date', function () {
    [$branch, $product] = reportFixture();

    $this->getJson("/api/v1/reports/branch/{$branch->id}/product-sales?from=2026-08-05&to=2026-08-05")
        ->assertOk()->assertJsonPath('data.products.0.product_id', $product->id)
        ->assertJsonPath('data.products.0.sales', '100.00')
        ->assertJsonPath('data.products.0.returns', '20.00')
        ->assertJsonPath('data.products.0.net_sales', '80.00')
        ->assertJsonPath('data.products.0.gross_profit', '50.00')
        ->assertJsonPath('data.products.0.margin_percent', '62.50');
});

it('filters sales by product and customer and returns one row per day', function () {
    [$branch, $product, $customer] = reportFixture();

    $query = "from=2026-08-05&to=2026-08-06&product_id={$product->id}&customer_id={$customer->id}";

    $this->getJson("/api/v1/reports/branch/{$branch->id}/daily?{$query}")
        ->assertOk()->assertJsonPath('data.sale_count', 1);

    $this->getJson("/api/v1/reports/branch/{$branch->id}/product-sales?{$query}")
        ->assertOk()->assertJsonCount(1, 'data.products');

    $this->getJson("/api/v1/reports/branch/{$branch->id}/daily-profit?from=2026-08-05&to=2026-08-06")
        ->assertOk()->assertJsonCount(2, 'data.daily')
        ->assertJsonPath('data.daily.1.business_date', '2026-08-06')
        ->assertJsonPath('data.daily.1.net_profit', '0.00');
});
it('does not leak another branch report', function () {
    reportFixture();
    $other = Branch::factory()->create();

    $this->getJson("/api/v1/reports/branch/{$other->id}/daily?from=2026-08-05&to=2026-08-05")->assertForbidden();
});
