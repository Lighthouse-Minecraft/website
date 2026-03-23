<?php

declare(strict_types=1);

use App\Enums\MeetingStatus;
use App\Enums\StaffRank;
use App\Models\Meeting;
use App\Models\MeetingReport;
use App\Models\MinecraftAccount;
use App\Models\SiteConfig;
use App\Models\User;
use Livewire\Volt\Volt;

uses()->group('meetings', 'livewire', 'payout-preview');

beforeEach(function () {
    SiteConfig::setValue('meeting_payout_jr_crew', '50');
    SiteConfig::setValue('meeting_payout_crew_member', '75');
    SiteConfig::setValue('meeting_payout_officer', '100');
});

function makeFinalizingMeeting(): Meeting
{
    return Meeting::factory()->withStatus(MeetingStatus::Finalizing)->create();
}

function addMeetingAttendee(Meeting $meeting, User $user, bool $attended = true): void
{
    $meeting->attendees()->syncWithoutDetaching([
        $user->id => ['added_at' => now(), 'attended' => $attended],
    ]);
}

function submitMeetingReport(Meeting $meeting, User $user): void
{
    MeetingReport::create([
        'meeting_id' => $meeting->id,
        'user_id' => $user->id,
        'submitted_at' => now(),
    ]);
}

function withMcAccount(User $user): MinecraftAccount
{
    return MinecraftAccount::factory()->active()->primary()->create(['user_id' => $user->id]);
}

function meetingManager(): User
{
    return User::factory()->withRole('Meeting - Manager')->create();
}

// --- Visibility ---

it('renders the payout preview during finalizing for a staff meeting', function () {
    $manager = meetingManager();
    loginAs($manager);

    $meeting = makeFinalizingMeeting();

    Volt::test('meeting.payout-preview', ['meeting' => $meeting])
        ->assertSee('Payout Preview');
});

it('hides the preview when all rank payout amounts are zero', function () {
    SiteConfig::setValue('meeting_payout_jr_crew', '0');
    SiteConfig::setValue('meeting_payout_crew_member', '0');
    SiteConfig::setValue('meeting_payout_officer', '0');

    $manager = meetingManager();
    loginAs($manager);

    $meeting = makeFinalizingMeeting();

    Volt::test('meeting.payout-preview', ['meeting' => $meeting])
        ->assertDontSee('Payout Preview');
});

// --- Eligible users ---

it('shows an eligible user with payout amount', function () {
    $manager = meetingManager();
    loginAs($manager);

    $meeting = makeFinalizingMeeting();
    $user = User::factory()->create(['name' => 'Eligible Crew', 'staff_rank' => StaffRank::CrewMember]);
    withMcAccount($user);
    addMeetingAttendee($meeting, $user);
    submitMeetingReport($meeting, $user);

    Volt::test('meeting.payout-preview', ['meeting' => $meeting])
        ->assertSee('Eligible Crew')
        ->assertSee('75');
});

// --- Ineligible users ---

it('shows ineligible user grayed out with skip reason for missing form', function () {
    $manager = meetingManager();
    loginAs($manager);

    $meeting = makeFinalizingMeeting();
    $user = User::factory()->create(['name' => 'No Form User', 'staff_rank' => StaffRank::JrCrew]);
    withMcAccount($user);
    addMeetingAttendee($meeting, $user);
    // No report submitted

    Volt::test('meeting.payout-preview', ['meeting' => $meeting])
        ->assertSee('No Form User')
        ->assertSee('Form not submitted');
});

it('shows ineligible Officer grayed out when did not attend', function () {
    $manager = meetingManager();
    loginAs($manager);

    $meeting = makeFinalizingMeeting();
    $user = User::factory()->create(['name' => 'Absent Officer', 'staff_rank' => StaffRank::Officer]);
    withMcAccount($user);
    addMeetingAttendee($meeting, $user, attended: false);
    submitMeetingReport($meeting, $user);

    Volt::test('meeting.payout-preview', ['meeting' => $meeting])
        ->assertSee('Absent Officer')
        ->assertSee('Did not attend');
});

it('shows ineligible user when no Minecraft account', function () {
    $manager = meetingManager();
    loginAs($manager);

    $meeting = makeFinalizingMeeting();
    $user = User::factory()->create(['name' => 'No MC User', 'staff_rank' => StaffRank::CrewMember]);
    // No MC account
    addMeetingAttendee($meeting, $user);
    submitMeetingReport($meeting, $user);

    Volt::test('meeting.payout-preview', ['meeting' => $meeting])
        ->assertSee('No MC User')
        ->assertSee('No Minecraft account');
});

// --- Toggle / Excluded list ---

it('toggling an eligible user off adds them to the excluded list', function () {
    $manager = meetingManager();
    loginAs($manager);

    $meeting = makeFinalizingMeeting();
    $user = User::factory()->create(['staff_rank' => StaffRank::CrewMember]);
    withMcAccount($user);
    addMeetingAttendee($meeting, $user);
    submitMeetingReport($meeting, $user);

    Volt::test('meeting.payout-preview', ['meeting' => $meeting])
        ->call('toggleExclude', $user->id)
        ->assertSet('excludedUserIds', [$user->id]);
});

it('toggling an excluded user back on removes them from the excluded list', function () {
    $manager = meetingManager();
    loginAs($manager);

    $meeting = makeFinalizingMeeting();
    $user = User::factory()->create(['staff_rank' => StaffRank::CrewMember]);
    withMcAccount($user);
    addMeetingAttendee($meeting, $user);
    submitMeetingReport($meeting, $user);

    Volt::test('meeting.payout-preview', ['meeting' => $meeting])
        ->call('toggleExclude', $user->id)
        ->assertSet('excludedUserIds', [$user->id])
        ->call('toggleExclude', $user->id)
        ->assertSet('excludedUserIds', []);
});

it('dispatches payoutExcludedUsersChanged event when toggle changes', function () {
    $manager = meetingManager();
    loginAs($manager);

    $meeting = makeFinalizingMeeting();
    $user = User::factory()->create(['staff_rank' => StaffRank::JrCrew]);
    withMcAccount($user);
    addMeetingAttendee($meeting, $user);
    submitMeetingReport($meeting, $user);

    Volt::test('meeting.payout-preview', ['meeting' => $meeting])
        ->call('toggleExclude', $user->id)
        ->assertDispatched('payoutExcludedUsersChanged');
});

it('denies toggle to non-manager user', function () {
    $regular = User::factory()->create();
    loginAs($regular);

    $meeting = makeFinalizingMeeting();
    $user = User::factory()->create(['staff_rank' => StaffRank::JrCrew]);
    withMcAccount($user);
    addMeetingAttendee($meeting, $user);
    submitMeetingReport($meeting, $user);

    Volt::test('meeting.payout-preview', ['meeting' => $meeting])
        ->call('toggleExclude', $user->id)
        ->assertForbidden();
});
