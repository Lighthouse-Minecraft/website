<?php

declare(strict_types=1);

use App\Enums\StaffDepartment;
use App\Enums\StaffRank;
use App\Models\StaffApplication;
use App\Models\StaffPosition;
use App\Models\User;

uses()->group('gates', 'roles');

// == manage-stowaway-users / manage-traveler-users == //

it('grants manage-stowaway-users to user with Membership Level - Manager role', function () {
    $user = User::factory()->withRole('Membership Level - Manager')->create();

    expect($user->can('manage-stowaway-users'))->toBeTrue();
});

it('grants manage-traveler-users to user with Membership Level - Manager role', function () {
    $user = User::factory()->withRole('Membership Level - Manager')->create();

    expect($user->can('manage-traveler-users'))->toBeTrue();
});

it('denies manage-stowaway-users without Membership Level - Manager role', function () {
    $user = User::factory()
        ->withStaffPosition(StaffDepartment::Quartermaster, StaffRank::Officer)
        ->create();

    expect($user->can('manage-stowaway-users'))->toBeFalse();
});

it('grants manage-stowaway-users to admin via hasRole override', function () {
    $user = User::factory()->admin()->create();

    expect($user->can('manage-stowaway-users'))->toBeTrue()
        ->and($user->can('manage-traveler-users'))->toBeTrue();
});

it('grants manage-stowaway-users to user with allow-all position', function () {
    $user = User::factory()->create();
    StaffPosition::factory()->assignedTo($user->id)->create(['has_all_roles_at' => now()]);

    expect($user->fresh()->can('manage-stowaway-users'))->toBeTrue();
});

// == put-in-brig == //

it('grants put-in-brig to user with Brig Warden role', function () {
    $user = User::factory()->withRole('Brig Warden')->create();

    expect($user->can('put-in-brig'))->toBeTrue();
});

it('denies put-in-brig without Brig Warden role', function () {
    $user = User::factory()
        ->withStaffPosition(StaffDepartment::Command, StaffRank::Officer)
        ->create();

    expect($user->can('put-in-brig'))->toBeFalse();
});

it('grants put-in-brig to admin', function () {
    $user = User::factory()->admin()->create();

    expect($user->can('put-in-brig'))->toBeTrue();
});

// == release-from-brig == //

it('grants release-from-brig to user with Brig Warden role', function () {
    $user = User::factory()->withRole('Brig Warden')->create();

    expect($user->can('release-from-brig'))->toBeTrue();
});

it('denies release-from-brig without Brig Warden role', function () {
    $user = User::factory()
        ->withStaffPosition(StaffDepartment::Command, StaffRank::Officer)
        ->create();

    expect($user->can('release-from-brig'))->toBeFalse();
});

it('grants release-from-brig to admin', function () {
    $user = User::factory()->admin()->create();

    expect($user->can('release-from-brig'))->toBeTrue();
});

// == view-ready-room-{department} == //

it('grants view-ready-room-command to user in Command department', function () {
    $user = User::factory()
        ->withStaffPosition(StaffDepartment::Command, StaffRank::JrCrew)
        ->create();

    expect($user->can('view-ready-room-command'))->toBeTrue();
});

it('denies view-ready-room-command to user in different department without role', function () {
    $user = User::factory()
        ->withStaffPosition(StaffDepartment::Chaplain, StaffRank::Officer)
        ->create();

    expect($user->can('view-ready-room-command'))->toBeFalse();
});

it('grants view-ready-room-command to user with Ready Room - View All role', function () {
    $user = User::factory()->withRole('Ready Room - View All')->create();

    expect($user->can('view-ready-room-command'))->toBeTrue();
});

it('grants all department ready rooms to admin', function () {
    $user = User::factory()->admin()->create();

    expect($user->can('view-ready-room-command'))->toBeTrue()
        ->and($user->can('view-ready-room-chaplain'))->toBeTrue()
        ->and($user->can('view-ready-room-engineer'))->toBeTrue()
        ->and($user->can('view-ready-room-quartermaster'))->toBeTrue()
        ->and($user->can('view-ready-room-steward'))->toBeTrue();
});

