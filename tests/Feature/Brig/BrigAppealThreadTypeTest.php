<?php

declare(strict_types=1);

use App\Enums\BrigType;
use App\Enums\ThreadType;
use App\Models\StaffPosition;
use App\Models\Thread;
use App\Models\User;
use Livewire\Volt\Volt;

use function Pest\Laravel\actingAs;

uses()->group('brig', 'appeals', 'thread-type');

// ─── ThreadType enum ──────────────────────────────────────────────────────────

it('ThreadType::BrigAppeal has the correct value', function () {
    expect(ThreadType::BrigAppeal->value)->toBe('brig_appeal');
});

it('ThreadType::BrigAppeal has a label', function () {
    expect(ThreadType::BrigAppeal->label())->toBe('Brig Appeal');
});

// ─── Appeal creates correct thread type ──────────────────────────────────────

it('submitting a disciplinary appeal creates a BrigAppeal thread', function () {
    $user = User::factory()->create([
        'in_brig' => true,
        'brig_reason' => 'Rule violation',
        'brig_type' => BrigType::Discipline,
        'next_appeal_available_at' => null,
    ]);

    actingAs($user);

    Volt::test('dashboard.in-brig-card')
        ->set('appealMessage', 'I would like to appeal this decision because I believe it was unfair.')
        ->call('submitAppeal')
        ->assertHasNoErrors();

    $thread = Thread::where('created_by_user_id', $user->id)->latest()->first();
    expect($thread)->not->toBeNull()
        ->and($thread->type)->toBe(ThreadType::BrigAppeal);
});

it('submitting a parental contact creates a Topic thread, not BrigAppeal', function () {
    $user = User::factory()->create([
        'in_brig' => true,
        'brig_type' => BrigType::ParentalPending,
        'next_appeal_available_at' => null,
    ]);

    actingAs($user);

    Volt::test('dashboard.in-brig-card')
        ->set('appealMessage', 'I need help with my account access please contact me.')
        ->call('submitAppeal')
        ->assertHasNoErrors();

    $thread = Thread::where('created_by_user_id', $user->id)->latest()->first();
    expect($thread)->not->toBeNull()
        ->and($thread->type)->toBe(ThreadType::Topic);
});

// ─── Correct participants are added ──────────────────────────────────────────

it('Brig Warden is added as participant when appeal is submitted', function () {
    $warden = User::factory()->withRole('Brig Warden')->create();
    $brigged = User::factory()->create([
        'in_brig' => true,
        'brig_reason' => 'Test',
        'brig_type' => BrigType::Discipline,
        'next_appeal_available_at' => null,
    ]);

    actingAs($brigged);

    Volt::test('dashboard.in-brig-card')
        ->set('appealMessage', 'I would like to appeal this decision because I believe it was unfair.')
        ->call('submitAppeal')
        ->assertHasNoErrors();

    $thread = Thread::where('created_by_user_id', $brigged->id)->latest()->first();
    expect($thread->participants()->where('user_id', $warden->id)->exists())->toBeTrue();
});

it('Admin is added as participant when appeal is submitted', function () {
    $admin = User::factory()->admin()->create();
    $brigged = User::factory()->create([
        'in_brig' => true,
        'brig_reason' => 'Test',
        'brig_type' => BrigType::Discipline,
        'next_appeal_available_at' => null,
    ]);

    actingAs($brigged);

    Volt::test('dashboard.in-brig-card')
        ->set('appealMessage', 'I would like to appeal this decision because I believe it was unfair.')
        ->call('submitAppeal')
        ->assertHasNoErrors();

    $thread = Thread::where('created_by_user_id', $brigged->id)->latest()->first();
    expect($thread->participants()->where('user_id', $admin->id)->exists())->toBeTrue();
});

it('All-roles staff is added as participant when appeal is submitted', function () {
    $allRolesUser = User::factory()->create();
    $position = StaffPosition::factory()->assignedTo($allRolesUser->id)->create([
        'has_all_roles_at' => now(),
    ]);
    $allRolesUser->unsetRelation('staffPosition');

    $brigged = User::factory()->create([
        'in_brig' => true,
        'brig_reason' => 'Test',
        'brig_type' => BrigType::Discipline,
        'next_appeal_available_at' => null,
    ]);

    actingAs($brigged);

    Volt::test('dashboard.in-brig-card')
        ->set('appealMessage', 'I would like to appeal this decision because I believe it was unfair.')
        ->call('submitAppeal')
        ->assertHasNoErrors();

    $thread = Thread::where('created_by_user_id', $brigged->id)->latest()->first();
    expect($thread->participants()->where('user_id', $allRolesUser->id)->exists())->toBeTrue();
});

// ─── BrigAppeal excluded from topics list ────────────────────────────────────

it('BrigAppeal threads do not appear in the topics list', function () {
    $user = User::factory()->withStaffPosition(\App\Enums\StaffDepartment::Quartermaster, \App\Enums\StaffRank::CrewMember)->create();

    $brigAppealThread = Thread::factory()->brigAppeal()->create(['subject' => 'Unique Brig Appeal Subject XYZ']);
    $brigAppealThread->addParticipant($user);

    actingAs($user);

    Volt::test('topics.topics-list')
        ->assertDontSee('Unique Brig Appeal Subject XYZ');
});

it('Topic threads continue to appear in the topics list', function () {
    $user = User::factory()->withStaffPosition(\App\Enums\StaffDepartment::Quartermaster, \App\Enums\StaffRank::CrewMember)->create();

    $topicThread = Thread::factory()->topic()->create(['subject' => 'Unique Topic Subject ABC']);
    $topicThread->addParticipant($user);

    actingAs($user);

    Volt::test('topics.topics-list')
        ->assertSee('Unique Topic Subject ABC');
});

// ─── view-topic accepts BrigAppeal threads ───────────────────────────────────

it('view-topic renders BrigAppeal threads for participants', function () {
    $user = User::factory()->create();
    $thread = Thread::factory()->brigAppeal()->create(['subject' => 'Brig Appeal: TestUser']);
    $thread->addParticipant($user);

    actingAs($user);

    Volt::test('topics.view-topic', ['thread' => $thread])
        ->assertSee('Brig Appeal: TestUser');
});

it('view-topic returns 404 for BrigAppeal threads without matching participant', function () {
    $user = User::factory()->create();
    $thread = Thread::factory()->brigAppeal()->create();

    actingAs($user);

    Volt::test('topics.view-topic', ['thread' => $thread])
        ->assertForbidden();
});
