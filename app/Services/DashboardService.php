<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Customer;
use App\Support\Money;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * The one screen an owner opens first.
 *
 * Deliberately answers "is anything wrong, and where do I act today?" rather
 * than restating figures the reports already own. Four of its five sections
 * are things to *do* — reorder, waste about to happen, money owed — and only
 * one is a number to read.
 *
 * Assembled in a single call because a dashboard is where load time is
 * noticed, and composing it from the per-branch report endpoints would be four
 * requests per branch.
 */
class DashboardService
{
    private const MONEY = 2;

    /**
     * @return array<string, mixed>
     */
    public function overview(int $days): array
    {
        $branches = $this->visibleBranches();
        $branchIds = $branches->pluck('id')->all();

        $today = now()->toDateString();
        $from = now()->subDays(max($days, 1) - 1)->toDateString();

        return [
            'days' => $days,
            'from' => $from,
            'to' => $today,
            'sales_today' => $this->salesToday($branches, $today),
            'top_products' => $this->topProducts($branchIds, $from, $today),
            'attention' => $this->attention($branchIds),
            'utang' => $this->utang($branchIds),
        ];
    }

    /**
     * The branches this user may see.
     *
     * Mirrors `BaseRepository::applyBranchScope` — `branches.view-all` sees
     * every branch, everyone else sees only what they are assigned to. The
     * dashboard aggregates across branches, so this is the guard that stops one
     * supervisor reading another branch's takings.
     *
     * **This is data scoping, not an authorization gate.** Whether the actor
     * may open the dashboard at all is settled by `can:reports.view` on the
     * route; this decides *whose numbers* they get. It reads like a permission
     * check in a service and is not one — deleting it hands a tindera every
     * branch's takings, silently.
     */
    private function visibleBranches(): Collection
    {
        $user = Auth::user();

        if ($user !== null && ! $user->can('branches.view-all')) {
            return $user->branches()->orderBy('name')->get();
        }

        return Branch::query()->orderBy('name')->get();
    }

    /**
     * Today's takings per branch, with the total.
     *
     * Read from `sales` rather than `daily_sales_summaries`: that table is
     * written at Z-read, so it holds closed days and would show today as zero
     * until the shift is counted.
     *
     * @return array<string, mixed>
     */
    private function salesToday(Collection $branches, string $today): array
    {
        $rows = DB::table('sales')
            ->whereIn('branch_id', $branches->pluck('id'))
            ->where('status', 'completed')
            ->whereDate('sold_at', $today)
            ->groupBy('branch_id')
            ->selectRaw('branch_id, COUNT(*) as sale_count, SUM(total) as net_sales')
            ->get()
            ->keyBy('branch_id');

        // Cost of what was sold, so the headline is profit rather than takings.
        $cost = DB::table('sale_items')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->whereIn('sales.branch_id', $branches->pluck('id'))
            ->where('sales.status', 'completed')
            ->whereDate('sales.sold_at', $today)
            ->groupBy('sales.branch_id')
            ->selectRaw('sales.branch_id, SUM(sale_items.quantity_base * sale_items.unit_cost) as cost')
            ->get()
            ->keyBy('branch_id');

        $branchRows = $branches->map(function (Branch $branch) use ($rows, $cost) {
            $row = $rows[$branch->id] ?? null;
            $sales = $this->money($row?->net_sales);
            $spent = $this->money($cost[$branch->id]->cost ?? 0);

            return [
                'branch_id' => $branch->id,
                'branch_name' => $branch->name,
                'sale_count' => (int) ($row?->sale_count ?? 0),
                'net_sales' => $sales,
                'gross_profit' => bcsub($sales, $spent, self::MONEY),
            ];
        })->values();

        return [
            'branches' => $branchRows->all(),
            'total_sales' => $branchRows->reduce(
                fn (string $carry, array $row) => bcadd($carry, $row['net_sales'], self::MONEY),
                '0.00',
            ),
            'total_profit' => $branchRows->reduce(
                fn (string $carry, array $row) => bcadd($carry, $row['gross_profit'], self::MONEY),
                '0.00',
            ),
            'total_count' => $branchRows->sum('sale_count'),
        ];
    }