it('grants view-ready-room-chaplain to user in Chaplain department', function () {
    $user = User::factory()
        ->withStaffPosition(StaffDepartment::Chaplain, StaffRank::JrCrew)
        ->create();

    expect($user->can('view-ready-room-chaplain'))->toBeTrue()
        ->and($user->can('view-ready-room-command'))->toBeFalse();
});

it('grants view-ready-room-engineer to user in Engineer department', function () {
    $user = User::factory()
        ->withStaffPosition(StaffDepartment::Engineer, StaffRank::JrCrew)
        ->create();

    expect($user->can('view-ready-room-engineer'))->toBeTrue()
        ->and($user->can('view-ready-room-command'))->toBeFalse();
});

it('grants view-ready-room-quartermaster to user in Quartermaster department', function () {
    $user = User::factory()
        ->withStaffPosition(StaffDepartment::Quartermaster, StaffRank::JrCrew)
        ->create();

    expect($user->can('view-ready-room-quartermaster'))->toBeTrue()
        ->and($user->can('view-ready-room-command'))->toBeFalse();
});

it('grants view-ready-room-steward to user in Steward department', function () {
    $user = User::factory()
        ->withStaffPosition(StaffDepartment::Steward, StaffRank::JrCrew)
        ->create();

    expect($user->can('view-ready-room-steward'))->toBeTrue()
        ->and($user->can('view-ready-room-command'))->toBeFalse();
});

it('grants all department ready rooms to user with Ready Room - View All role', function () {
    $user = User::factory()->withRole('Ready Room - View All')->create();

    expect($user->can('view-ready-room-command'))->toBeTrue()
        ->and($user->can('view-ready-room-chaplain'))->toBeTrue()
        ->and($user->can('view-ready-room-engineer'))->toBeTrue()
        ->and($user->can('view-ready-room-quartermaster'))->toBeTrue()
        ->and($user->can('view-ready-room-steward'))->toBeTrue();
});

// == view-acp == //

it('grants view-acp to admin', function () {
    $user = User::factory()->admin()->create();

    expect($user->can('view-acp'))->toBeTrue();
});

it('grants view-acp to user with Staff Access role', function () {
    $user = User::factory()->withRole('Staff Access')->create();

    expect($user->can('view-acp'))->toBeTrue();
});

it('denies view-acp to staff without Staff Access role', function () {
    $user = User::factory()
        ->withStaffPosition(StaffDepartment::Chaplain, StaffRank::JrCrew)
        ->create();

    expect($user->can('view-acp'))->toBeFalse();
});

it('denies view-acp to regular user', function () {
    $user = User::factory()->create();

    expect($user->can('view-acp'))->toBeFalse();
});

// == view-*-log gates == //

it('grants all log gates to user with Logs - Viewer role', function () {
    $user = User::factory()->withRole('Logs - Viewer')->create();

    expect($user->can('view-mc-command-log'))->toBeTrue()
        ->and($user->can('view-discord-api-log'))->toBeTrue()
        ->and($user->can('view-activity-log'))->toBeTrue()
        ->and($user->can('view-discipline-report-log'))->toBeTrue();
});

it('denies log gates without Logs - Viewer role', function () {
    $user = User::factory()
        ->withStaffPosition(StaffDepartment::Engineer, StaffRank::Officer)
        ->create();

    expect($user->can('view-mc-command-log'))->toBeFalse()
        ->and($user->can('view-discord-api-log'))->toBeFalse()
        ->and($user->can('view-activity-log'))->toBeFalse()
        ->and($user->can('view-discipline-report-log'))->toBeFalse();
});

it('grants view-discipline-report-log to user with Discipline Report - Manager role', function () {
    $user = User::factory()->withRole('Discipline Report - Manager')->create();

    expect($user->can('view-discipline-report-log'))->toBeTrue()
        ->and($user->can('view-mc-command-log'))->toBeFalse()
        ->and($user->can('view-discord-api-log'))->toBeFalse()
        ->and($user->can('view-activity-log'))->toBeFalse();
});

it('grants all log gates to admin', function () {
    $user = User::factory()->admin()->create();

    expect($user->can('view-mc-command-log'))->toBeTrue()
        ->and($user->can('view-discord-api-log'))->toBeTrue()
        ->and($user->can('view-activity-log'))->toBeTrue()
        ->and($user->can('view-discipline-report-log'))->toBeTrue();
});

// == manage-discipline-reports == //

