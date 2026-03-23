<?php

declare(strict_types=1);

use App\Actions\ProcessMeetingPayouts;
use App\Enums\MeetingStatus;
use App\Enums\StaffRank;
use App\Models\Meeting;
use App\Models\MeetingPayout;
use App\Models\MeetingReport;
use App\Models\MinecraftAccount;
use App\Models\SiteConfig;
use App\Models\User;
use App\Services\MinecraftRconService;

uses()->group('meetings', 'actions');

function makeMeeting(): Meeting
{
    return Meeting::factory()->withStatus(MeetingStatus::Finalizing)->create();
}

function addAttendee(Meeting $meeting, User $user, bool $attended = true): void
{
    $meeting->attendees()->syncWithoutDetaching([
        $user->id => ['added_at' => now(), 'attended' => $attended],
    ]);
}

function submitReport(Meeting $meeting, User $user): void
{
    MeetingReport::create([
        'meeting_id' => $meeting->id,
        'user_id' => $user->id,
        'submitted_at' => now(),
    ]);
}

function withPrimaryMcAccount(User $user): MinecraftAccount
{
    return MinecraftAccount::factory()->active()->primary()->create(['user_id' => $user->id]);
}

function mockRcon(): void
{
    test()->mock(MinecraftRconService::class)
        ->shouldReceive('executeCommand')
        ->andReturn(['success' => true, 'response' => null, 'error' => null]);
}

beforeEach(function () {
    SiteConfig::setValue('meeting_payout_jr_crew', '50');
    SiteConfig::setValue('meeting_payout_crew_member', '75');
    SiteConfig::setValue('meeting_payout_officer', '100');
});

// --- Eligibility: Jr Crew ---

it('pays a Jr Crew member who submitted the form', function () {
    mockRcon();
    $meeting = makeMeeting();
    $user = User::factory()->create(['staff_rank' => StaffRank::JrCrew]);
    withPrimaryMcAccount($user);
    addAttendee($meeting, $user);
    submitReport($meeting, $user);

    ProcessMeetingPayouts::run($meeting);

    $payout = MeetingPayout::where('meeting_id', $meeting->id)->where('user_id', $user->id)->first();
    expect($payout)->not->toBeNull()
        ->and($payout->status)->toBe('paid')
        ->and($payout->amount)->toBe(50);
});

it('skips a Jr Crew member who did not submit the form', function () {
    $meeting = makeMeeting();
    $user = User::factory()->create(['staff_rank' => StaffRank::JrCrew]);
    withPrimaryMcAccount($user);
    addAttendee($meeting, $user);
    // No report submitted

    ProcessMeetingPayouts::run($meeting);

    $payout = MeetingPayout::where('meeting_id', $meeting->id)->where('user_id', $user->id)->first();
    expect($payout->status)->toBe('skipped')
        ->and($payout->skip_reason)->toBe('Form not submitted');
});

// --- Eligibility: Crew Member ---

it('pays a Crew Member who submitted the form', function () {
    mockRcon();
    $meeting = makeMeeting();
    $user = User::factory()->create(['staff_rank' => StaffRank::CrewMember]);
    withPrimaryMcAccount($user);
    addAttendee($meeting, $user);
    submitReport($meeting, $user);

    ProcessMeetingPayouts::run($meeting);

    $payout = MeetingPayout::where('meeting_id', $meeting->id)->where('user_id', $user->id)->first();
    expect($payout->status)->toBe('paid')
        ->and($payout->amount)->toBe(75);
});

it('skips a Crew Member who did not submit the form', function () {
    $meeting = makeMeeting();
    $user = User::factory()->create(['staff_rank' => StaffRank::CrewMember]);
    withPrimaryMcAccount($user);
    addAttendee($meeting, $user);

    ProcessMeetingPayouts::run($meeting);

    $payout = MeetingPayout::where('meeting_id', $meeting->id)->where('user_id', $user->id)->first();
    expect($payout->status)->toBe('skipped')
        ->and($payout->skip_reason)->toBe('Form not submitted');
});

// --- Eligibility: Officer ---

it('pays an Officer who submitted the form and attended', function () {
    mockRcon();
    $meeting = makeMeeting();
    $user = User::factory()->create(['staff_rank' => StaffRank::Officer]);
    withPrimaryMcAccount($user);
    addAttendee($meeting, $user, attended: true);
    submitReport($meeting, $user);

    ProcessMeetingPayouts::run($meeting);

    $payout = MeetingPayout::where('meeting_id', $meeting->id)->where('user_id', $user->id)->first();
    expect($payout->status)->toBe('paid')
        ->and($payout->amount)->toBe(100);
});

