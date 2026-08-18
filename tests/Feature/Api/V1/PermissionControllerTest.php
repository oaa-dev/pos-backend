<?php

use Database\Seeders\PermissionSeeder;

beforeEach(function () {
    actingAsOwner();
});

it('returns the seeded catalogue grouped by module', function () {
    $this->seed(PermissionSeeder::class);

    $response = $this->getJson('/api/v1/permissions');

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonCount(19, 'data');

    $groups = collect($response->json('data'));

    expect($groups->pluck('name')->all())->toBe([
        'branches', 'users', 'roles', 'products',
        // The three catalogue-support modules sit next to `products` in the
        // seeder, and this asserts that order, not just the membership.
        'categories', 'units', 'discount-types',
        'inventory', 'sales',
        'customers', 'credit', 'shifts', 'suppliers',
        'expenses', 'reports', 'settings', 'support',
        // Platform-only, and seeded just before `stores` for that reason.
        'activity-logs',
        'stores',
    ]);

    expect($groups->sum(fn ($group) => count($group['permissions'])))->toBe(73);
});

it('splits each permission name into its module and action', function () {
    $this->seed(PermissionSeeder::class);

    $response = $this->getJson('/api/v1/permissions');

    $sales = collect($response->json('data'))->firstWhere('name', 'sales');

    expect(collect($sales['permissions'])->pluck('action')->all())
        ->toBe(['create', 'view', 'void', 'refund', 'return', 'suspend', 'discount', 'override-price']);

    expect(collect($sales['permissions'])->pluck('module')->unique()->all())
        ->toBe(['sales']);
});

it('carries the module description used by the role editor', function () {
    $this->seed(PermissionSeeder::class);

    $response = $this->getJson('/api/v1/permissions');

    $reports = collect($response->json('data'))->firstWhere('name', 'reports');

    expect($reports['description'])->toBe('Sales and inventory reporting');
});

it('requires authentication', function () {
    app()['auth']->forgetGuards();

    $this->getJson('/api/v1/permissions')->assertUnauthorized();
});