it('grants manage-discipline-reports to user with Discipline Report - Manager role', function () {
    $user = User::factory()->withRole('Discipline Report - Manager')->create();

    expect($user->can('manage-discipline-reports'))->toBeTrue();
});

it('denies manage-discipline-reports without role', function () {
    $user = User::factory()
        ->withStaffPosition(StaffDepartment::Command, StaffRank::Officer)
        ->create();

    expect($user->can('manage-discipline-reports'))->toBeFalse();
});

// == publish-discipline-reports == //

it('grants publish-discipline-reports to user with Discipline Report - Publisher role', function () {
    $user = User::factory()->withRole('Discipline Report - Publisher')->create();

    expect($user->can('publish-discipline-reports'))->toBeTrue();
});

it('denies publish-discipline-reports without role', function () {
    $user = User::factory()
        ->withStaffPosition(StaffDepartment::Command, StaffRank::Officer)
        ->create();

    expect($user->can('publish-discipline-reports'))->toBeFalse();
});

// == manage-site-config == //

it('grants manage-site-config to user with Site Config - Manager role', function () {
    $user = User::factory()->withRole('Site Config - Manager')->create();

    expect($user->can('manage-site-config'))->toBeTrue();
});

it('denies manage-site-config without role', function () {
    $user = User::factory()
        ->withStaffPosition(StaffDepartment::Command, StaffRank::Officer)
        ->create();

    expect($user->can('manage-site-config'))->toBeFalse();
});

// == view-command-dashboard == //

it('grants view-command-dashboard to user with Command Dashboard - Viewer role', function () {
    $user = User::factory()->withRole('Command Dashboard - Viewer')->create();

    expect($user->can('view-command-dashboard'))->toBeTrue();
});

it('denies view-command-dashboard without role', function () {
    $user = User::factory()
        ->withStaffPosition(StaffDepartment::Command, StaffRank::Officer)
        ->create();

    expect($user->can('view-command-dashboard'))->toBeFalse();
});

// == edit-docs == //

it('grants edit-docs in local environment', function () {
    app()->detectEnvironment(fn () => 'local');
    $user = User::factory()->create();

    expect($user->can('edit-docs'))->toBeTrue();
});

it('denies edit-docs in production environment', function () {
    app()->detectEnvironment(fn () => 'production');
    $user = User::factory()->admin()->create();

    expect($user->can('edit-docs'))->toBeFalse();
});

// == lock-topic == //

it('grants lock-topic to user with Moderator role', function () {
    $user = User::factory()->withRole('Moderator')->create();

    expect($user->can('lock-topic'))->toBeTrue();
});

it('denies lock-topic without Moderator role', function () {
    $user = User::factory()
        ->withStaffPosition(StaffDepartment::Command, StaffRank::Officer)
        ->create();

    expect($user->can('lock-topic'))->toBeFalse();
});

// == manage-community-stories == //

it('grants manage-community-stories to user with Community Stories - Manager role', function () {
    $user = User::factory()->withRole('Community Stories - Manager')->create();

    expect($user->can('manage-community-stories'))->toBeTrue();
});

it('denies manage-community-stories without role', function () {
    $user = User::factory()
        ->withStaffPosition(StaffDepartment::Chaplain, StaffRank::Officer)
        ->create();

    expect($user->can('manage-community-stories'))->toBeFalse();
});

// == manage-application-questions == //

it('grants manage-application-questions to user with Site Config - Manager role', function () {
    $user = User::factory()->withRole('Site Config - Manager')->create();

    expect($user->can('manage-application-questions'))->toBeTrue();
});

it('denies manage-application-questions without role', function () {
    $user = User::factory()
        ->withStaffPosition(StaffDepartment::Command, StaffRank::Officer)
        ->create();

    expect($user->can('manage-application-questions'))->toBeFalse();
});

// == review-staff-applications == //

it('grants review-staff-applications to admin', function () {
    $user = User::factory()->admin()->create();

    expect($user->can('review-staff-applications'))->toBeTrue();
});

it('grants review-staff-applications to user with Applicant Review - All role', function () {
    $user = User::factory()
        ->withRole('Applicant Review - All')
        ->create();

    expect($user->can('review-staff-applications'))->toBeTrue();
});

