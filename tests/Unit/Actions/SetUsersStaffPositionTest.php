<?php

namespace Tests\Unit\Actions;

use App\Actions\SetUsersStaffPosition;
use App\Enums\StaffDepartment;
use App\Enums\StaffRank;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('SetUsersStaffPosition Action', function () {
    it('sets staff position for user with no existing position', function () {
        $user = User::factory()->create([
            'staff_department' => null,
            'staff_rank' => StaffRank::None,
            'staff_title' => null,
        ]);

        $result = SetUsersStaffPosition::run(
            $user,
            'Captain',
            StaffDepartment::Command,
            StaffRank::Officer
        );

        expect($result)->toBeTrue();

        $user->refresh();
        expect($user->staff_department)->toBe(StaffDepartment::Command);
        expect($user->staff_rank)->toBe(StaffRank::Officer);
        expect($user->staff_title)->toBe('Captain');
    });

    it('updates existing staff position completely', function () {
        $user = User::factory()->create([
            'staff_department' => StaffDepartment::Engineer,
            'staff_rank' => StaffRank::JrCrew,
            'staff_title' => 'Mechanic',
        ]);

        $result = SetUsersStaffPosition::run(
            $user,
            'Chief Engineer',
            StaffDepartment::Command,
            StaffRank::Officer
        );

        expect($result)->toBeTrue();

        $user->refresh();
        expect($user->staff_department)->toBe(StaffDepartment::Command);
        expect($user->staff_rank)->toBe(StaffRank::Officer);
        expect($user->staff_title)->toBe('Chief Engineer');
    });

    it('returns false when title is null', function () {
        $user = User::factory()->create();

        $result = SetUsersStaffPosition::run(
            $user,
            null,
            StaffDepartment::Command,
            StaffRank::Officer
        );

        expect($result)->toBeFalse();

        // User should remain unchanged
        $user->refresh();
        expect($user->staff_department)->toBeNull();
        expect($user->staff_rank)->toBe(StaffRank::None);
        expect($user->staff_title)->toBeNull();
    });

    it('returns true when no changes are needed', function () {
        $user = User::factory()->create([
            'staff_department' => StaffDepartment::Quartermaster,
            'staff_rank' => StaffRank::CrewMember,
            'staff_title' => 'Supply Officer',
        ]);

        $result = SetUsersStaffPosition::run(
            $user,
            'Supply Officer',
            StaffDepartment::Quartermaster,
            StaffRank::CrewMember
        );

        expect($result)->toBeTrue();

        // No activity should be recorded when no changes are made
        expect(ActivityLog::count())->toBe(0);
    });

    it('records activity when staff position is updated', function () {
        $user = User::factory()->create([
            'staff_department' => StaffDepartment::Steward,
            'staff_rank' => StaffRank::JrCrew,
            'staff_title' => 'Kitchen Assistant',
        ]);

        SetUsersStaffPosition::run(
            $user,
            'Head Chef',
            StaffDepartment::Steward,
            StaffRank::Officer
        );

        expect(ActivityLog::count())->toBe(1);

        $activityLog = ActivityLog::first();
        expect($activityLog->subject_id)->toBe($user->id);
        expect($activityLog->subject_type)->toBe(User::class);
        expect($activityLog->action)->toBe('staff_position_updated');
        expect($activityLog->description)->toContain('Updating staff position:');
        expect($activityLog->description)->toContain('Rank: Junior Crew Member => Officer');
        expect($activityLog->description)->toContain('Title: Kitchen Assistant => Head Chef');
    });

    it('only updates changed fields - department only', function () {
        $user = User::factory()->create([
            'staff_department' => StaffDepartment::Engineer,
            'staff_rank' => StaffRank::CrewMember,
            'staff_title' => 'Mechanic',
        ]);

        SetUsersStaffPosition::run(
            $user,
            'Mechanic',
            StaffDepartment::Quartermaster,
            StaffRank::CrewMember
        );

        $user->refresh();
        expect($user->staff_department)->toBe(StaffDepartment::Quartermaster);
        expect($user->staff_rank)->toBe(StaffRank::CrewMember);
        expect($user->staff_title)->toBe('Mechanic');

        $activityLog = ActivityLog::first();
        expect($activityLog->description)->toContain('Department: Engineer => Quartermaster');
        expect($activityLog->description)->not->toContain('Rank:');
        expect($activityLog->description)->not->toContain('Title:');
    });

    it('only updates changed fields - rank only', function () {
        $user = User::factory()->create([
            'staff_department' => StaffDepartment::Chaplain,
            'staff_rank' => StaffRank::JrCrew,
            'staff_title' => 'Assistant',
        ]);

        SetUsersStaffPosition::run(
            $user,
            'Assistant',
            StaffDepartment::Chaplain,
            StaffRank::Officer
        );

        $user->refresh();
        expect($user->staff_department)->toBe(StaffDepartment::Chaplain);
        expect($user->staff_rank)->toBe(StaffRank::Officer);
        expect($user->staff_title)->toBe('Assistant');

        $activityLog = ActivityLog::first();
        expect($activityLog->description)->toContain('Rank: Junior Crew Member => Officer');
        expect($activityLog->description)->not->toContain('Department:');
        expect($activityLog->description)->not->toContain('Title:');
    });

    it('only updates changed fields - title only', function () {
        $user = User::factory()->create([
            'staff_department' => StaffDepartment::Command,
            'staff_rank' => StaffRank::Officer,
            'staff_title' => 'Lieutenant',
        ]);

        SetUsersStaffPosition::run(
            $user,
            'Commander',
            StaffDepartment::Command,
            StaffRank::Officer
        );

        $user->refresh();
        expect($user->staff_department)->toBe(StaffDepartment::Command);
        expect($user->staff_rank)->toBe(StaffRank::Officer);
        expect($user->staff_title)->toBe('Commander');

        $activityLog = ActivityLog::first();
        expect($activityLog->description)->toContain('Title: Lieutenant => Commander');
        expect($activityLog->description)->not->toContain('Department:');
        expect($activityLog->description)->not->toContain('Rank:');
    });

    it('handles user with null existing department correctly', function () {
        $user = User::factory()->create([
            'staff_department' => null,
            'staff_rank' => StaffRank::None,
            'staff_title' => null,
        ]);

        SetUsersStaffPosition::run(
            $user,
            'New Officer',
            StaffDepartment::Engineer,
            StaffRank::Officer
        );

        $user->refresh();
        expect($user->staff_department)->toBe(StaffDepartment::Engineer);
        expect($user->staff_rank)->toBe(StaffRank::Officer);
        expect($user->staff_title)->toBe('New Officer');

        $activityLog = ActivityLog::first();
        expect($activityLog->description)->toContain('Department:  => Engineer');
        expect($activityLog->description)->toContain('Rank: None => Officer');
        expect($activityLog->description)->toContain('Title:  => New Officer');
    });

    it('handles user with None rank correctly', function () {
        $user = User::factory()->create([
            'staff_department' => null,
            'staff_rank' => StaffRank::None,
            'staff_title' => null,
        ]);

        SetUsersStaffPosition::run(
            $user,
            'Trainee',
            StaffDepartment::Steward,
            StaffRank::JrCrew
        );

        $activityLog = ActivityLog::first();
        expect($activityLog->description)->toContain('Rank: None => Junior Crew Member');
    });

    it('works with all staff departments', function () {
        $departments = [
            StaffDepartment::Command,
            StaffDepartment::Chaplain,
            StaffDepartment::Engineer,
            StaffDepartment::Quartermaster,
            StaffDepartment::Steward,
        ];

        foreach ($departments as $department) {
            $user = User::factory()->create();

            $result = SetUsersStaffPosition::run(
                $user,
                'Test Title',
                $department,
                StaffRank::CrewMember
            );

            expect($result)->toBeTrue();
            $user->refresh();
            expect($user->staff_department)->toBe($department);

            $activityLog = ActivityLog::where('subject_id', $user->id)->first();
            expect($activityLog->description)->toContain("Department:  => {$department->label()}");
        }
    });

    it('works with all staff ranks', function () {
        $ranks = [
            StaffRank::JrCrew,
            StaffRank::CrewMember,
            StaffRank::Officer,
        ];

        foreach ($ranks as $rank) {
            $user = User::factory()->create();

            $result = SetUsersStaffPosition::run(
                $user,
                'Test Title',
                StaffDepartment::Command,
                $rank
            );

            expect($result)->toBeTrue();
            $user->refresh();
            expect($user->staff_rank)->toBe($rank);

            $activityLog = ActivityLog::where('subject_id', $user->id)->first();
            expect($activityLog->description)->toContain("Rank: None => {$rank->label()}");
        }
    });

    it('handles special characters in title', function () {
        $user = User::factory()->create();
        $specialTitle = 'Chef & Kitchen Manager (Senior) - éñ中文';

        SetUsersStaffPosition::run(
            $user,
            $specialTitle,
            StaffDepartment::Steward,
            StaffRank::Officer
        );

        $user->refresh();
        expect($user->staff_title)->toBe($specialTitle);

        $activityLog = ActivityLog::first();
        expect($activityLog->description)->toContain("Title:  => {$specialTitle}");
    });

    it('handles empty string title as null', function () {
        $user = User::factory()->create();

        $result = SetUsersStaffPosition::run(
            $user,
            '',
            StaffDepartment::Command,
            StaffRank::Officer
        );

        // Empty string should be treated as valid (not null)
        expect($result)->toBeFalse();
    });

    it('preserves other user data when updating staff position', function () {
        $user = User::factory()->create([
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
        ]);

        $originalName = $user->name;
        $originalEmail = $user->email;
        $originalId = $user->id;

        SetUsersStaffPosition::run(
            $user,
            'Officer',
            StaffDepartment::Command,
            StaffRank::Officer
        );

        $user->refresh();
        expect($user->id)->toBe($originalId);
        expect($user->name)->toBe($originalName);
        expect($user->email)->toBe($originalEmail);
        expect($user->staff_title)->toBe('Officer');
    });

    it('persists changes to database correctly', function () {
        $user = User::factory()->create();
        $userId = $user->id;

        SetUsersStaffPosition::run(
            $user,
            'Database Test',
            StaffDepartment::Engineer,
            StaffRank::CrewMember
        );

        // Fetch fresh instance from database
        $freshUser = User::find($userId);

        expect($freshUser->staff_department)->toBe(StaffDepartment::Engineer);
        expect($freshUser->staff_rank)->toBe(StaffRank::CrewMember);
        expect($freshUser->staff_title)->toBe('Database Test');
    });

    it('handles multiple updates to same user', function () {
        $user = User::factory()->create();

        // First update
        SetUsersStaffPosition::run(
            $user,
            'Junior Officer',
            StaffDepartment::Command,
            StaffRank::JrCrew
        );

        // Second update
        SetUsersStaffPosition::run(
            $user,
            'Senior Officer',
            StaffDepartment::Engineer,
            StaffRank::Officer
        );

        $user->refresh();
        expect($user->staff_department)->toBe(StaffDepartment::Engineer);
        expect($user->staff_rank)->toBe(StaffRank::Officer);
        expect($user->staff_title)->toBe('Senior Officer');

        // Should have two activity logs
        expect(ActivityLog::where('subject_id', $user->id)->count())->toBe(2);
    });

    it('generates correct activity description with multiple changes', function () {
        $user = User::factory()->create([
            'staff_department' => StaffDepartment::Steward,
            'staff_rank' => StaffRank::JrCrew,
            'staff_title' => 'Assistant Cook',
        ]);

        SetUsersStaffPosition::run(
            $user,
            'Head Chef',
            StaffDepartment::Engineer,
            StaffRank::Officer
        );

        $activityLog = ActivityLog::first();
        $description = $activityLog->description;

        expect($description)->toStartWith('Updating staff position: ');
        expect($description)->toContain('Department: Steward => Engineer, ');
        expect($description)->toContain('Rank: Junior Crew Member => Officer, ');
        expect($description)->toContain('Title: Assistant Cook => Head Chef');
    });

    it('handles long titles correctly', function () {
        $user = User::factory()->create();
        $longTitle = 'Senior Chief Petty Officer of the Engineering Department and Maintenance Supervisor Extraordinaire';

        SetUsersStaffPosition::run(
            $user,
            $longTitle,
            StaffDepartment::Engineer,
            StaffRank::Officer
        );

        $user->refresh();
        expect($user->staff_title)->toBe($longTitle);

        $activityLog = ActivityLog::first();
        expect($activityLog->description)->toContain("Title:  => {$longTitle}");
    });
});