it('skips an Officer who submitted the form but did not attend', function () {
    $meeting = makeMeeting();
    $user = User::factory()->create(['staff_rank' => StaffRank::Officer]);
    withPrimaryMcAccount($user);
    addAttendee($meeting, $user, attended: false);
    submitReport($meeting, $user);

    ProcessMeetingPayouts::run($meeting);

    $payout = MeetingPayout::where('meeting_id', $meeting->id)->where('user_id', $user->id)->first();
    expect($payout->status)->toBe('skipped')
        ->and($payout->skip_reason)->toBe('Did not attend');
});

it('skips an Officer who attended but did not submit the form', function () {
    $meeting = makeMeeting();
    $user = User::factory()->create(['staff_rank' => StaffRank::Officer]);
    withPrimaryMcAccount($user);
    addAttendee($meeting, $user, attended: true);
    // No report submitted

    ProcessMeetingPayouts::run($meeting);

    $payout = MeetingPayout::where('meeting_id', $meeting->id)->where('user_id', $user->id)->first();
    expect($payout->status)->toBe('skipped')
        ->and($payout->skip_reason)->toBe('Form not submitted');
});

// --- Special skip conditions ---

it('skips a user with no primary Minecraft account', function () {
    $meeting = makeMeeting();
    $user = User::factory()->create(['staff_rank' => StaffRank::CrewMember]);
    // No MC account created
    addAttendee($meeting, $user);
    submitReport($meeting, $user);

    ProcessMeetingPayouts::run($meeting);

    $payout = MeetingPayout::where('meeting_id', $meeting->id)->where('user_id', $user->id)->first();
    expect($payout->status)->toBe('skipped')
        ->and($payout->skip_reason)->toBe('No Minecraft account');
});

it('skips a user when rank payout is set to 0', function () {
    SiteConfig::setValue('meeting_payout_crew_member', '0');

    $meeting = makeMeeting();
    $user = User::factory()->create(['staff_rank' => StaffRank::CrewMember]);
    withPrimaryMcAccount($user);
    addAttendee($meeting, $user);
    submitReport($meeting, $user);

    ProcessMeetingPayouts::run($meeting);

    $payout = MeetingPayout::where('meeting_id', $meeting->id)->where('user_id', $user->id)->first();
    expect($payout->status)->toBe('skipped')
        ->and($payout->skip_reason)->toBe('Rank payout disabled');
});

it('skips a user in the excluded list', function () {
    $meeting = makeMeeting();
    $user = User::factory()->create(['staff_rank' => StaffRank::CrewMember]);
    withPrimaryMcAccount($user);
    addAttendee($meeting, $user);
    submitReport($meeting, $user);

    ProcessMeetingPayouts::run($meeting, [$user->id]);

    $payout = MeetingPayout::where('meeting_id', $meeting->id)->where('user_id', $user->id)->first();
    expect($payout->status)->toBe('skipped')
        ->and($payout->skip_reason)->toBe('Excluded by manager');
});

// --- RCON failure ---

it('marks payout as failed when RCON returns failure, but does not block completion', function () {
    $meeting = makeMeeting();
    $user = User::factory()->create(['staff_rank' => StaffRank::CrewMember]);
    withPrimaryMcAccount($user);
    addAttendee($meeting, $user);
    submitReport($meeting, $user);

    test()->mock(MinecraftRconService::class)
        ->shouldReceive('executeCommand')
        ->andReturn(['success' => false, 'response' => null, 'error' => 'RCON error']);

    // Should not throw
    ProcessMeetingPayouts::run($meeting);

    $payout = MeetingPayout::where('meeting_id', $meeting->id)->where('user_id', $user->id)->first();
    expect($payout->status)->toBe('failed');
});

// --- Duplicate prevention ---

it('does not create duplicate payout records when called twice', function () {
    mockRcon();
    $meeting = makeMeeting();
    $user = User::factory()->create(['staff_rank' => StaffRank::CrewMember]);
    withPrimaryMcAccount($user);
    addAttendee($meeting, $user);
    submitReport($meeting, $user);

    ProcessMeetingPayouts::run($meeting);
    ProcessMeetingPayouts::run($meeting);

    $count = MeetingPayout::where('meeting_id', $meeting->id)->where('user_id', $user->id)->count();
    expect($count)->toBe(1);
});

// --- Activity log ---

it('records activity with correct counts', function () {
    mockRcon();
    $meeting = makeMeeting();

    $paid = User::factory()->create(['staff_rank' => StaffRank::CrewMember]);
    withPrimaryMcAccount($paid);
    addAttendee($meeting, $paid);
    submitReport($meeting, $paid);

    $skipped = User::factory()->create(['staff_rank' => StaffRank::CrewMember]);
    withPrimaryMcAccount($skipped);
    addAttendee($meeting, $skipped);
    // no form submission

    ProcessMeetingPayouts::run($meeting);

    $this->assertDatabaseHas('activity_logs', [
        'subject_type' => Meeting::class,
        'subject_id' => $meeting->id,
        'action' => 'meeting_payouts_processed',
        'description' => 'Meeting payouts: 1 paid, 1 skipped, 0 failed.',
    ]);
});
