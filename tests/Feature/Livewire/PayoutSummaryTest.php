<?php

declare(strict_types=1);

use App\Enums\MeetingStatus;
use App\Models\Meeting;
use App\Models\MeetingPayout;
use App\Models\User;
use Livewire\Volt\Volt;

uses()->group('meetings', 'livewire', 'payout-summary');

function makeCompletedMeeting(): Meeting
{
    return Meeting::factory()->withStatus(MeetingStatus::Completed)->create();
}

function addPayout(Meeting $meeting, User $user, string $status, int $amount = 0, ?string $reason = null): MeetingPayout
{
    return MeetingPayout::create([
        'meeting_id' => $meeting->id,
        'user_id' => $user->id,
        'amount' => $amount,
        'status' => $status,
        'skip_reason' => $reason,
    ]);
}

// --- Visibility ---

it('denies access to non-staff users', function () {
    $nonStaff = User::factory()->create(); // no 'Staff Access' role
    loginAs($nonStaff);

    $meeting = makeCompletedMeeting();
    $user = User::factory()->create(['name' => 'Paid Staff']);
    addPayout($meeting, $user, 'paid', 75);

    Volt::test('meeting.payout-summary', ['meeting' => $meeting])
        ->assertForbidden();
});

it('renders payout summary when payout records exist', function () {
    $staff = User::factory()->withRole('Staff Access')->create();
    loginAs($staff);

    $meeting = makeCompletedMeeting();
    $user = User::factory()->create(['name' => 'Paid Staff']);
    addPayout($meeting, $user, 'paid', 75);

    Volt::test('meeting.payout-summary', ['meeting' => $meeting])
        ->assertSee('Payout Summary')
        ->assertSee('Paid Staff');
});

it('does not render when there are no payout records', function () {
    $staff = User::factory()->withRole('Staff Access')->create();
    loginAs($staff);

    $meeting = makeCompletedMeeting();

    Volt::test('meeting.payout-summary', ['meeting' => $meeting])
        ->assertDontSee('Payout Summary');
});

// --- Summary counts ---

it('shows correct paid count and total lumens', function () {
    $staff = User::factory()->withRole('Staff Access')->create();
    loginAs($staff);

    $meeting = makeCompletedMeeting();
    $userA = User::factory()->create();
    $userB = User::factory()->create();
    addPayout($meeting, $userA, 'paid', 75);
    addPayout($meeting, $userB, 'paid', 100);

    Volt::test('meeting.payout-summary', ['meeting' => $meeting])
        ->assertSee('2 paid')
        ->assertSee('175');
});

it('shows correct skipped count', function () {
    $staff = User::factory()->withRole('Staff Access')->create();
    loginAs($staff);

    $meeting = makeCompletedMeeting();
    $userA = User::factory()->create();
    $userB = User::factory()->create();
    addPayout($meeting, $userA, 'paid', 50);
    addPayout($meeting, $userB, 'skipped', 0, 'Form not submitted');

    Volt::test('meeting.payout-summary', ['meeting' => $meeting])
        ->assertSee('1 skipped');
});

it('shows correct failed count', function () {
    $staff = User::factory()->withRole('Staff Access')->create();
    loginAs($staff);

    $meeting = makeCompletedMeeting();
    $userA = User::factory()->create();
    addPayout($meeting, $userA, 'failed', 75);

    Volt::test('meeting.payout-summary', ['meeting' => $meeting])
        ->assertSee('1 failed');
});

// --- Detail table ---

it('shows paid status badge and amount for paid user', function () {
    $staff = User::factory()->withRole('Staff Access')->create();
    loginAs($staff);

    $meeting = makeCompletedMeeting();
    $user = User::factory()->create(['name' => 'Paid Person']);
    addPayout($meeting, $user, 'paid', 75);

    Volt::test('meeting.payout-summary', ['meeting' => $meeting])
        ->assertSee('Paid Person')
        ->assertSee('75')
        ->assertSee('Paid');
});

it('shows skipped status and reason for skipped user', function () {
    $staff = User::factory()->withRole('Staff Access')->create();
    loginAs($staff);

    $meeting = makeCompletedMeeting();
    $user = User::factory()->create(['name' => 'Skipped Person']);
    addPayout($meeting, $user, 'skipped', 0, 'Form not submitted');

    Volt::test('meeting.payout-summary', ['meeting' => $meeting])
        ->assertSee('Skipped Person')
        ->assertSee('Skipped')
        ->assertSee('Form not submitted');
});

it('shows failed status for failed user', function () {
    $staff = User::factory()->withRole('Staff Access')->create();
    loginAs($staff);

    $meeting = makeCompletedMeeting();
    $user = User::factory()->create(['name' => 'Failed Person']);
    addPayout($meeting, $user, 'failed', 75);

    Volt::test('meeting.payout-summary', ['meeting' => $meeting])
        ->assertSee('Failed Person')
        ->assertSee('Failed');
});

it('shows pending status badge for interrupted payout', function () {
    $staff = User::factory()->withRole('Staff Access')->create();
    loginAs($staff);

    $meeting = makeCompletedMeeting();
    $user = User::factory()->create(['name' => 'Pending Person']);
    addPayout($meeting, $user, 'pending', 75);

    Volt::test('meeting.payout-summary', ['meeting' => $meeting])
        ->assertSee('Pending Person')
        ->assertSee('Pending')
        ->assertSee('pending (interrupted');
});

it('summary data persists from database across reloads', function () {
    $staff = User::factory()->withRole('Staff Access')->create();
    loginAs($staff);

    $meeting = makeCompletedMeeting();
    $user = User::factory()->create(['name' => 'Persistent User']);
    addPayout($meeting, $user, 'paid', 50);

    // First load
    Volt::test('meeting.payout-summary', ['meeting' => $meeting])
        ->assertSee('Persistent User');

    // Second fresh load — reads from DB, same result
    Volt::test('meeting.payout-summary', ['meeting' => $meeting])
        ->assertSee('Persistent User')
        ->assertSee('50');
});
