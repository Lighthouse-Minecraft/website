<?php

declare(strict_types=1);

use App\Models\BackgroundCheck;
use App\Models\StaffApplication;
use App\Models\StaffPosition;
use App\Models\User;
use Livewire\Volt\Volt;

uses()->group('applications', 'livewire', 'background-checks');

it('hides Approve Application button when applicant has no terminal background check', function () {
    $reviewer = loginAsAdmin();
    $applicant = User::factory()->create();
    $position = StaffPosition::factory()->officer()->create(['accepting_applications' => true]);
    $application = StaffApplication::factory()->backgroundCheck()->for($applicant)->for($position, 'staffPosition')->create();

    Volt::test('staff-applications.review-detail', ['staffApplication' => $application])
        ->assertDontSee('Approve Application')
        ->assertSee('Approval requires a completed background check record');
});

it('shows Approve Application button when applicant has a passed background check', function () {
    $reviewer = loginAsAdmin();
    $applicant = User::factory()->create();
    $position = StaffPosition::factory()->officer()->create(['accepting_applications' => true]);
    $application = StaffApplication::factory()->backgroundCheck()->for($applicant)->for($position, 'staffPosition')->create();
    BackgroundCheck::factory()->passed()->create(['user_id' => $applicant->id]);

    Volt::test('staff-applications.review-detail', ['staffApplication' => $application])
        ->assertSee('Approve Application');
});

it('shows Approve Application button when applicant has a failed background check', function () {
    $reviewer = loginAsAdmin();
    $applicant = User::factory()->create();
    $position = StaffPosition::factory()->officer()->create(['accepting_applications' => true]);
    $application = StaffApplication::factory()->backgroundCheck()->for($applicant)->for($position, 'staffPosition')->create();
    BackgroundCheck::factory()->create([
        'user_id' => $applicant->id,
        'status' => \App\Enums\BackgroundCheckStatus::Failed,
    ]);

    Volt::test('staff-applications.review-detail', ['staffApplication' => $application])
        ->assertSee('Approve Application');
});

it('moves application to background check step', function () {
    $reviewer = loginAsAdmin();
    $applicant = User::factory()->create();
    $position = StaffPosition::factory()->officer()->create(['accepting_applications' => true]);
    $application = StaffApplication::factory()->interview()->for($applicant)->for($position, 'staffPosition')->create();

    Volt::test('staff-applications.review-detail', ['staffApplication' => $application])
        ->call('moveToBackgroundCheck')
        ->assertHasNoErrors();

    expect($application->fresh()->status)->toBe(\App\Enums\ApplicationStatus::BackgroundCheck);
});

it('approves application when terminal background check exists', function () {
    $reviewer = loginAsAdmin();
    $applicant = User::factory()->create();
    $position = StaffPosition::factory()->officer()->create(['accepting_applications' => true]);
    $application = StaffApplication::factory()->backgroundCheck()->for($applicant)->for($position, 'staffPosition')->create();
    BackgroundCheck::factory()->passed()->create(['user_id' => $applicant->id]);

    Volt::test('staff-applications.review-detail', ['staffApplication' => $application])
        ->call('approve')
        ->assertHasNoErrors();

    expect($application->fresh()->status)->toBe(\App\Enums\ApplicationStatus::Approved);
});
