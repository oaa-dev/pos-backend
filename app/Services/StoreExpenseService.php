<?php

namespace App\Services;

use App\Data\StoreExpenseData;
use App\Enums\CashMovementTypeEnum;
use App\Models\Branch;
use App\Models\StoreExpense;
use App\Repositories\Contracts\StoreExpenseRepositoryInterface;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Spatie\LaravelData\Optional;

/**
 * Operating expenses — what turns gross margin into actual profit.
 *
 *   Gross profit = selling price − product cost
 *   Net profit   = gross profit − operating expenses
 *
 * Without this, "profit" only ever means margin on goods, and a store can look
 * profitable while losing money on kuryente, yelo, and plastic bags.
 */
class StoreExpenseService extends BaseService
{
    private const SCALE = 2;

    public function __construct(
        protected readonly StoreExpenseRepositoryInterface $expenses,
        protected readonly CashDrawerService $drawer,
    ) {
        parent::__construct($expenses);
    }

    /**
     * @param  array{branch_id:int, expense_category_id:int, amount:float|string,
     *     description?:string|null, paid_from?:string, supplier_id?:int|null,
     *     incurred_at?:string|null}  $data
     */
    /** `Optional` collapses to the given fallback; a real null stays null. */
    private function value(mixed $field, mixed $fallback = null): mixed
    {
        return $field instanceof Optional ? $fallback : $field;
    }

    public function record(StoreExpenseData $data): StoreExpense
    {
        $amount = bcadd((string) $this->value($data->amount, 0), '0', self::SCALE);

        if (bccomp($amount, '0', self::SCALE) <= 0) {
            throw new \InvalidArgumentException('An expense must be greater than zero.');
        }

        $paidFrom = $this->value($data->paid_from, 'drawer') ?? 'drawer';

        return DB::transaction(function () use ($data, $amount, $paidFrom) {
            $branch = Branch::findOrFail($data->branch_id);

            // Only money leaving the till affects the shift. An expense paid
            // from the owner's own pocket still reduces profit, but the drawer
            // never saw it.
            $session = $paidFrom === 'drawer'
                ? $this->drawer->findOpenForBranch($branch->id)
                : null;

            $expense = $this->expenses->create([
                'branch_id' => $branch->id,
                'expense_category_id' => $data->expense_category_id,
                'amount' => $amount,
                'description' => $this->value($data->description),
                'paid_from' => $paidFrom,
                'cash_drawer_session_id' => $session?->id,
                'supplier_id' => $this->value($data->supplier_id),
                'incurred_at' => $this->value($data->incurred_at) ?? now()->toDateString(),
                'recorded_by' => Auth::id(),
            ]);

            // One write, two effects, same transaction: without the cash
            // movement the shift would come up short by exactly this amount
            // and read as a shortage rather than a purchase.
            if ($session !== null) {
                $this->drawer->recordMovement(
                    session: $session,
                    type: CashMovementTypeEnum::STORE_EXPENSE,
                    amount: $amount,
                    reason: $this->value($data->description) ?? 'Store expense',
                    reference: $expense,
                );
            }

            return $expense->load(['category', 'branch']);
        });
    }

    public function approve(StoreExpense $expense, int $approvedBy): StoreExpense
    {
        return $this->expenses->update($expense, ['approved_by' => $approvedBy]);
    }

    /**
     * Total spent in a period, and the same split by category — the two
     * numbers a net-profit report needs.
     *
     * @return array{total: string, by_category: array<string, string>}
     */
    public function summary(int $branchId, string $from, string $to): array
    {
        $rows = $this->expenses->betweenDates($branchId, $from, $to);

        $total = '0.00';
        $byCategory = [];

        foreach ($rows as $expense) {
            $name = $expense->category?->name ?? 'uncategorised';
            $byCategory[$name] = bcadd(
                $byCategory[$name] ?? '0.00',
                (string) $expense->amount,
                self::SCALE,
            );
            $total = bcadd($total, (string) $expense->amount, self::SCALE);
        }

        arsort($byCategory);

        return ['total' => $total, 'by_category' => $byCategory];
    }
}
