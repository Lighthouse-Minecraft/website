<?php

namespace Tests\Unit\Actions;

use App\Actions\RemoveUsersStaffPosition;
use App\Enums\StaffDepartment;
use App\Enums\StaffRank;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('RemoveUsersStaffPosition Action', function () {
    it('removes all staff position details from user', function () {
        $user = User::factory()->create([
            'staff_department' => StaffDepartment::Command,
            'staff_rank' => StaffRank::Officer,
            'staff_title' => 'Captain',
        ]);

        RemoveUsersStaffPosition::run($user);

        $user->refresh();

        expect($user->staff_department)->toBeNull();
        expect($user->staff_rank)->toBe(StaffRank::None);
        expect($user->staff_title)->toBeNull();
    });

    it('records activity when staff position is removed', function () {
        $user = User::factory()->create([
            'staff_department' => StaffDepartment::Engineer,
            'staff_rank' => StaffRank::CrewMember,
            'staff_title' => 'Chief Engineer',
        ]);

        RemoveUsersStaffPosition::run($user);

        expect(ActivityLog::count())->toBe(1);

        $activityLog = ActivityLog::first();
        expect($activityLog->subject_id)->toBe($user->id);
        expect($activityLog->subject_type)->toBe(User::class);
        expect($activityLog->action)->toBe('staff_position_removed');
        expect($activityLog->description)->toContain('Removed staff position:');
        expect($activityLog->description)->toContain('Department: Engineer');
        expect($activityLog->description)->toContain('Rank: Crew Member');
        expect($activityLog->description)->toContain('Title: Chief Engineer');
    });

    it('returns true when operation is successful', function () {
        $user = User::factory()->create([
            'staff_department' => StaffDepartment::Quartermaster,
            'staff_rank' => StaffRank::JrCrew,
            'staff_title' => 'Supply Assistant',
        ]);

        $result = RemoveUsersStaffPosition::run($user);

        expect($result)->toBeTrue();
    });

    it('handles user with different staff departments correctly', function () {
        $testCases = [
            [StaffDepartment::Command, 'Admiral'],
            [StaffDepartment::Chaplain, 'Senior Chaplain'],
            [StaffDepartment::Engineer, 'Lead Engineer'],
            [StaffDepartment::Quartermaster, 'Supply Officer'],
            [StaffDepartment::Steward, 'Head Steward'],
        ];

        foreach ($testCases as [$department, $title]) {
            $user = User::factory()->create([
                'staff_department' => $department,
                'staff_rank' => StaffRank::Officer,
                'staff_title' => $title,
            ]);

            RemoveUsersStaffPosition::run($user);

            $user->refresh();
            expect($user->staff_department)->toBeNull();
            expect($user->staff_rank)->toBe(StaffRank::None);
            expect($user->staff_title)->toBeNull();

            $activityLog = ActivityLog::where('subject_id', $user->id)->first();
            expect($activityLog->description)->toContain("Department: {$department->label()}");
            expect($activityLog->description)->toContain("Title: {$title}");
        }
    });

    it('handles user with different staff ranks correctly', function () {
        $testCases = [
            StaffRank::JrCrew,
            StaffRank::CrewMember,
            StaffRank::Officer,
        ];

        foreach ($testCases as $rank) {
            $user = User::factory()->create([
                'staff_department' => StaffDepartment::Command,
                'staff_rank' => $rank,
                'staff_title' => 'Test Title',
            ]);

            RemoveUsersStaffPosition::run($user);

            $user->refresh();
            expect($user->staff_rank)->toBe(StaffRank::None);

            $activityLog = ActivityLog::where('subject_id', $user->id)->first();
            expect($activityLog->description)->toContain("Rank: {$rank->label()}");
        }
    });

    it('handles user with null staff title', function () {
        $user = User::factory()->create([
            'staff_department' => StaffDepartment::Steward,
            'staff_rank' => StaffRank::CrewMember,
            'staff_title' => null,
        ]);

        RemoveUsersStaffPosition::run($user);

        $user->refresh();
        expect($user->staff_department)->toBeNull();
        expect($user->staff_rank)->toBe(StaffRank::None);
        expect($user->staff_title)->toBeNull();

        $activityLog = ActivityLog::first();
        expect($activityLog->description)->toContain('Title: ');
        expect($activityLog->description)->toContain('Department: Steward');
        expect($activityLog->description)->toContain('Rank: Crew Member');
    });

    it('handles user with empty string staff title', function () {
        $user = User::factory()->create([
            'staff_department' => StaffDepartment::Chaplain,
            'staff_rank' => StaffRank::JrCrew,
            'staff_title' => '',
        ]);

        RemoveUsersStaffPosition::run($user);

        $user->refresh();
        expect($user->staff_department)->toBeNull();
        expect($user->staff_rank)->toBe(StaffRank::None);
        expect($user->staff_title)->toBeNull();

        $activityLog = ActivityLog::first();
        expect($activityLog->description)->toContain('Title: ');
    });

    it('preserves other user data when removing staff position', function () {
        $user = User::factory()->create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'staff_department' => StaffDepartment::Engineer,
            'staff_rank' => StaffRank::Officer,
            'staff_title' => 'Senior Engineer',
        ]);

        $originalName = $user->name;
        $originalEmail = $user->email;
        $originalId = $user->id;

        RemoveUsersStaffPosition::run($user);

        $user->refresh();
        expect($user->id)->toBe($originalId);
        expect($user->name)->toBe($originalName);
        expect($user->email)->toBe($originalEmail);
        expect($user->staff_department)->toBeNull();
        expect($user->staff_rank)->toBe(StaffRank::None);
        expect($user->staff_title)->toBeNull();
    });

    it('generates correct activity description format', function () {
        $user = User::factory()->create([
            'staff_department' => StaffDepartment::Command,
            'staff_rank' => StaffRank::Officer,
            'staff_title' => 'Fleet Admiral',
        ]);

        RemoveUsersStaffPosition::run($user);

        $activityLog = ActivityLog::first();
        $description = $activityLog->description;

        expect($description)->toStartWith('Removed staff position: ');
        expect($description)->toMatch('/Department: Command, Rank: Officer, Title: Fleet Admiral/');
    });

    it('works with user that has special characters in staff title', function () {
        $user = User::factory()->create([
            'staff_department' => StaffDepartment::Steward,
            'staff_rank' => StaffRank::CrewMember,
            'staff_title' => 'Chef & Kitchen Manager (Senior)',
        ]);

        RemoveUsersStaffPosition::run($user);

        $user->refresh();
        expect($user->staff_department)->toBeNull();
        expect($user->staff_rank)->toBe(StaffRank::None);
        expect($user->staff_title)->toBeNull();

        $activityLog = ActivityLog::first();
        expect($activityLog->description)->toContain('Title: Chef & Kitchen Manager (Senior)');
    });

    it('persists changes to database correctly', function () {
        $user = User::factory()->create([
            'staff_department' => StaffDepartment::Quartermaster,
            'staff_rank' => StaffRank::Officer,
            'staff_title' => 'Inventory Manager',
        ]);

        $userId = $user->id;

        RemoveUsersStaffPosition::run($user);

        // Fetch fresh instance from database
        $freshUser = User::find($userId);

        expect($freshUser->staff_department)->toBeNull();
        expect($freshUser->staff_rank)->toBe(StaffRank::None);
        expect($freshUser->staff_title)->toBeNull();
    });

    it('can be called multiple times on same user without error', function () {
        $user = User::factory()->create([
            'staff_department' => StaffDepartment::Engineer,
            'staff_rank' => StaffRank::CrewMember,
            'staff_title' => 'Mechanic',
        ]);

        // First removal
        $result1 = RemoveUsersStaffPosition::run($user);
        expect($result1)->toBeTrue();

        // Verify state after first removal
        $user->refresh();
        expect($user->staff_department)->toBeNull();
        expect($user->staff_rank)->toBe(StaffRank::None);
        expect($user->staff_title)->toBeNull();

        // Second removal (should still work)
        $result2 = RemoveUsersStaffPosition::run($user);
        expect($result2)->toBeTrue();

        // Verify state is still correct
        $user->refresh();
        expect($user->staff_department)->toBeNull();
        expect($user->staff_rank)->toBe(StaffRank::None);
        expect($user->staff_title)->toBeNull();

        // Should have two activity logs
        expect(ActivityLog::where('subject_id', $user->id)->count())->toBe(2);
    });

    it('handles long staff titles correctly', function () {
        $longTitle = 'Senior Chief Petty Officer of the Engineering Department and Maintenance Supervisor';

        $user = User::factory()->create([
            'staff_department' => StaffDepartment::Engineer,
            'staff_rank' => StaffRank::Officer,
            'staff_title' => $longTitle,
        ]);

        RemoveUsersStaffPosition::run($user);

        $user->refresh();
        expect($user->staff_title)->toBeNull();

        $activityLog = ActivityLog::first();
        expect($activityLog->description)->toContain("Title: {$longTitle}");
    });

    it('calls RecordActivity with correct parameters', function () {
        $user = User::factory()->create([
            'staff_department' => StaffDepartment::Chaplain,
            'staff_rank' => StaffRank::JrCrew,
            'staff_title' => 'Assistant Chaplain',
        ]);

        RemoveUsersStaffPosition::run($user);

        $activityLog = ActivityLog::first();

        expect($activityLog->subject_type)->toBe(User::class);
        expect($activityLog->subject_id)->toBe($user->id);
        expect($activityLog->action)->toBe('staff_position_removed');
        expect($activityLog->description)->not->toBeNull();
        expect($activityLog->description)->not->toBe('');
    });
});
