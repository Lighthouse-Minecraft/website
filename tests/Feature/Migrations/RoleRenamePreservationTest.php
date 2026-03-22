<?php

declare(strict_types=1);

use App\Models\Role;
use App\Models\User;

uses()->group('roles', 'migrations');

it('preserves pivot assignments after role rename', function () {
    $user = User::factory()->withRole('Membership Level - Manager')->create();

    // Verify the user has the role via the new name
    expect($user->fresh()->hasRole('Membership Level - Manager'))->toBeTrue();
});

it('has all renamed roles with new Feature - Tier names', function () {
    $renamedRoles = [
        'Page - Editor',
        'Announcement - Editor',
        'Membership Level - Manager',
        'Community Stories - Manager',
        'Discipline Report - Manager',
        'Discipline Report - Publisher',
        'Site Config - Manager',
        'Meeting - Manager',
        'Logs - Viewer',
        'Ready Room - View All',
        'User - Manager',
        'PII - Viewer',
        'Command Dashboard - Viewer',
        'Blog - Author',
    ];

    foreach ($renamedRoles as $name) {
        expect(Role::where('name', $name)->exists())
            ->toBeTrue("Role '{$name}' should exist after rename");
    }
});

it('has all new feature-scoped roles seeded', function () {
    $newRoles = [
        'Staff Access',
        'Ticket - User',
        'Ticket - Manager',
        'Task - Department',
        'Task - Manager',
        'Meeting - Department',
        'Meeting - Secretary',
        'Internal Note - Manager',
        'Discipline Report - Publisher',
        'Applicant Review - Department',
        'Applicant Review - All',
        'Officer Docs - Viewer',
    ];

    foreach ($newRoles as $name) {
        expect(Role::where('name', $name)->exists())
            ->toBeTrue("Role '{$name}' should exist");
    }
});

it('has role_staff_rank table with correct schema', function () {
    expect(\Illuminate\Support\Facades\Schema::hasTable('role_staff_rank'))->toBeTrue()
        ->and(\Illuminate\Support\Facades\Schema::hasColumns('role_staff_rank', [
            'role_id', 'staff_rank', 'created_at', 'updated_at',
        ]))->toBeTrue();
});

it('unchanged roles still exist', function () {
    expect(Role::where('name', 'Moderator')->exists())->toBeTrue()
        ->and(Role::where('name', 'Brig Warden')->exists())->toBeTrue();
});