    /**
     * What actually moved, over the chosen window.
     *
     * Ranked by quantity *and* by profit, because they disagree: the fast
     * mover is often the thin-margin one, and an owner deciding what to push
     * needs both answers rather than whichever the query happened to sort by.
     *
     * @return array<string, mixed>
     */
    private function topProducts(array $branchIds, string $from, string $to): array
    {
        if ($branchIds === []) {
            return ['by_quantity' => [], 'by_profit' => []];
        }

        $rows = DB::table('sale_items')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->whereIn('sales.branch_id', $branchIds)
            ->where('sales.status', 'completed')
            ->whereBetween('sales.sold_at', [$from.' 00:00:00', $to.' 23:59:59'])
            ->groupBy('sale_items.product_id', 'sale_items.product_name_snapshot')
            ->selectRaw('sale_items.product_id,
                sale_items.product_name_snapshot as product_name,
                SUM(sale_items.quantity_base) as quantity_base,
                SUM(sale_items.line_total) as sales,
                SUM(sale_items.quantity_base * sale_items.unit_cost) as cost')
            ->get()
            ->map(fn ($row) => [
                'product_id' => $row->product_id,
                'product_name' => $row->product_name,
                'quantity_base' => bcadd((string) $row->quantity_base, '0', 3),
                'sales' => $this->money($row->sales),
                'gross_profit' => bcsub($this->money($row->sales), $this->money($row->cost), self::MONEY),
            ]);

        return [
            'by_quantity' => $rows
                ->sortByDesc(fn (array $row) => (float) $row['quantity_base'])
                ->take(5)->values()->all(),
            'by_profit' => $rows
                ->sortByDesc(fn (array $row) => (float) $row['gross_profit'])
                ->take(5)->values()->all(),
        ];
    }

    /**
     * The two things that cost money by being ignored.
     *
     * Low stock is by the store's own reorder points — the same rule Stock
     * Levels and the What to Buy tab use, so the three cannot disagree. It
     * stays empty until reorder points are set, which is honest: nothing has
     * been configured, so nothing is overdue.
     *
     * @return array<string, mixed>
     */
    private function attention(array $branchIds): array
    {
        if ($branchIds === []) {
            return ['low_stock' => ['count' => 0, 'items' => []], 'expiring' => ['count' => 0, 'items' => []]];
        }

        $low = DB::table('branch_product_stocks')
            ->join('products', 'products.id', '=', 'branch_product_stocks.product_id')
            ->join('branches', 'branches.id', '=', 'branch_product_stocks.branch_id')
            ->whereIn('branch_product_stocks.branch_id', $branchIds)
            ->where('branch_product_stocks.reorder_point', '>', 0)
            ->whereColumn(
                'branch_product_stocks.quantity_on_hand',
                '<=',
                'branch_product_stocks.reorder_point',
            )
            ->orderBy('branch_product_stocks.quantity_on_hand')
            ->select([
                'products.name as product_name',
                'branches.name as branch_name',
                'branch_product_stocks.quantity_on_hand',
                'branch_product_stocks.reorder_point',
            ])
            ->get();

        // A month's warning: long enough to discount it or move it, short
        // enough that the list stays worth reading.
        $horizon = now()->addDays(30)->toDateString();

        $expiring = DB::table('product_batches')
            ->join('products', 'products.id', '=', 'product_batches.product_id')
            ->join('branches', 'branches.id', '=', 'product_batches.branch_id')
            ->whereIn('product_batches.branch_id', $branchIds)
            ->where('product_batches.quantity_remaining', '>', 0)
            ->whereNotNull('product_batches.expiry_date')
            ->whereDate('product_batches.expiry_date', '<=', $horizon)
            ->orderBy('product_batches.expiry_date')
            ->select([
                'products.name as product_name',
                'branches.name as branch_name',
                'product_batches.expiry_date',
                'product_batches.quantity_remaining',
            ])
            ->get();

        return [
            'low_stock' => [
                'count' => $low->count(),
                'items' => $low->take(5)->values()->all(),
            ],
            'expiring' => [
                'count' => $expiring->count(),
                'items' => $expiring->take(5)->values()->all(),
            ],
        ];
    }

    /**
     * What the suki owe.
     *
     * Customers are branch-scoped, so a supervisor sees only their own
     * branch's ledger.
     *
     * @return array<string, mixed>
     */
    private function utang(array $branchIds): array
    {
        if ($branchIds === []) {
            return ['total' => '0.00', 'customer_count' => 0, 'customers' => []];
        }

        $owing = Customer::query()
            ->whereIn('branch_id', $branchIds)
            ->owing()
            ->orderByDesc('current_balance')
            ->get(['id', 'name', 'nickname', 'current_balance']);

        return [
            'total' => $owing->reduce(
                fn (string $carry, Customer $customer) => bcadd($carry, (string) $customer->current_balance, self::MONEY),
                '0.00',
            ),
            'customer_count' => $owing->count(),
            'customers' => $owing->take(5)->map(fn (Customer $customer) => [
                'id' => $customer->id,
                'name' => $customer->nickname ?? $customer->name,
                'balance' => $this->money($customer->current_balance),
            ])->values()->all(),
        ];
    }

    private function money(string|float|int|null $value): string
    {
        return Money::round((string) ($value ?? 0), self::MONEY);
    }
}
