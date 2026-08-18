<?php

use App\Enums\SupportTicketStatusEnum;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Store;
use App\Models\SupportTicket;
use App\Models\SupportTicketReply;
use App\Models\User;
use App\Services\SupportTicketService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\SystemRoleSeeder;

/** A ticket belonging to some other customer. */
function foreignTicket(): SupportTicket
{
    $other = anotherStore();
    $stranger = User::factory()->create();
    $stranger->stores()->syncWithoutDetaching([$other->id => ['is_owner' => true]]);

    return SupportTicket::create([
        'store_id' => $other->id,
        'user_id' => $stranger->id,
        'subject' => 'Their problem',
        'body' => 'Not yours to read.',
    ]);
}

function ownTicket(): SupportTicket
{
    return SupportTicket::create([
        'store_id' => testStore()->id,
        'subject' => 'Printer will not cut',
        'body' => 'The receipt tears instead.',
    ]);
}

it('raises a ticket against the actor’s own store', function () {
    actingAsOwner();

    $this->postJson('/api/v1/support-ticket', [
        'store_id' => testStore()->id,
        'subject' => 'Printer will not cut',
        'body' => 'The receipt tears instead.',
        'category' => 'bug',
        'priority' => 'normal',
    ])
        ->assertCreated()
        ->assertJsonPath('data.status', 'open')
        ->assertJsonPath('data.store_id', testStore()->id);
});

/**
 * A bare `exists:stores,id` — which twelve other requests still use — would
 * accept this.
 */
it('refuses to raise a ticket against another store', function () {
    actingAsOwner();

    $this->postJson('/api/v1/support-ticket', [
        'store_id' => anotherStore()->id,
        'subject' => 'Sneaky',
        'body' => 'Filed against someone else.',
        'category' => 'bug',
        'priority' => 'normal',
    ])->assertJsonValidationErrors('store_id');

    expect(SupportTicket::count())->toBe(0);
});

/**
 * `$attributes` mirrors the column defaults. Without it the row takes the
 * database value while the returned instance carries null, and the controller
 * serialises exactly that instance.
 */
it('returns a fully stamped instance without re-reading it', function () {
    actingAsOwner();

    $ticket = app(SupportTicketService::class)->create([
        'store_id' => testStore()->id,
        'subject' => 'S',
        'body' => 'B',
    ]);

    expect($ticket->status)->toBe(SupportTicketStatusEnum::OPEN)
        ->and($ticket->status)->toBe($ticket->fresh()->status);
});

it('shows an owner only their own store’s tickets', function () {
    actingAsUserWith(['support.view']);

    ownTicket();
    foreignTicket();

    $subjects = collect($this->getJson('/api/v1/support-tickets')->json('data'))->pluck('subject');

    expect($subjects)->toHaveCount(1)
        ->and($subjects)->not->toContain('Their problem');
});

it('shows the operator every store’s tickets', function () {
    actingAsUserWith(['support.view', 'stores.view']);

    ownTicket();
    foreignTicket();

    expect($this->getJson('/api/v1/support-tickets')->json('data'))->toHaveCount(2);
});

/**
 * **The negative test that matters**, and it is at the detail endpoint.
 *
 * The actor *holds* `support.view`, so the `can:` middleware lets them through
 * — only the policy can refuse. Route-model binding never reaches the
 * repository's store rule, so without `SupportTicketPolicy` this returns
 * another customer's ticket.
 */
it('refuses another store’s ticket at the detail endpoint', function () {
    actingAsUserWith(['support.view']);

    $ticket = foreignTicket();

    $this->getJson("/api/v1/support-ticket/{$ticket->id}")
        ->assertForbidden()
        ->assertJsonPath('success', false);
});

/**
 * The positive half, and it is not optional.
 *
 * The refusal test above passes whether the policy denies deliberately or
 * simply has no `view()` method at all — a missing method denies everything,
 * including this. Only asserting that the *right* actor gets through proves the
 * policy is making a decision rather than blocking the door.
 */
it('serves an owner their own store’s ticket at the detail endpoint', function () {
    actingAsUserWith(['support.view']);

    $ticket = ownTicket();

    $this->getJson("/api/v1/support-ticket/{$ticket->id}")
        ->assertOk()
        ->assertJsonPath('data.subject', 'Printer will not cut');
});

