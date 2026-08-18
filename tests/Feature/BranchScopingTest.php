<?php

use App\Models\Branch;
use App\Repositories\Contracts\BranchRepositoryInterface;

it('narrows the listing to the branches the user is assigned to', function () {
    $mine = Branch::factory()->create();
    Branch::factory()->count(2)->create();

    $tindera = actingAsUserWith(['branches.view']);
    $tindera->branches()->attach($mine->id);

    $response = $this->getJson('/api/v1/branches')->assertOk();

    expect($response->json('data'))->toHaveCount(1)
        ->and($response->json('data.0.id'))->toBe($mine->id);
});

it('shows every branch to a user holding branches.view-all', function () {
    Branch::factory()->count(3)->create();

    actingAsUserWith(['branches.view', 'branches.view-all']);

    expect($this->getJson('/api/v1/branches')->json('data'))->toHaveCount(3);
});

it('returns nothing to an assigned-to-nothing user without view-all', function () {
    Branch::factory()->count(3)->create();

    actingAsUserWith(['branches.view']);

    expect($this->getJson('/api/v1/branches')->json('data'))->toHaveCount(0);
});

/**
 * Seeders, queued jobs and artisan commands run with no authenticated user.
 * A scope that assumes auth()->user() is non-null fatals there, so the no-op
 * is load-bearing rather than defensive.
 */
it('does not scope when there is no authenticated user', function () {
    Branch::factory()->count(3)->create();

    $repository = app(BranchRepositoryInterface::class);

    expect($repository->paginate()->total())->toBe(3);
});

/**
 * Repository scoping only covers listings — route-model binding resolves a
 * single branch directly. Without BranchPolicy this returns 200.
 */
it('refuses to show a branch the user is not assigned to', function () {
    $mine = Branch::factory()->create();
    $theirs = Branch::factory()->create();

    $tindera = actingAsUserWith(['branches.view']);
    $tindera->branches()->attach($mine->id);

    $this->getJson("/api/v1/branch/{$mine->id}")->assertOk();

    $this->getJson("/api/v1/branch/{$theirs->id}")
        ->assertForbidden()
        ->assertJsonPath('success', false);
});

it('refuses to update a branch the user is not assigned to', function () {
    $theirs = Branch::factory()->create(['name' => 'Not Mine']);

    actingAsUserWith(['branches.view', 'branches.update']);

    $this->patchJson("/api/v1/branch/{$theirs->id}", ['name' => 'Hijacked'])
        ->assertForbidden()
        ->assertJsonPath('success', false);

    expect($theirs->fresh()->name)->toBe('Not Mine');
});

it('lets a view-all holder show any branch', function () {
    $branch = Branch::factory()->create();

    actingAsUserWith(['branches.view', 'branches.view-all']);

    $this->getJson("/api/v1/branch/{$branch->id}")->assertOk();
});
