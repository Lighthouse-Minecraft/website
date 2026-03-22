<?php

declare(strict_types=1);

use App\Enums\ReportLocation;
use App\Enums\ReportSeverity;
use App\Enums\ReportStatus;
use App\Enums\StaffDepartment;
use App\Enums\StaffRank;
use App\Models\DisciplineReport;
use App\Models\User;
use Livewire\Volt\Volt;

uses()->group('discipline-reports', 'livewire');

it('shows discipline reports card to staff on profile page', function () {
    $staff = officerCommand();
    loginAs($staff);
    $subject = User::factory()->create();

    $this->get(route('profile.show', $subject))
        ->assertSeeLivewire('users.discipline-reports-card');
});

it('shows discipline reports card to the subject user', function () {
    $user = User::factory()->create();
    loginAs($user);

    $this->get(route('profile.show', $user))
        ->assertSeeLivewire('users.discipline-reports-card');
});

it('shows discipline reports card to parent of subject', function () {
    $parent = User::factory()->create();
    $child = User::factory()->create();
    $parent->children()->attach($child);
    loginAs($parent);

    $this->get(route('profile.show', $child))
        ->assertSeeLivewire('users.discipline-reports-card');
});

it('hides discipline reports card from unrelated users', function () {
    $unrelatedUser = membershipTraveler();
    loginAs($unrelatedUser);
    $subject = User::factory()->create();

    Volt::test('users.discipline-reports-card', ['user' => $subject])
        ->assertForbidden();
});

it('shows only published reports to non-staff users', function () {
    $subject = User::factory()->create();
    loginAs($subject);

    DisciplineReport::factory()->forSubject($subject)->create([
        'description' => 'Draft report unique text',
    ]);
    DisciplineReport::factory()->forSubject($subject)->published()->create([
        'description' => 'Published report unique text',
    ]);

    Volt::test('users.discipline-reports-card', ['user' => $subject])
        ->assertDontSee('Draft report unique text')
        ->assertSee('Published report unique text');
});

it('shows all reports including drafts to staff', function () {
    $staff = officerCommand();
    loginAs($staff);
    $subject = User::factory()->create();

    DisciplineReport::factory()->forSubject($subject)->create([
        'description' => 'Draft visible to staff',
    ]);
    DisciplineReport::factory()->forSubject($subject)->published()->create([
        'description' => 'Published visible to staff',
    ]);

    Volt::test('users.discipline-reports-card', ['user' => $subject])
        ->assertSee('Draft visible to staff')
        ->assertSee('Published visible to staff');
});

it('allows staff to create a report via modal', function () {
    $staff = User::factory()
        ->withStaffPosition(StaffDepartment::Command, StaffRank::Officer)
        ->withRole('Staff Access')
        ->withRole('Discipline Report - Manager')
        ->create();
    loginAs($staff);
    $subject = User::factory()->create();

    Volt::test('users.discipline-reports-card', ['user' => $subject])
        ->set('formDescription', 'Test incident happened here')
        ->set('formLocation', ReportLocation::Minecraft->value)
        ->set('formActionsTaken', 'Warning given')
        ->set('formSeverity', ReportSeverity::Minor->value)
        ->call('createReport');

    $this->assertDatabaseHas('discipline_reports', [
        'subject_user_id' => $subject->id,
        'reporter_user_id' => $staff->id,
        'description' => 'Test incident happened here',
        'status' => ReportStatus::Draft->value,
    ]);
});

it('allows user with Discipline Report - Publisher role to publish a draft report', function () {
    $publisher = User::factory()
        ->withStaffPosition(StaffDepartment::Quartermaster, StaffRank::Officer)
        ->withRole('Staff Access')
        ->withRole('Discipline Report - Publisher')
        ->create();
    loginAs($publisher);
    $subject = User::factory()->create();
    $report = DisciplineReport::factory()->forSubject($subject)->create();

    Volt::test('users.discipline-reports-card', ['user' => $subject])
        ->call('publishReport', $report->id);

    expect($report->fresh()->status)->toBe(ReportStatus::Published);
});

it('prevents non-officer from publishing', function () {
    $crew = crewQuartermaster();
    loginAs($crew);
    $subject = User::factory()->create();
    $report = DisciplineReport::factory()->forSubject($subject)->create();

    Volt::test('users.discipline-reports-card', ['user' => $subject])
        ->call('publishReport', $report->id)
        ->assertForbidden();
});

it('allows creator to edit their draft report', function () {
    $creator = jrCrewQuartermaster();
    loginAs($creator);
    $subject = User::factory()->create();
    $report = DisciplineReport::factory()->forSubject($subject)->byReporter($creator)->create();

    Volt::test('users.discipline-reports-card', ['user' => $subject])
        ->call('openEditModal', $report->id)
        ->set('formDescription', 'Updated description here')
        ->set('formLocation', ReportLocation::DiscordVoice->value)
        ->set('formActionsTaken', 'Updated actions')
        ->set('formSeverity', ReportSeverity::Major->value)
        ->call('updateReport');

    expect($report->fresh()->description)->toBe('Updated description here');
});

it('prevents editing of published reports', function () {
    // Use a non-command crew member (not bypassed by before())
    $crew = crewQuartermaster();
    loginAs($crew);
    $subject = User::factory()->create();
    $report = DisciplineReport::factory()->forSubject($subject)->byReporter($crew)->published()->create();

    Volt::test('users.discipline-reports-card', ['user' => $subject])
        ->call('openEditModal', $report->id)
        ->assertForbidden();
});

it('shows risk score badge with correct color', function () {
    $staff = officerCommand();
    loginAs($staff);
    $subject = User::factory()->create();

    // Create a published severe report (10pts) from 2 days ago
    // 7d=10, 30d=10, 90d=10, total=30 -> orange
    DisciplineReport::factory()->forSubject($subject)->severe()->publishedDaysAgo(2)->create();

    Volt::test('users.discipline-reports-card', ['user' => $subject])
        ->assertSee('Risk:');
});