it('refuses to reply to another store’s ticket', function () {
    actingAsUserWith(['support.view']);

    $ticket = foreignTicket();

    $this->postJson("/api/v1/support-ticket/{$ticket->id}/reply", ['body' => 'Prying'])
        ->assertForbidden();

    expect(SupportTicketReply::count())->toBe(0);
});

it('refuses the listing to a tindera', function () {
    actingAsUserWith(['sales.create', 'inventory.view']);

    $this->getJson('/api/v1/support-tickets')->assertForbidden();
});

it('lets the operator move status but not the owner', function () {
    actingAsUserWith(['support.view', 'support.update']);
    $ticket = ownTicket();

    $this->patchJson("/api/v1/support-ticket/{$ticket->id}", ['status' => 'resolved'])
        ->assertForbidden();

    actingAsUserWith(['support.view', 'support.update', 'stores.view']);

    $this->patchJson("/api/v1/support-ticket/{$ticket->id}", ['status' => 'resolved'])
        ->assertOk()
        ->assertJsonPath('data.status', 'resolved');

    expect($ticket->fresh()->resolved_at)->not->toBeNull();
});

it('clears the resolved timestamp when a ticket is reopened', function () {
    actingAsUserWith(['support.view', 'support.update', 'stores.view']);

    $ticket = ownTicket();
    $this->patchJson("/api/v1/support-ticket/{$ticket->id}", ['status' => 'resolved'])->assertOk();
    $this->patchJson("/api/v1/support-ticket/{$ticket->id}", ['status' => 'in_progress'])->assertOk();

    expect($ticket->fresh()->resolved_at)->toBeNull();
});

/** The customer's own words are not the operator's to edit. */
it('never lets the subject or body be updated', function () {
    actingAsUserWith(['support.view', 'support.update', 'stores.view']);
    $ticket = ownTicket();

    $this->patchJson("/api/v1/support-ticket/{$ticket->id}", [
        'subject' => 'Rewritten',
        'body' => 'Rewritten',
        'status' => 'in_progress',
    ])->assertOk();

    expect($ticket->fresh()->subject)->toBe('Printer will not cut')
        ->and($ticket->fresh()->body)->toBe('The receipt tears instead.');
});

it('stamps the reply time and picks the ticket up', function () {
    actingAsUserWith(['support.view', 'stores.view']);
    $ticket = ownTicket();

    expect($ticket->status)->toBe(SupportTicketStatusEnum::OPEN)
        ->and($ticket->last_replied_at)->toBeNull();

    $this->postJson("/api/v1/support-ticket/{$ticket->id}/reply", ['body' => 'Looking into it.'])
        ->assertCreated();

    expect($ticket->fresh()->status)->toBe(SupportTicketStatusEnum::IN_PROGRESS)
        ->and($ticket->fresh()->last_replied_at)->not->toBeNull();
});

it('returns the thread with the ticket', function () {
    actingAsUserWith(['support.view', 'stores.view']);
    $ticket = ownTicket();

    $this->postJson("/api/v1/support-ticket/{$ticket->id}/reply", ['body' => 'First'])->assertCreated();
    $this->postJson("/api/v1/support-ticket/{$ticket->id}/reply", ['body' => 'Second'])->assertCreated();

    $this->getJson("/api/v1/support-ticket/{$ticket->id}")
        ->assertOk()
        ->assertJsonCount(2, 'data.replies')
        ->assertJsonPath('data.replies.0.body', 'First');
});

it('grants support to the owner role and withholds it from the tindera', function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(SystemRoleSeeder::class);

    $owner = Role::where('slug', 'owner')->with('permissions')->first();
    $tindera = Role::where('slug', 'tindera')->with('permissions')->first();

    expect($owner->permissions->pluck('name'))->toContain('support.create', 'support.view', 'support.update')
        ->and($tindera->permissions->pluck('name'))->not->toContain('support.view')
        // Closing is a status; a ticket is never erased.
        ->and(Permission::where('name', 'support.delete')->exists())->toBeFalse();
});
