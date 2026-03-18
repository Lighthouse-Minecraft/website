<?php

declare(strict_types=1);

use App\Actions\SubmitApplication;
use App\Enums\ApplicationStatus;
use App\Models\ApplicationQuestion;
use App\Models\StaffApplication;
use App\Models\StaffPosition;
use App\Models\User;

uses()->group('applications', 'actions');

it('creates application with submitted status', function () {
    $user = User::factory()->create();
    $position = StaffPosition::factory()->officer()->create(['accepting_applications' => true]);
    $question = ApplicationQuestion::factory()->create();

    $application = SubmitApplication::run($user, $position, [
        $question->id => 'My answer',
    ]);

    expect($application)
        ->toBeInstanceOf(StaffApplication::class)
        ->status->toBe(ApplicationStatus::Submitted)
        ->user_id->toBe($user->id)
        ->staff_position_id->toBe($position->id);
});

it('creates answer records for each question', function () {
    $user = User::factory()->create();
    $position = StaffPosition::factory()->officer()->create(['accepting_applications' => true]);
    $q1 = ApplicationQuestion::factory()->create();
    $q2 = ApplicationQuestion::factory()->create();
    $q3 = ApplicationQuestion::factory()->create();

    $application = SubmitApplication::run($user, $position, [
        $q1->id => 'Answer one',
        $q2->id => 'Answer two',
        $q3->id => 'Answer three',
    ]);

    expect($application->answers)->toHaveCount(3);

    $this->assertDatabaseHas('staff_application_answers', [
        'staff_application_id' => $application->id,
        'application_question_id' => $q1->id,
        'answer' => 'Answer one',
    ]);

    $this->assertDatabaseHas('staff_application_answers', [
        'staff_application_id' => $application->id,
        'application_question_id' => $q2->id,
        'answer' => 'Answer two',
    ]);

    $this->assertDatabaseHas('staff_application_answers', [
        'staff_application_id' => $application->id,
        'application_question_id' => $q3->id,
        'answer' => 'Answer three',
    ]);
});

it('records activity log', function () {
    $user = User::factory()->create();
    $position = StaffPosition::factory()->officer()->create(['accepting_applications' => true]);
    $question = ApplicationQuestion::factory()->create();

    $application = SubmitApplication::run($user, $position, [
        $question->id => 'My answer',
    ]);

    $this->assertDatabaseHas('activity_logs', [
        'subject_type' => StaffApplication::class,
        'subject_id' => $application->id,
        'action' => 'application_submitted',
    ]);
});

it('rejects when position is not accepting applications', function () {
    $user = User::factory()->create();
    $position = StaffPosition::factory()->officer()->create(['accepting_applications' => false]);
    $question = ApplicationQuestion::factory()->create();

    SubmitApplication::run($user, $position, [
        $question->id => 'My answer',
    ]);
})->throws(RuntimeException::class, 'This position is not currently accepting applications.');

it('rejects when user already has pending application for position', function () {
    $user = User::factory()->create();
    $position = StaffPosition::factory()->officer()->create(['accepting_applications' => true]);
    $question = ApplicationQuestion::factory()->create();

    StaffApplication::factory()->create([
        'user_id' => $user->id,
        'staff_position_id' => $position->id,
        'status' => ApplicationStatus::Submitted,
    ]);

    SubmitApplication::run($user, $position, [
        $question->id => 'My answer',
    ]);
})->throws(RuntimeException::class, 'You already have a pending application for this position.');

it('allows submission when previous application was denied', function () {
    $user = User::factory()->create();
    $position = StaffPosition::factory()->officer()->create(['accepting_applications' => true]);
    $question = ApplicationQuestion::factory()->create();

    StaffApplication::factory()->denied()->create([
        'user_id' => $user->id,
        'staff_position_id' => $position->id,
    ]);

    $application = SubmitApplication::run($user, $position, [
        $question->id => 'My new answer',
    ]);

    expect($application)
        ->toBeInstanceOf(StaffApplication::class)
        ->status->toBe(ApplicationStatus::Submitted);

    expect(StaffApplication::where('user_id', $user->id)
        ->where('staff_position_id', $position->id)
        ->count()
    )->toBe(2);
});
