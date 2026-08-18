<?php

namespace App\Repositories;

use App\Data\ReportFilterData;
use App\Repositories\Contracts\ReportRepositoryInterface;
use Carbon\CarbonPeriod;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class ReportRepository implements ReportRepositoryInterface
{
    public function posDaily(int $branchId, string $date): array
    {
        $filter = new ReportFilterData($date, $date);
        $salesSummary = $this->dailySales($branchId, $filter);
        $sales = $this->salesQuery($branchId, $filter);
        $saleIds = (clone $sales)->select('sales.id');

        $itemsSold = DB::table('sale_items')->whereIn('sale_id', (clone $saleIds))->sum('quantity');
        $returnCount = $this->returnsQuery($branchId, $filter)->count('sale_returns.id');
        $voided = DB::table('sales')->where('branch_id', $branchId)->where('status', 'voided')
            ->whereBetween('voided_at', $this->timestamps($filter));
        $voidedCount = (clone $voided)->count();
        $voidedTotal = $this->money((clone $voided)->sum('total'));
        $firstSaleAt = (clone $sales)->min('sold_at');
        $lastSaleAt = (clone $sales)->max('sold_at');

        $cashiers = (clone $sales)->join('users', 'users.id', '=', 'sales.user_id')
            ->groupBy('sales.user_id', 'users.name')
            ->selectRaw('sales.user_id, users.name as cashier_name, COUNT(*) as sale_count,
                SUM(sales.subtotal) as gross_sales, SUM(sales.discount_total) as discount_total,
                SUM(sales.total) as completed_sales')
            ->orderByDesc('completed_sales')->get()->map(fn ($row) => [
                'user_id' => $row->user_id,
                'cashier_name' => $row->cashier_name,
                'sale_count' => (int) $row->sale_count,
                'gross_sales' => $this->money($row->gross_sales),
                'discount_total' => $this->money($row->discount_total),
                'completed_sales' => $this->money($row->completed_sales),
            ])->all();

        $drawerSessions = DB::table('cash_drawer_sessions')->join('users', 'users.id', '=', 'cash_drawer_sessions.user_id')
            ->where('cash_drawer_sessions.branch_id', $branchId)
            ->where(function ($query) use ($filter, $sales) {
                $query->whereBetween('cash_drawer_sessions.opened_at', $this->timestamps($filter))
                    ->orWhereIn('cash_drawer_sessions.id', (clone $sales)->select('sales.cash_drawer_session_id'));
            })
            ->orderBy('cash_drawer_sessions.opened_at')
            ->select([
                'cash_drawer_sessions.id', 'cash_drawer_sessions.opened_at', 'cash_drawer_sessions.closed_at',
                'cash_drawer_sessions.opening_float', 'cash_drawer_sessions.expected_cash',
                'cash_drawer_sessions.closing_counted', 'cash_drawer_sessions.variance',
                'cash_drawer_sessions.status', 'users.name as cashier_name',
            ])->get()->map(fn ($session) => [
                'id' => $session->id,
                'cashier_name' => $session->cashier_name,
                'opened_at' => $session->opened_at,
                'closed_at' => $session->closed_at,
                'status' => $session->status,
                'initial_cash' => $this->money($session->opening_float),
                'expected_cash' => $session->expected_cash === null ? null : $this->money($session->expected_cash),
                'counted_cash' => $session->closing_counted === null ? null : $this->money($session->closing_counted),
                'variance' => $session->variance === null ? null : $this->money($session->variance),
            ]);

        $closedDrawers = $drawerSessions->where('status', 'closed');
        $cashReconciliation = [
            'session_count' => $drawerSessions->count(),
            'closed_session_count' => $closedDrawers->count(),
            'uncounted_session_count' => $drawerSessions->whereNull('counted_cash')->count(),
            'initial_cash' => $this->sumMoney($drawerSessions, 'initial_cash'),
            'expected_cash' => $closedDrawers->isEmpty() ? null : $this->sumMoney($closedDrawers, 'expected_cash'),
            'counted_cash' => $closedDrawers->isEmpty() ? null : $this->sumMoney($closedDrawers, 'counted_cash'),
            'variance' => $closedDrawers->isEmpty() ? null : $this->sumMoney($closedDrawers, 'variance'),
        ];

        $paymentTotal = collect($salesSummary['payments'])
            ->reduce(fn (string $total, string $amount) => bcadd($total, $amount, 2), '0.00');
        $payments = collect($salesSummary['payments'])->map(fn (string $amount, string $method) => [
            'method' => $method,
            'amount' => $amount,
            'share_percent' => bccomp($paymentTotal, '0', 2) > 0
                ? bcmul(bcdiv($amount, $paymentTotal, 4), '100', 2)
                : '0.00',
        ])->values()->all();

        return [
            'date' => $date,
            'first_sale_at' => $firstSaleAt,
            'last_sale_at' => $lastSaleAt,
            'summary' => [
                'sale_count' => $salesSummary['sale_count'],
                'items_sold' => number_format((float) $itemsSold, 3, '.', ''),
                'return_count' => $returnCount,
                'voided_count' => $voidedCount,
                'gross_sales' => $salesSummary['gross_sales'],
                'discount_total' => $salesSummary['discount_total'],
                'completed_sales' => $salesSummary['completed_sales'],
                'returns_total' => $salesSummary['returns_total'],
                'voided_total' => $voidedTotal,
                'net_sales' => $salesSummary['net_sales'],
                'average_sale' => $salesSummary['average_sale'],
            ],
            'payments' => $payments,
            'cash_reconciliation' => $cashReconciliation,
            'drawer_sessions' => $drawerSessions->values()->all(),
            'cashiers' => $cashiers,
            'transactions' => $salesSummary['transactions'],
        ];
    }

    public function dailySales(int $branchId, ReportFilterData $filter): array
    {
        $sales = $this->salesQuery($branchId, $filter);
        $saleCount = (clone $sales)->count();
        $gross = $this->money((clone $sales)->sum('subtotal'));
        $discounts = $this->money((clone $sales)->sum('discount_total'));
        $completed = $this->money((clone $sales)->sum('total'));
        $returnsQuery = $this->returnsQuery($branchId, $filter);
        $returns = $this->money((clone $returnsQuery)->sum('sale_returns.refund_total'));

        $paymentRows = DB::table('sale_payments')->whereIn('sale_id', (clone $sales)->select('sales.id'))
            ->selectRaw('sale_id, method, SUM(amount) as total')->groupBy('sale_id', 'method')->get();
        $payments = $paymentRows->groupBy('method')->map(fn ($rows) => $this->money($rows->sum('total')))->all();
        $paymentsBySale = $paymentRows->groupBy('sale_id')->map(fn ($rows) => $rows->mapWithKeys(fn ($row) => [$row->method => $this->money($row->total)])->all());
        $returnsBySale = (clone $returnsQuery)->selectRaw('sale_returns.sale_id, SUM(sale_returns.refund_total) as total')
            ->groupBy('sale_returns.sale_id')->pluck('total', 'sale_returns.sale_id');

        $transactions = (clone $sales)->leftJoin('users', 'users.id', '=', 'sales.user_id')
            ->leftJoin('customers', 'customers.id', '=', 'sales.customer_id')->orderByDesc('sales.sold_at')
            ->select(['sales.id', 'sales.sale_number', 'sales.sold_at', 'sales.subtotal', 'sales.discount_total',
                'sales.total', 'users.name as cashier_name', 'customers.name as customer_name'])->get()
            ->map(function ($sale) use ($paymentsBySale, $returnsBySale) {
                $return = $this->money($returnsBySale[$sale->id] ?? 0);

                return ['id' => $sale->id, 'sale_number' => $sale->sale_number, 'sold_at' => $sale->sold_at,
                    'cashier_name' => $sale->cashier_name, 'customer_name' => $sale->customer_name,
                    'gross_sales' => $this->money($sale->subtotal), 'discount' => $this->money($sale->discount_total),
                    'completed_sales' => $this->money($sale->total), 'returns' => $return,
                    'net_sales' => bcsub($this->money($sale->total), $return, 2),
                    'payments' => $paymentsBySale[$sale->id] ?? []];
            })->all();

        return ['from' => $filter->from, 'to' => $filter->to, 'sale_count' => $saleCount,
            'gross_sales' => $gross, 'discount_total' => $discounts, 'completed_sales' => $completed,
            'returns_total' => $returns, 'net_sales' => bcsub($completed, $returns, 2),
            'average_sale' => $saleCount > 0 ? bcdiv($completed, (string) $saleCount, 2) : '0.00',
            'payments' => $payments, 'transactions' => $transactions];
    }

    public function dailyProfit(int $branchId, ReportFilterData $filter): array
    {
        $saleRows = $this->salesQuery($branchId, $filter)->selectRaw('DATE(sold_at) as business_date,
                SUM(subtotal) as gross_sales, SUM(discount_total) as discounts, SUM(total) as completed_sales')
            ->groupByRaw('DATE(sold_at)')->get()->keyBy('business_date');
        $returnRows = $this->returnsQuery($branchId, $filter)->selectRaw('DATE(sale_returns.returned_at) as business_date,
                SUM(sale_returns.refund_total) as returns_total')->groupByRaw('DATE(sale_returns.returned_at)')->get()->keyBy('business_date');
        $costRows = DB::table('sale_items')->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->where('sales.branch_id', $branchId)->where('sales.status', 'completed')->whereBetween('sales.sold_at', $this->timestamps($filter))
            ->selectRaw('DATE(sales.sold_at) as business_date, SUM(sale_items.quantity_base * sale_items.unit_cost) as cost')
            ->groupByRaw('DATE(sales.sold_at)')->get()->keyBy('business_date');
        $restoredRows = DB::table('sale_return_items')->join('sale_returns', 'sale_returns.id', '=', 'sale_return_items.sale_return_id')
            ->join('sale_items', 'sale_items.id', '=', 'sale_return_items.sale_item_id')->where('sale_returns.branch_id', $branchId)
            ->where('sale_return_items.restock', true)->whereBetween('sale_returns.returned_at', $this->timestamps($filter))
            ->selectRaw('DATE(sale_returns.returned_at) as business_date,
                SUM(sale_return_items.quantity * (sale_items.quantity_base / sale_items.quantity) * sale_items.unit_cost) as restored_cost')
            ->groupByRaw('DATE(sale_returns.returned_at)')->get()->keyBy('business_date');
        $expenseRows = DB::table('store_expenses')->where('branch_id', $branchId)->whereBetween('incurred_at', [$filter->from, $filter->to])
            ->selectRaw('incurred_at as business_date, SUM(amount) as expenses')->groupBy('incurred_at')->get()->keyBy('business_date');

        $daily = collect(CarbonPeriod::create($filter->from, $filter->to))->map(function ($day) use ($saleRows, $returnRows, $costRows, $restoredRows, $expenseRows) {
            $date = $day->toDateString();
            $sale = $saleRows[$date] ?? null;
            $gross = $this->money($sale?->gross_sales);
            $discounts = $this->money($sale?->discounts);
            $completed = $this->money($sale?->completed_sales);
            $returns = $this->money(($returnRows[$date] ?? null)?->returns_total);
            $netSales = bcsub($completed, $returns, 2);
            $cost = bcsub($this->money(($costRows[$date] ?? null)?->cost), $this->money(($restoredRows[$date] ?? null)?->restored_cost), 2);
            $grossProfit = bcsub($netSales, $cost, 2);
            $expenses = $this->money(($expenseRows[$date] ?? null)?->expenses);

            return ['business_date' => $date, 'gross_sales' => $gross, 'discount_total' => $discounts,
                'returns_total' => $returns, 'net_sales' => $netSales, 'cost_of_goods_sold' => $cost,
                'gross_profit' => $grossProfit, 'operating_expenses' => $expenses,
                'net_profit' => bcsub($grossProfit, $expenses, 2)];
        })->values();

        return ['from' => $filter->from, 'to' => $filter->to,
            'net_sales' => $this->sumMoney($daily, 'net_sales'), 'cost_of_goods_sold' => $this->sumMoney($daily, 'cost_of_goods_sold'),
            'gross_profit' => $this->sumMoney($daily, 'gross_profit'), 'operating_expenses' => $this->sumMoney($daily, 'operating_expenses'),
            'net_profit' => $this->sumMoney($daily, 'net_profit'), 'daily' => $daily->all()];
    }

    public function productSales(int $branchId, ReportFilterData $filter): array
    {
        $returns = $this->returnsQuery($branchId, $filter)->join('sale_return_items', 'sale_return_items.sale_return_id', '=', 'sale_returns.id')
            ->join('sale_items', 'sale_items.id', '=', 'sale_return_items.sale_item_id')->groupBy('sale_items.product_id')
            ->selectRaw('sale_items.product_id, SUM(sale_return_items.quantity * (sale_items.quantity_base / sale_items.quantity)) as quantity_base,
                SUM(sale_return_items.refund_amount) as refund_total,
                SUM(CASE WHEN sale_return_items.restock = 1 THEN sale_return_items.quantity * (sale_items.quantity_base / sale_items.quantity) * sale_items.unit_cost ELSE 0 END) as restored_cost')
            ->get()->keyBy('product_id');
        $query = DB::table('sale_items')->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->where('sales.branch_id', $branchId)->where('sales.status', 'completed')->whereBetween('sales.sold_at', $this->timestamps($filter))
            ->when($filter->product_id, fn ($q, $id) => $q->where('sale_items.product_id', $id))
            ->when($filter->customer_id, fn ($q, $id) => $q->where('sales.customer_id', $id));

        $products = $query->groupBy('sale_items.product_id', 'sale_items.product_name_snapshot')
            ->selectRaw('sale_items.product_id, sale_items.product_name_snapshot as product_name, SUM(sale_items.quantity_base) as quantity_base,
                SUM(sale_items.line_total) as sales, SUM(sale_items.line_discount) as discounts,
                SUM(sale_items.quantity_base * sale_items.unit_cost) as cost')->orderByDesc('sales')->get()
            ->map(function ($row) use ($returns) {
                $returned = $returns[$row->product_id] ?? null;
                $returnQuantity = (string) ($returned?->quantity_base ?? 0);
                $returnValue = $this->money($returned?->refund_total);
                $netSales = bcsub($this->money($row->sales), $returnValue, 2);
                $netCost = bcsub($this->money($row->cost), $this->money($returned?->restored_cost), 2);
                $profit = bcsub($netSales, $netCost, 2);

                return ['product_id' => $row->product_id, 'product_name' => $row->product_name,
                    'quantity_base' => (string) $row->quantity_base, 'returned_quantity_base' => $returnQuantity,
                    'net_quantity_base' => bcsub((string) $row->quantity_base, $returnQuantity, 3), 'sales' => $this->money($row->sales),
                    'returns' => $returnValue, 'net_sales' => $netSales, 'discounts' => $this->money($row->discounts),
                    'cost' => $netCost, 'gross_profit' => $profit,
                    'margin_percent' => bccomp($netSales, '0', 2) > 0 ? bcmul(bcdiv($profit, $netSales, 4), '100', 2) : '0.00'];
            })->all();

        return ['from' => $filter->from, 'to' => $filter->to, 'products' => $products];
    }

    public function saveZReading(int $branchId, string $date, int $userId, array $summary): object
    {
        DB::table('daily_sales_summaries')->updateOrInsert(['branch_id' => $branchId, 'business_date' => $date], [
            'sale_count' => $summary['sale_count'], 'gross_sales' => $summary['gross_sales'], 'returns_total' => $summary['returns_total'],
            'net_sales' => $summary['net_sales'], 'cash_total' => $summary['payments']['cash'] ?? '0.00', 'z_read_at' => now(),
            'z_read_by' => $userId, 'created_at' => now(), 'updated_at' => now()]);

        return DB::table('daily_sales_summaries')->where('branch_id', $branchId)->where('business_date', $date)->first();
    }

    private function salesQuery(int $branchId, ReportFilterData $filter): Builder
    {
        return DB::table('sales')->where('sales.branch_id', $branchId)->where('sales.status', 'completed')
            ->whereBetween('sales.sold_at', $this->timestamps($filter))
            ->when($filter->customer_id, fn ($q, $id) => $q->where('sales.customer_id', $id))
            ->when($filter->product_id, fn ($q, $id) => $q->whereExists(fn ($items) => $items->selectRaw('1')->from('sale_items')
                ->whereColumn('sale_items.sale_id', 'sales.id')->where('sale_items.product_id', $id)));
    }

    private function returnsQuery(int $branchId, ReportFilterData $filter): Builder
    {
        return DB::table('sale_returns')->join('sales', 'sales.id', '=', 'sale_returns.sale_id')
            ->where('sale_returns.branch_id', $branchId)->whereBetween('sale_returns.returned_at', $this->timestamps($filter))
            ->when($filter->customer_id, fn ($q, $id) => $q->where('sales.customer_id', $id))
            ->when($filter->product_id, fn ($q, $id) => $q->whereExists(fn ($items) => $items->selectRaw('1')->from('sale_return_items')
                ->join('sale_items', 'sale_items.id', '=', 'sale_return_items.sale_item_id')
                ->whereColumn('sale_return_items.sale_return_id', 'sale_returns.id')->where('sale_items.product_id', $id)));
    }

    private function timestamps(ReportFilterData $filter): array
    {
        return [$filter->from.' 00:00:00', $filter->to.' 23:59:59'];
    }

    private function money(mixed $value): string
    {
        return bcadd((string) ($value ?: 0), '0', 2);
    }

    private function sumMoney($rows, string $key): string
    {
        return $this->money($rows->sum(fn ($row) => $row[$key]));
    }
}
