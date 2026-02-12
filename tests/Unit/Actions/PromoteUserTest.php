<?php

namespace Tests\Unit\Actions;

use App\Actions\PromoteUser;
use App\Enums\MembershipLevel;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('PromoteUser Action', function () {
    it('promotes a user to the next membership level', function () {

        $user = User::factory()->create([
            'membership_level' => MembershipLevel::Drifter,
        ]);

        PromoteUser::run($user);

        expect($user->fresh()->membership_level)->toBe(MembershipLevel::Stowaway);
        expect($user->fresh()->promoted_at)->not->toBeNull();
    });

    it('records activity when user is promoted', function () {
        $user = User::factory()->create([
            'membership_level' => MembershipLevel::Drifter,
        ]);

        PromoteUser::run($user);
        expect(ActivityLog::count())->toBe(1);

        $activityLog = ActivityLog::first();
        expect($activityLog->subject_id)->toBe($user->id);
        expect($activityLog->subject_type)->toBe(User::class);
        expect($activityLog->action)->toBe('user_promoted');
        expect($activityLog->description)->toBe('Promoted from Drifter to Stowaway.');
    });

    it('promotes user through multiple levels sequentially', function () {
        $user = User::factory()->create([
            'membership_level' => MembershipLevel::Drifter,
        ]);

        // Promote from Drifter to Stowaway
        PromoteUser::run($user);
        expect($user->fresh()->membership_level)->toBe(MembershipLevel::Stowaway);

        // Promote from Stowaway to Traveler
        PromoteUser::run($user);
        expect($user->fresh()->membership_level)->toBe(MembershipLevel::Traveler);

        // Promote from Traveler to Resident
        PromoteUser::run($user);
        expect($user->fresh()->membership_level)->toBe(MembershipLevel::Resident);

        // Promote from Resident to Citizen
        PromoteUser::run($user);
        expect($user->fresh()->membership_level)->toBe(MembershipLevel::Citizen);
    });

    it('respects the maximum level limit', function () {
        $user = User::factory()->create([
            'membership_level' => MembershipLevel::Drifter,
        ]);

        PromoteUser::run($user, MembershipLevel::Traveler);
        expect($user->fresh()->membership_level)->toBe(MembershipLevel::Stowaway);

        // Promote again, should go to Traveler
        PromoteUser::run($user, MembershipLevel::Traveler);
        expect($user->fresh()->membership_level)->toBe(MembershipLevel::Traveler);

        // Try to promote again, should not exceed Traveler
        PromoteUser::run($user, MembershipLevel::Traveler);
        expect($user->fresh()->membership_level)->toBe(MembershipLevel::Traveler);
    });

    it('does not promote user if already at or above maximum level', function () {
        $user = User::factory()->create([
            'membership_level' => MembershipLevel::Citizen,
        ]);

        $originalLevel = $user->membership_level;
        PromoteUser::run($user, MembershipLevel::Resident);

        expect($user->fresh()->membership_level)->toBe($originalLevel);
        expect(ActivityLog::count())->toBe(0);
    });

    it('does not promote user if already at the highest level', function () {
        $user = User::factory()->create([
            'membership_level' => MembershipLevel::Citizen,
        ]);

        $originalLevel = $user->membership_level;
        PromoteUser::run($user);

        expect($user->fresh()->membership_level)->toBe($originalLevel);
        expect(ActivityLog::count())->toBe(0);
    });

    it('uses default maximum level of Citizen when not specified', function () {
        $user = User::factory()->create([
            'membership_level' => MembershipLevel::Resident,
        ]);

        PromoteUser::run($user);
        expect($user->fresh()->membership_level)->toBe(MembershipLevel::Citizen);
    });

    it('handles edge case where user is already at maximum allowed level', function () {
        $user = User::factory()->create([
            'membership_level' => MembershipLevel::Traveler,
        ]);

        PromoteUser::run($user, MembershipLevel::Traveler);

        // Should not change since already at max allowed level
        expect($user->fresh()->membership_level)->toBe(MembershipLevel::Traveler);
        expect(ActivityLog::count())->toBe(0);
    });

    it('does not promote if next level would exceed maximum level', function () {
        $user = User::factory()->create([
            'membership_level' => MembershipLevel::Resident,
        ]);

        PromoteUser::run($user, MembershipLevel::Resident);

        // Should not promote because next level (Citizen) would exceed max (Resident)
        expect($user->fresh()->membership_level)->toBe(MembershipLevel::Resident);
        expect(ActivityLog::count())->toBe(0);
    });

    it('handles all membership levels correctly', function () {
        $levels = MembershipLevel::cases();

        expect(count($levels))->toBe(5);
        expect($levels)->toContain(MembershipLevel::Drifter);
        expect($levels)->toContain(MembershipLevel::Stowaway);
        expect($levels)->toContain(MembershipLevel::Traveler);
        expect($levels)->toContain(MembershipLevel::Resident);
        expect($levels)->toContain(MembershipLevel::Citizen);
    });

    it('maintains database consistency after promotion', function () {
        $user = User::factory()->create([
            'membership_level' => MembershipLevel::Drifter,
        ]);

        $originalUserId = $user->id;
        $originalUserName = $user->name;
        $originalUserEmail = $user->email;

        PromoteUser::run($user);

        $updatedUser = User::find($originalUserId);

        expect($updatedUser->id)->toBe($originalUserId);
        expect($updatedUser->name)->toBe($originalUserName);
        expect($updatedUser->email)->toBe($originalUserEmail);
        expect($updatedUser->membership_level)->toBe(MembershipLevel::Stowaway);
    });

    it('works with different starting levels', function () {
        $testCases = [
            [MembershipLevel::Drifter, MembershipLevel::Stowaway],
            [MembershipLevel::Stowaway, MembershipLevel::Traveler],
            [MembershipLevel::Traveler, MembershipLevel::Resident],
            [MembershipLevel::Resident, MembershipLevel::Citizen],
        ];

        foreach ($testCases as [$startLevel, $expectedNextLevel]) {
            $user = User::factory()->create([
                'membership_level' => $startLevel,
            ]);

            PromoteUser::run($user);

            expect($user->fresh()->membership_level)->toBe($expectedNextLevel);
        }
    });
});

describe('PromoteUser Action with Invalid data', function () {

    it('does not promote user with null membership level', function () {
        $user = User::factory()->create([
            'membership_level' => MembershipLevel::Drifter,
        ]);

        expect(fn () => PromoteUser::run($user, null))->toThrow(\TypeError::class);
    });

    it('does not promote user who is at max level', function () {
        $user = User::factory()->create([
            'membership_level' => MembershipLevel::Citizen,
        ]);

        $originalLevel = $user->membership_level;
        PromoteUser::run($user, MembershipLevel::Citizen);

        expect($user->fresh()->membership_level)->toBe($originalLevel);
        expect(ActivityLog::count())->toBe(0);
    });

    it('does not promote user if they are at the max requested level', function () {
        $user = User::factory()->create([
            'membership_level' => MembershipLevel::Citizen,
        ]);

        $originalLevel = $user->membership_level;
        PromoteUser::run($user, MembershipLevel::Citizen);

        expect($user->fresh()->membership_level)->toBe($originalLevel);
        expect(ActivityLog::count())->toBe(0);
    });
});
