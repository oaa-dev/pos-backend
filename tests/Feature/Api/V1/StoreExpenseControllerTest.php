<?php

use App\Enums\StatusEnum;
use App\Models\Branch;
use App\Models\CashMovement;
use App\Models\ExpenseCategory;
use App\Models\StoreExpense;
use App\Services\CashDrawerService;
use Database\Seeders\ExpenseCategorySeeder;

function expenseCategory(string $name = 'Kuryente'): ExpenseCategory
{
    return ExpenseCategory::firstOrCreate(['name' => $name], ['status' => StatusEnum::ACTIVE]);
}

it('lists the seeded expense categories', function () {
    actingAsOwner();
    $this->seed(ExpenseCategorySeeder::class);

    $response = $this->getJson('/api/v1/expense-categories')->assertOk();

    expect($response->json('data'))->toHaveCount(12)
        ->and(collect($response->json('data'))->pluck('name'))
        ->toContain('Kuryente', 'Yelo', 'Plastic bags');
});

it('seeds expense categories idempotently', function () {
    $this->seed(ExpenseCategorySeeder::class);
    $this->seed(ExpenseCategorySeeder::class);

    expect(ExpenseCategory::count())->toBe(12);
});

/**
 * The point of the whole module: an expense paid from the till must reduce the
 * expected cash, or the shift comes up short by exactly that amount and reads
 * as a shortage rather than a purchase.
 */
it('subtracts a drawer-paid expense from the expected cash', function () {
    actingAsOwner();
    $branch = Branch::factory()->create();
    $session = app(CashDrawerService::class)->open($branch, 1000);

    $this->postJson('/api/v1/expense', [
        'branch_id' => $branch->id,
        'expense_category_id' => expenseCategory('Yelo')->id,
        'amount' => 150,
        'description' => 'Ice for the softdrinks',
    ])->assertCreated()->assertJsonPath('data.affects_drawer', true);

    // One write, two effects.
    expect(CashMovement::where('type', 'store_expense')->count())->toBe(1);

    $this->postJson("/api/v1/shift/{$session->id}/close", ['closing_counted' => 850])
        ->assertOk()
        ->assertJsonPath('data.expected_cash', '850.00')
        ->assertJsonPath('data.variance', '0.00');
});

/**
 * Money from the owner's own pocket still reduces profit, but the drawer never
 * saw it — counting it would make every shift read as an overage.
 */
it('leaves the drawer alone for an owner-pocket expense', function () {
    actingAsOwner();
    $branch = Branch::factory()->create();
    $session = app(CashDrawerService::class)->open($branch, 1000);

    $this->postJson('/api/v1/expense', [
        'branch_id' => $branch->id,
        'expense_category_id' => expenseCategory('Upa')->id,
        'amount' => 500,
        'paid_from' => 'owner_pocket',
    ])->assertCreated()->assertJsonPath('data.affects_drawer', false);

    expect(CashMovement::count())->toBe(0)
        ->and(StoreExpense::first()->cash_drawer_session_id)->toBeNull();

    $this->postJson("/api/v1/shift/{$session->id}/close", ['closing_counted' => 1000])
        ->assertOk()
        ->assertJsonPath('data.expected_cash', '1000.00')
        ->assertJsonPath('data.variance', '0.00');
});

it('records a drawer expense with no open shift without failing', function () {
    actingAsOwner();
    $branch = Branch::factory()->create();

    $this->postJson('/api/v1/expense', [
        'branch_id' => $branch->id,
        'expense_category_id' => expenseCategory()->id,
        'amount' => 200,
    ])->assertCreated();

    // Recorded for the profit figure, but tied to no shift.
    expect(StoreExpense::first()->cash_drawer_session_id)->toBeNull();
});

it('rejects a zero or negative expense', function () {
    actingAsOwner();
    $branch = Branch::factory()->create();

    $this->postJson('/api/v1/expense', [
        'branch_id' => $branch->id,
        'expense_category_id' => expenseCategory()->id,
        'amount' => 0,
    ])->assertStatus(422);
});

it('summarises spend by category', function () {
    actingAsOwner();
    $branch = Branch::factory()->create();

    foreach ([['Yelo', 150], ['Yelo', 100], ['Kuryente', 800]] as [$name, $amount]) {
        $this->postJson('/api/v1/expense', [
            'branch_id' => $branch->id,
            'expense_category_id' => expenseCategory($name)->id,
            'amount' => $amount,
            'paid_from' => 'owner_pocket',
        ])->assertCreated();
    }

    $summary = $this->getJson("/api/v1/expenses/branch/{$branch->id}/summary")->assertOk();

    expect($summary->json('data.total'))->toEqual('1050.00')
        ->and($summary->json('data.by_category.Yelo'))->toEqual('250.00')
        ->and($summary->json('data.by_category.Kuryente'))->toEqual('800.00');
});

it('scopes expenses to the users branch', function () {
    actingAsOwner();
    $mine = Branch::factory()->create();
    $theirs = Branch::factory()->create();

    foreach ([$mine, $theirs] as $branch) {
        $this->postJson('/api/v1/expense', [
            'branch_id' => $branch->id,
            'expense_category_id' => expenseCategory()->id,
            'amount' => 100,
            'paid_from' => 'owner_pocket',
        ])->assertCreated();
    }

    expect($this->getJson('/api/v1/expenses')->json('data'))->toHaveCount(2);

    $tindera = actingAsUserWith(['expenses.view']);
    $tindera->branches()->attach($mine->id);

    expect($this->getJson('/api/v1/expenses')->json('data'))->toHaveCount(1);
});

/**
 * A tindera records what she spends from the till; the owner approves it
 * afterwards. That split is what makes a fabricated expense visible.
 */
it('lets a tindera record but not approve', function () {
    actingAsOwner();
    $branch = Branch::factory()->create();
    $category = expenseCategory();

    $tindera = actingAsUserWith(['expenses.create', 'expenses.view', 'branches.view']);
    $tindera->branches()->attach($branch->id);

    $expense = $this->postJson('/api/v1/expense', [
        'branch_id' => $branch->id,
        'expense_category_id' => $category->id,
        'amount' => 120,
        'paid_from' => 'owner_pocket',
    ])->assertCreated()->json('data.id');

    $this->postJson("/api/v1/expense/{$expense}/approve")
        ->assertForbidden()
        ->assertJsonPath('success', false);
});

it('marks an expense approved by the owner', function () {
    $owner = actingAsOwner();
    $branch = Branch::factory()->create();

    $expense = $this->postJson('/api/v1/expense', [
        'branch_id' => $branch->id,
        'expense_category_id' => expenseCategory()->id,
        'amount' => 120,
        'paid_from' => 'owner_pocket',
    ])->json('data.id');

    $this->postJson("/api/v1/expense/{$expense}/approve")
        ->assertOk()
        ->assertJsonPath('data.is_approved', true);

    expect(StoreExpense::find($expense)->approved_by)->toBe($owner->id);
});