it('grants review-staff-applications list access to Applicant Review - Department', function () {
    $user = User::factory()
        ->withStaffPosition(StaffDepartment::Chaplain, StaffRank::JrCrew)
        ->withRole('Applicant Review - Department')
        ->create();

    // Without application parameter, department reviewer can access the list
    expect($user->can('review-staff-applications'))->toBeTrue();
});

it('grants review-staff-applications for same-department application to Applicant Review - Department', function () {
    $user = User::factory()
        ->withStaffPosition(StaffDepartment::Chaplain, StaffRank::JrCrew)
        ->withRole('Applicant Review - Department')
        ->create();

    $position = StaffPosition::factory()
        ->inDepartment(StaffDepartment::Chaplain)
        ->create();
    $application = StaffApplication::factory()->create(['staff_position_id' => $position->id]);

    expect($user->can('review-staff-applications', $application))->toBeTrue();
});

it('denies review-staff-applications for different-department application to Applicant Review - Department', function () {
    $user = User::factory()
        ->withStaffPosition(StaffDepartment::Chaplain, StaffRank::JrCrew)
        ->withRole('Applicant Review - Department')
        ->create();

    $position = StaffPosition::factory()
        ->inDepartment(StaffDepartment::Engineer)
        ->create();
    $application = StaffApplication::factory()->create(['staff_position_id' => $position->id]);

    expect($user->can('review-staff-applications', $application))->toBeFalse();
});

it('grants review-staff-applications for any department to Applicant Review - All', function () {
    $user = User::factory()
        ->withRole('Applicant Review - All')
        ->create();

    $position = StaffPosition::factory()
        ->inDepartment(StaffDepartment::Engineer)
        ->create();
    $application = StaffApplication::factory()->create(['staff_position_id' => $position->id]);

    expect($user->can('review-staff-applications', $application))->toBeTrue();
});

it('denies review-staff-applications to regular users', function () {
    $user = User::factory()->create();

    expect($user->can('review-staff-applications'))->toBeFalse();
});

it('denies review-staff-applications to staff without applicant review role', function () {
    $user = User::factory()
        ->withStaffPosition(StaffDepartment::Chaplain, StaffRank::CrewMember)
        ->withRole('Staff Access')
        ->create();

    // Staff Access alone does not grant applicant review access
    expect($user->can('review-staff-applications'))->toBeFalse();
});

it('grants review-staff-applications for same-department application to CrewMember with Applicant Review - Department', function () {
    $user = User::factory()
        ->withStaffPosition(StaffDepartment::Chaplain, StaffRank::CrewMember)
        ->withRole('Applicant Review - Department')
        ->create();

    $position = StaffPosition::factory()
        ->inDepartment(StaffDepartment::Chaplain)
        ->create();
    $application = StaffApplication::factory()->create(['staff_position_id' => $position->id]);

    expect($user->can('review-staff-applications', $application))->toBeTrue();
});

it('denies review-staff-applications for different-department application to CrewMember with Applicant Review - Department', function () {
    $user = User::factory()
        ->withStaffPosition(StaffDepartment::Chaplain, StaffRank::CrewMember)
        ->withRole('Applicant Review - Department')
        ->create();

    $position = StaffPosition::factory()
        ->inDepartment(StaffDepartment::Engineer)
        ->create();
    $application = StaffApplication::factory()->create(['staff_position_id' => $position->id]);

    expect($user->can('review-staff-applications', $application))->toBeFalse();
});

// == Staff Access role gates == //

it('grants view-ready-room to user with Staff Access role', function () {
    $user = User::factory()->withRole('Staff Access')->create();

    expect($user->can('view-ready-room'))->toBeTrue();
});

it('denies view-ready-room to staff without Staff Access role', function () {
    $user = User::factory()
        ->withStaffPosition(StaffDepartment::Chaplain, StaffRank::JrCrew)
        ->create();

    expect($user->can('view-ready-room'))->toBeFalse();
});

it('denies view-ready-room to regular user', function () {
    $user = User::factory()->create();

    expect($user->can('view-ready-room'))->toBeFalse();
});

it('grants edit-staff-bio to user with Staff Access role', function () {
    $user = User::factory()->withRole('Staff Access')->create();

    expect($user->can('edit-staff-bio'))->toBeTrue();
});

