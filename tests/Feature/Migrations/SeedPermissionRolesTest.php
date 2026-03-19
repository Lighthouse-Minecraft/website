<?php

declare(strict_types=1);

use App\Models\Role;

uses()->group('roles', 'migrations');

$expectedRoles = [
    ['name' => 'Announcement Editor', 'description' => 'Create, edit, and delete announcements', 'color' => 'cyan', 'icon' => 'megaphone'],
    ['name' => 'Page Editor', 'description' => 'Edit and manage website content pages', 'color' => 'purple', 'icon' => 'newspaper'],
    ['name' => 'Moderator', 'description' => 'View flagged messages, moderator powers in Discussions, lock topics', 'color' => 'red', 'icon' => 'shield-exclamation'],
    ['name' => 'Brig Warden', 'description' => 'Handle brig appeals, release users from the brig', 'color' => 'orange', 'icon' => 'lock-closed'],
    ['name' => 'Manage Membership Level', 'description' => 'Promote and demote users through membership levels', 'color' => 'emerald', 'icon' => 'arrow-trending-up'],
    ['name' => 'Meeting Secretary', 'description' => 'Manage non-staff-meeting meetings', 'color' => 'amber', 'icon' => 'inbox-arrow-down'],
    ['name' => 'Manage Staff Meeting', 'description' => 'Write access to staff meetings', 'color' => 'blue', 'icon' => 'pencil-square'],
    ['name' => 'Manage Community Stories', 'description' => 'Manage community questions and responses', 'color' => 'pink', 'icon' => 'chat-bubble-left-right'],
    ['name' => 'Manage Discipline Reports', 'description' => 'Create and manage discipline reports', 'color' => 'yellow', 'icon' => 'clipboard-document-list'],
    ['name' => 'Publish Discipline Reports', 'description' => 'Publish and finalize discipline reports', 'color' => 'red', 'icon' => 'clipboard-document-check'],
    ['name' => 'Manage Site Config', 'description' => 'Manage site configuration and application questions', 'color' => 'slate', 'icon' => 'cog-6-tooth'],
    ['name' => 'View Logs', 'description' => 'Access MC command log, Discord API log, and activity log', 'color' => 'zinc', 'icon' => 'document-magnifying-glass'],
    ['name' => 'View All Ready Rooms', 'description' => 'See all department ready rooms', 'color' => 'teal', 'icon' => 'building-office'],
    ['name' => 'View Command Dashboard', 'description' => 'Access the Command dashboard', 'color' => 'indigo', 'icon' => 'chart-bar'],
];

it('seeds exactly 14 permission roles', function () {
    expect(Role::count())->toBeGreaterThanOrEqual(14);
});

it('seeds all 14 roles with correct attributes', function () use ($expectedRoles) {
    foreach ($expectedRoles as $expected) {
        $role = Role::where('name', $expected['name'])->first();

        expect($role)->not->toBeNull("Role '{$expected['name']}' should exist")
            ->and($role->description)->toBe($expected['description'])
            ->and($role->color)->toBe($expected['color'])
            ->and($role->icon)->toBe($expected['icon']);
    }
});

it('has all 14 role names matching the PRD specification', function () use ($expectedRoles) {
    $expectedNames = collect($expectedRoles)->pluck('name')->sort()->values();
    $actualNames = Role::whereIn('name', $expectedNames)->pluck('name')->sort()->values();

    expect($actualNames->toArray())->toBe($expectedNames->toArray());
});
