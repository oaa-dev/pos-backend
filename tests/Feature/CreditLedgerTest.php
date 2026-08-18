<?php

use App\Exceptions\CreditLimitExceededException;
use App\Models\CreditAllocation;
use App\Models\CreditTransaction;
use App\Models\Customer;
use App\Models\Sale;
use App\Services\CreditService;

function credit(): CreditService
{
    return app(CreditService::class);
}

function suki(array $attributes = []): Customer
{
    return Customer::factory()->create($attributes);
}

it('records a charge and moves the cached balance', function () {
    actingAsOwner();
    $customer = suki();

    $charge = posCreditCharge($customer, 350, note: 'Groceries');

    expect($charge->amount)->toEqual('350.00')
        ->and($charge->balance_after)->toEqual('350.00')
        ->and($charge->outstanding)->toEqual('350.00')
        ->and($customer->fresh()->current_balance)->toEqual('350.00');
});

/**
 * There is no ceiling on utang — an owner judges each suki case by case, so a
 * large charge is allowed and only `is_blocked` refuses.
 */
it('allows a charge of any size', function () {
    actingAsOwner();
    $customer = suki();

    $charge = posCreditCharge($customer, 99999);

    expect($charge->amount)->toEqual('99999.00');
});

it('refuses a charge for a blocked customer', function () {
    actingAsOwner();
    $customer = Customer::factory()->blocked()->create();

    posCreditCharge($customer, 50);
})->throws(CreditLimitExceededException::class);

it('rejects a credit charge for a sale belonging to another customer', function () {
    actingAsOwner();
    $firstCustomer = suki();
    $secondCustomer = suki();
    $sale = Sale::findOrFail(posCreditCharge($firstCustomer, 100)->sale_id);

    credit()->charge($secondCustomer, $sale, 100);
})->throws(InvalidArgumentException::class);

// --- FIFO allocation -------------------------------------------------------

/**
 * The reason allocations exist at all: a bare running balance cannot answer
 * "which utang is still unpaid", so every debt would look the same age.
 */
it('settles the oldest charge first', function () {
    actingAsOwner();
    $customer = suki();

    $first = posCreditCharge($customer, 100, note: 'Aug 1');
    $second = posCreditCharge($customer, 200, note: 'Aug 5');

    credit()->collect($customer, 100);

    expect($first->fresh()->outstanding)->toEqual('0.00')
        ->and($second->fresh()->outstanding)->toEqual('200.00');
});

it('spans a payment across several charges', function () {
    actingAsOwner();
    $customer = suki();

    $first = posCreditCharge($customer, 100);
    $second = posCreditCharge($customer, 200);

    $payment = credit()->collect($customer, 250);

    expect($first->fresh()->outstanding)->toEqual('0.00')
        ->and($second->fresh()->outstanding)->toEqual('50.00')
        ->and($payment->allocations)->toHaveCount(2)
        ->and($customer->fresh()->current_balance)->toEqual('50.00');
});

it('records a partial payment against one charge', function () {
    actingAsOwner();
    $customer = suki();

    $charge = posCreditCharge($customer, 350);
    credit()->collect($customer, 100);

    expect($charge->fresh()->outstanding)->toEqual('250.00')
        ->and($customer->fresh()->current_balance)->toEqual('250.00');
});

/**
 * Paying more than is owed leaves the suki in credit. Inventing a charge to
 * absorb the excess would falsify the ledger.
 */
it('leaves an overpayment unallocated as a credit balance', function () {
    actingAsOwner();
    $customer = suki();

    posCreditCharge($customer, 100);
    $payment = credit()->collect($customer, 150);

    expect($customer->fresh()->current_balance)->toEqual('-50.00')
        ->and($payment->allocations)->toHaveCount(1)
        ->and($payment->allocations->first()->amount)->toEqual('100.00');
});

