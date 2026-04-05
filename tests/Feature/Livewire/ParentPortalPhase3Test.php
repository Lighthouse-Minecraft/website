<?php

declare(strict_types=1);

use App\Enums\MinecraftAccountStatus;
use App\Enums\ThreadStatus;
use App\Enums\ThreadSubtype;
use App\Enums\ThreadType;
use App\Models\MinecraftAccount;
use App\Models\ParentChildLink;
use App\Models\Thread;
use App\Models\ThreadParticipant;
use App\Models\User;

use function Pest\Laravel\actingAs;

uses()->group('parent-portal');

it('links child name to profile page', function () {
    $parent = User::factory()->adult()->create();
    $child = User::factory()->minor()->create(['name' => 'Linked Child']);
    ParentChildLink::create(['parent_user_id' => $parent->id, 'child_user_id' => $child->id]);
    actingAs($parent);

    Livewire\Volt\Volt::test('parent-portal.index')
        ->assertSeeHtml(route('profile.show', $child))
        ->assertSee('Linked Child');
});

it('links ticket subjects to ticket view page', function () {
    $parent = User::factory()->adult()->create();
    $child = User::factory()->minor()->create();
    ParentChildLink::create(['parent_user_id' => $parent->id, 'child_user_id' => $child->id]);

    $ticket = Thread::factory()->create([
        'created_by_user_id' => $child->id,
        'type' => ThreadType::Ticket,
        'status' => ThreadStatus::Open,
        'subject' => 'Test Ticket Subject',
    ]);

    actingAs($parent);

    Livewire\Volt\Volt::test('parent-portal.index')
        ->assertSee('Test Ticket Subject')
        ->assertSeeHtml(route('tickets.show', $ticket));
});

it('allows parent to view child ticket via policy', function () {
    $parent = User::factory()->adult()->create();
    $child = User::factory()->minor()->create();
    ParentChildLink::create(['parent_user_id' => $parent->id, 'child_user_id' => $child->id]);

    $ticket = Thread::factory()->create([
        'created_by_user_id' => $child->id,
        'type' => ThreadType::Ticket,
        'status' => ThreadStatus::Open,
    ]);

    expect($ticket->isVisibleTo($parent))->toBeTrue();
});

it('blocks parent from viewing non-child ticket', function () {
    $parent = User::factory()->adult()->create();
    $child = User::factory()->minor()->create();
    ParentChildLink::create(['parent_user_id' => $parent->id, 'child_user_id' => $child->id]);

    $stranger = User::factory()->create();
    $ticket = Thread::factory()->create([
        'created_by_user_id' => $stranger->id,
        'type' => ThreadType::Ticket,
        'status' => ThreadStatus::Open,
    ]);

    expect($ticket->isVisibleTo($parent))->toBeFalse();
});

it('blocks parent from viewing staff threads involving child', function () {
    $parent = User::factory()->adult()->create();
    $child = User::factory()->minor()->create();
    ParentChildLink::create(['parent_user_id' => $parent->id, 'child_user_id' => $child->id]);

    $dmThread = Thread::factory()->create([
        'created_by_user_id' => $child->id,
        'type' => ThreadType::DirectMessage,
        'status' => ThreadStatus::Open,
    ]);

    expect($dmThread->isVisibleTo($parent))->toBeFalse();
});

it('parent can see admin ticket where child is a participant', function () {
    $parent = User::factory()->adult()->create();
    $child = User::factory()->minor()->create();
    $staff = User::factory()->withRole('Financials - View')->create();
    ParentChildLink::create(['parent_user_id' => $parent->id, 'child_user_id' => $child->id]);

    // Admin ticket created by staff, child added as participant
    $ticket = Thread::factory()->create([
        'created_by_user_id' => $staff->id,
        'type' => ThreadType::Ticket,
        'subtype' => ThreadSubtype::AdminAction,
        'status' => ThreadStatus::Open,
        'subject' => 'Admin Action Ticket',
    ]);
    ThreadParticipant::create(['thread_id' => $ticket->id, 'user_id' => $child->id]);

    actingAs($parent);

    Livewire\Volt\Volt::test('parent-portal.index')
        ->assertSee('Admin Action Ticket');
});

it('blocks MC removal for non-child account', function () {
    $parent = User::factory()->adult()->create();
    $stranger = User::factory()->create();
    $account = MinecraftAccount::factory()->create([
        'user_id' => $stranger->id,
        'status' => MinecraftAccountStatus::Active,
    ]);

    actingAs($parent);

    Livewire\Volt\Volt::test('parent-portal.index')
        ->call('removeChildMcAccount', $account->id);

    expect($account->fresh()->status)->toBe(MinecraftAccountStatus::Active);
});

it('blocks cancelled MC removal for non-child account', function () {
    $parent = User::factory()->adult()->create();
    $stranger = User::factory()->create();
    $account = MinecraftAccount::factory()->cancelled()->create([
        'user_id' => $stranger->id,
    ]);

    actingAs($parent);

    Livewire\Volt\Volt::test('parent-portal.index')
        ->call('removeChildCancelledMcAccount', $account->id);

    $this->assertDatabaseHas('minecraft_accounts', ['id' => $account->id]);
});
