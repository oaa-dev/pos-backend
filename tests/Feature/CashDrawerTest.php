<?php

use App\Enums\CashMovementTypeEnum;
use App\Exceptions\ShiftNotOpenException;
use App\Models\Branch;
use App\Models\CashDrawerSession;
use App\Services\CashDrawerService;

function drawer(): CashDrawerService
{
    return app(CashDrawerService::class);
}

it('opens a session with an opening float', function () {
    actingAsOwner();
    $branch = Branch::factory()->create();

    $session = drawer()->open($branch, 1500);

    expect($session->opening_float)->toEqual('1500.00')
        ->and($session->status)->toBe('open')
        ->and($session->isOpen())->toBeTrue();
});

/**
 * MySQL cannot express "one open row per branch" as a constraint, so the guard
 * is a locked read. If it ever regresses, two tinderas could run overlapping
 * shifts and neither drawer would reconcile.
 */
it('refuses a second open session in the same branch', function () {
    actingAsOwner();
    $branch = Branch::factory()->create();

    drawer()->open($branch, 1000);
    drawer()->open($branch, 500);
})->throws(InvalidArgumentException::class);

it('allows a session in another branch at the same time', function () {
    actingAsOwner();
    $one = Branch::factory()->create();
    $two = Branch::factory()->create();

    drawer()->open($one, 1000);
    drawer()->open($two, 1000);

    expect(CashDrawerSession::open()->count())->toBe(2);
});

it('allows a new session once the previous one is closed', function () {
    actingAsOwner();
    $branch = Branch::factory()->create();

    $first = drawer()->open($branch, 1000);
    drawer()->close($first, 1000);

    $second = drawer()->open($branch, 800);

    expect($second->id)->not->toBe($first->id)
        ->and(CashDrawerSession::open()->count())->toBe(1);
});

it('throws when no session is open', function () {
    actingAsOwner();
    $branch = Branch::factory()->create();

    drawer()->requireOpenSession($branch);
})->throws(ShiftNotOpenException::class);

// --- expected cash ---------------------------------------------------------

it('expects the opening float when nothing has happened', function () {
    actingAsOwner();
    $session = drawer()->open(Branch::factory()->create(), 1000);

    expect(drawer()->expectedCash($session))->toEqual('1000.00');
});

it('adds inflows and subtracts outflows', function () {
    actingAsOwner();
    $session = drawer()->open(Branch::factory()->create(), 1000);

    drawer()->recordMovement($session, CashMovementTypeEnum::FLOAT_IN, 500, 'Dagdag panukli');
    drawer()->recordMovement($session, CashMovementTypeEnum::STORE_EXPENSE, 120, 'Yelo');
    drawer()->recordMovement($session, CashMovementTypeEnum::OWNER_WITHDRAWAL, 300);

    // 1000 + 500 - 120 - 300
    expect(drawer()->expectedCash($session->fresh()))->toEqual('1080.00');
});

/**
 * The counter-intuitive pair: a customer cashing IN hands over pesos, so the
 * drawer gains. Getting this backwards makes every GCash day reconcile wrong.
 */
it('treats a GCash cash-in as an inflow and cash-out as an outflow', function () {
    actingAsOwner();
    $session = drawer()->open(Branch::factory()->create(), 1000);

    drawer()->recordMovement($session, CashMovementTypeEnum::GCASH_CASH_IN, 1000);
    drawer()->recordMovement($session, CashMovementTypeEnum::GCASH_CASH_OUT, 400);

    expect(drawer()->expectedCash($session->fresh()))->toEqual('1600.00');
});

it('refuses a zero or negative cash movement', function () {
    actingAsOwner();
    $session = drawer()->open(Branch::factory()->create(), 1000);

    drawer()->recordMovement($session, CashMovementTypeEnum::PAID_IN, 0);
})->throws(InvalidArgumentException::class);

// --- closing ---------------------------------------------------------------

it('records a shortage as a negative variance', function () {
    actingAsOwner();
    $session = drawer()->open(Branch::factory()->create(), 1000);

    $closed = drawer()->close($session, 950, 'Kulang ng 50');

    expect($closed->expected_cash)->toEqual('1000.00')
        ->and($closed->closing_counted)->toEqual('950.00')
        ->and($closed->variance)->toEqual('-50.00')
        ->and($closed->status)->toBe('closed');
});

it('records an overage as a positive variance', function () {
    actingAsOwner();
    $session = drawer()->open(Branch::factory()->create(), 1000);

    expect(drawer()->close($session, 1025)->variance)->toEqual('25.00');
});

it('refuses to close an already closed session', function () {
    actingAsOwner();
    $session = drawer()->open(Branch::factory()->create(), 1000);
    drawer()->close($session, 1000);

    drawer()->close($session->fresh(), 1000);
})->throws(InvalidArgumentException::class);

it('refuses cash movements against a closed session', function () {
    actingAsOwner();
    $session = drawer()->open(Branch::factory()->create(), 1000);
    drawer()->close($session, 1000);

    drawer()->recordMovement($session->fresh(), CashMovementTypeEnum::PAID_IN, 100);
})->throws(InvalidArgumentException::class);
