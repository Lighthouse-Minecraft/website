<?php

declare(strict_types=1);

use App\Actions\CreateDefaultMeetingQuestions;
use App\Enums\MeetingStatus;
use App\Enums\MeetingType;
use App\Enums\StaffRank;
use App\Models\Meeting;
use App\Models\User;
use Livewire\Volt\Volt;

it('creates default questions when staff meeting is created', function () {
    $meeting = Meeting::factory()->create(['type' => MeetingType::StaffMeeting]);

    CreateDefaultMeetingQuestions::run($meeting);

    expect($meeting->questions)->toHaveCount(4);
    expect($meeting->questions->pluck('question_text')->contains(fn ($t) => str_contains($t, 'accomplish')))->toBeTrue();
});

it('does not create questions for non-staff meetings', function () {
    $meeting = Meeting::factory()->create(['type' => MeetingType::BoardMeeting]);

    CreateDefaultMeetingQuestions::run($meeting);

    expect($meeting->questions()->count())->toBe(0);
});

it('does not duplicate questions if already exist', function () {
    $meeting = Meeting::factory()->create(['type' => MeetingType::StaffMeeting]);

    CreateDefaultMeetingQuestions::run($meeting);
    CreateDefaultMeetingQuestions::run($meeting);

    expect($meeting->questions()->count())->toBe(4);
});

it('allows adding questions while meeting is pending', function () {
    $user = User::factory()->create(['staff_rank' => StaffRank::Officer]);
    $meeting = Meeting::factory()->create([
        'type' => MeetingType::StaffMeeting,
        'status' => MeetingStatus::Pending,
    ]);

    Volt::actingAs($user)
        ->test('meeting.manage-questions', ['meeting' => $meeting])
        ->set('newQuestion', 'What blockers do you have?')
        ->call('addQuestion')
        ->assertHasNoErrors();

    expect($meeting->questions()->count())->toBe(1);
    expect($meeting->questions->first()->question_text)->toBe('What blockers do you have?');
});

it('allows removing questions', function () {
    $user = User::factory()->create(['staff_rank' => StaffRank::Officer]);
    $meeting = Meeting::factory()->create([
        'type' => MeetingType::StaffMeeting,
        'status' => MeetingStatus::Pending,
    ]);

    CreateDefaultMeetingQuestions::run($meeting);
    $questionId = $meeting->questions()->first()->id;

    Volt::actingAs($user)
        ->test('meeting.manage-questions', ['meeting' => $meeting])
        ->call('removeQuestion', $questionId)
        ->assertHasNoErrors();

    expect($meeting->questions()->count())->toBe(3);
});