it('denies edit-staff-bio to staff without Staff Access role', function () {
    $user = User::factory()
        ->withStaffPosition(StaffDepartment::Chaplain, StaffRank::CrewMember)
        ->create();

    expect($user->can('edit-staff-bio'))->toBeFalse();
});

it('grants edit-staff-bio to board member without Staff Access role', function () {
    $user = User::factory()->create(['is_board_member' => true]);

    expect($user->can('edit-staff-bio'))->toBeTrue();
});

it('grants board-member to board member (unchanged)', function () {
    $user = User::factory()->create(['is_board_member' => true]);

    expect($user->can('board-member'))->toBeTrue();
});

// == Membership-level gates remain unchanged == //

it('grants view-community-content to user not in brig (unchanged)', function () {
    $user = User::factory()->create(['in_brig' => false]);

    expect($user->can('view-community-content'))->toBeTrue();
});

it('denies view-community-content to user in brig (unchanged)', function () {
    $user = User::factory()->create(['in_brig' => true]);

    expect($user->can('view-community-content'))->toBeFalse();
});

// == Allow All positions pass all role-based gates == //

it('grants all role-based gates to user with allow-all position', function () {
    $user = User::factory()->create();
    StaffPosition::factory()->assignedTo($user->id)->create(['has_all_roles_at' => now()]);
    $user = $user->fresh();

    expect($user->can('manage-stowaway-users'))->toBeTrue()
        ->and($user->can('manage-traveler-users'))->toBeTrue()
        ->and($user->can('put-in-brig'))->toBeTrue()
        ->and($user->can('release-from-brig'))->toBeTrue()
        ->and($user->can('view-mc-command-log'))->toBeTrue()
        ->and($user->can('view-discord-api-log'))->toBeTrue()
        ->and($user->can('view-activity-log'))->toBeTrue()
        ->and($user->can('view-discipline-report-log'))->toBeTrue()
        ->and($user->can('manage-discipline-reports'))->toBeTrue()
        ->and($user->can('publish-discipline-reports'))->toBeTrue()
        ->and($user->can('manage-site-config'))->toBeTrue()
        ->and($user->can('view-command-dashboard'))->toBeTrue()
        ->and($user->can('lock-topic'))->toBeTrue()
        ->and($user->can('manage-community-stories'))->toBeTrue()
        ->and($user->can('manage-application-questions'))->toBeTrue()
        ->and($user->can('view-acp'))->toBeTrue()
        ->and($user->can('view-ready-room'))->toBeTrue()
        ->and($user->can('edit-staff-bio'))->toBeTrue();
});

// == Admin override works for all role-based gates == //

it('grants all role-based gates to admin user', function () {
    $user = User::factory()->admin()->create();

    expect($user->can('manage-stowaway-users'))->toBeTrue()
        ->and($user->can('manage-traveler-users'))->toBeTrue()
        ->and($user->can('put-in-brig'))->toBeTrue()
        ->and($user->can('release-from-brig'))->toBeTrue()
        ->and($user->can('view-mc-command-log'))->toBeTrue()
        ->and($user->can('view-discord-api-log'))->toBeTrue()
        ->and($user->can('view-activity-log'))->toBeTrue()
        ->and($user->can('view-discipline-report-log'))->toBeTrue()
        ->and($user->can('manage-discipline-reports'))->toBeTrue()
        ->and($user->can('publish-discipline-reports'))->toBeTrue()
        ->and($user->can('manage-site-config'))->toBeTrue()
        ->and($user->can('view-command-dashboard'))->toBeTrue()
        ->and($user->can('lock-topic'))->toBeTrue()
        ->and($user->can('manage-community-stories'))->toBeTrue()
        ->and($user->can('manage-application-questions'))->toBeTrue()
        ->and($user->can('view-ready-room-command'))->toBeTrue()
        ->and($user->can('view-ready-room-chaplain'))->toBeTrue()
        ->and($user->can('view-ready-room-engineer'))->toBeTrue()
        ->and($user->can('view-ready-room-quartermaster'))->toBeTrue()
        ->and($user->can('view-ready-room-steward'))->toBeTrue()
        ->and($user->can('view-acp'))->toBeTrue()
        ->and($user->can('view-ready-room'))->toBeTrue()
        ->and($user->can('edit-staff-bio'))->toBeTrue();
});