it('keeps the cached balance equal to the ledger', function () {
    actingAsOwner();
    $customer = suki();

    posCreditCharge($customer, 350);
    credit()->collect($customer, 100);
    posCreditCharge($customer, 80);
    credit()->collect($customer, 30);

    $last = CreditTransaction::where('customer_id', $customer->id)->latest('id')->first();

    expect($customer->fresh()->current_balance)->toEqual($last->balance_after)
        // 350 - 100 + 80 - 30
        ->and($customer->fresh()->current_balance)->toEqual('300.00');
});

// --- writeoff and adjustment ----------------------------------------------

it('writes off a balance without touching the drawer', function () {
    actingAsOwner();
    $customer = suki();

    $charge = posCreditCharge($customer, 200);
    $writeoff = credit()->writeOff($customer, 200, note: 'Hindi na mababayaran');

    expect($customer->fresh()->current_balance)->toEqual('0.00')
        ->and($charge->fresh()->outstanding)->toEqual('0.00')
        // A writeoff moves no money, so it must never inflate expected cash.
        ->and($writeoff->cash_drawer_session_id)->toBeNull();
});

it('reverses a mistaken charge with an adjustment rather than deleting', function () {
    actingAsOwner();
    $customer = suki();

    $charge = posCreditCharge($customer, 500, note: 'Mali');
    credit()->adjust($customer, -500, note: 'Reversal');

    expect($customer->fresh()->current_balance)->toEqual('0.00')
        ->and($charge->fresh()->outstanding)->toEqual('0.00')
        // The original row survives — the statement still explains itself.
        ->and(CreditTransaction::where('customer_id', $customer->id)->count())->toBe(2);
});

it('rejects a zero adjustment', function () {
    actingAsOwner();
    credit()->adjust(suki(), 0);
})->throws(InvalidArgumentException::class);

// --- aging -----------------------------------------------------------------

it('buckets outstanding charges by age', function () {
    actingAsOwner();
    $customer = suki();

    posCreditCharge($customer, 100);
    CreditTransaction::latest('id')->first()->update(['occurred_at' => now()->subDays(3)]);

    posCreditCharge($customer, 200);
    CreditTransaction::latest('id')->first()->update(['occurred_at' => now()->subDays(45)]);

    posCreditCharge($customer, 300);
    CreditTransaction::latest('id')->first()->update(['occurred_at' => now()->subDays(90)]);

    $aging = credit()->aging($customer->fresh());

    expect($aging['days_1_7'])->toEqual('100.00')
        ->and($aging['days_31_60'])->toEqual('200.00')
        ->and($aging['days_over_60'])->toEqual('300.00')
        ->and($aging['total'])->toEqual('600.00');
});

it('drops a settled charge out of the aging', function () {
    actingAsOwner();
    $customer = suki();

    posCreditCharge($customer, 100);
    CreditTransaction::latest('id')->first()->update(['occurred_at' => now()->subDays(45)]);

    credit()->collect($customer, 100);

    expect(credit()->aging($customer->fresh())['total'])->toEqual('0.00');
});

it('flags a charge past its due date as overdue', function () {
    actingAsOwner();
    $customer = suki();

    posCreditCharge($customer, 100, dueDate: now()->subDay()->toDateString());
    posCreditCharge($customer, 200, dueDate: now()->addWeek()->toDateString());

    $overdue = credit()->overdue($customer->fresh());

    expect($overdue)->toHaveCount(1)
        ->and($overdue->first()->amount)->toEqual('100.00');
});

// --- reconcile -------------------------------------------------------------

it('recomputes a drifted balance from the ledger', function () {
    actingAsOwner();
    $customer = suki();

    posCreditCharge($customer, 400);
    $customer->fresh()->update(['current_balance' => 999]);

    $result = credit()->reconcile($customer->fresh());

    expect($result['balance_before'])->toEqual('999.00')
        ->and($result['balance_after'])->toEqual('400.00')
        ->and($customer->fresh()->current_balance)->toEqual('400.00');
});

it('never deletes an allocation when settling', function () {
    actingAsOwner();
    $customer = suki();

    posCreditCharge($customer, 100);
    credit()->collect($customer, 60);
    credit()->collect($customer, 40);

    expect(CreditAllocation::count())->toBe(2)
        ->and($customer->fresh()->current_balance)->toEqual('0.00');
});
