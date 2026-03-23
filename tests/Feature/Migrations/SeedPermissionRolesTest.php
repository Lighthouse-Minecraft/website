<?php

declare(strict_types=1);

use App\Models\Role;

uses()->group('roles', 'migrations');

$expectedRoles = [
    ['name' => 'Announcement - Editor', 'description' => 'Create, edit, and delete announcements', 'color' => 'cyan', 'icon' => 'megaphone'],
    ['name' => 'Page - Editor', 'description' => 'Edit and manage website content pages', 'color' => 'purple', 'icon' => 'newspaper'],
    ['name' => 'Moderator', 'description' => 'View flagged messages, moderator powers in Discussions, lock topics', 'color' => 'red', 'icon' => 'shield-exclamation'],
    ['name' => 'Brig Warden', 'description' => 'Handle brig appeals, release users from the brig', 'color' => 'orange', 'icon' => 'lock-closed'],
    ['name' => 'Membership Level - Manager', 'description' => 'Promote and demote users through membership levels', 'color' => 'emerald', 'icon' => 'arrow-trending-up'],
    ['name' => 'Meeting - Manager', 'description' => 'Write access to staff meetings', 'color' => 'blue', 'icon' => 'pencil-square'],
    ['name' => 'Community Stories - Manager', 'description' => 'Manage community questions and responses', 'color' => 'pink', 'icon' => 'chat-bubble-left-right'],
    ['name' => 'Discipline Report - Manager', 'description' => 'Create and manage discipline reports', 'color' => 'yellow', 'icon' => 'clipboard-document-list'],
    ['name' => 'Site Config - Manager', 'description' => 'Manage site configuration and application questions', 'color' => 'slate', 'icon' => 'cog-6-tooth'],
    ['name' => 'Logs - Viewer', 'description' => 'Access MC command log, Discord API log, and activity log', 'color' => 'zinc', 'icon' => 'document-magnifying-glass'],
    ['name' => 'User - Manager', 'description' => 'View and edit users, manage MC and Discord accounts in the ACP', 'color' => 'lime', 'icon' => 'users'],
    ['name' => 'PII - Viewer', 'description' => 'View personally identifiable information such as email addresses and dates of birth', 'color' => 'amber', 'icon' => 'eye'],
    ['name' => 'Ready Room - View All', 'description' => 'See all department ready rooms', 'color' => 'teal', 'icon' => 'building-office'],
    ['name' => 'Command Dashboard - Viewer', 'description' => 'Access the Command dashboard', 'color' => 'indigo', 'icon' => 'chart-bar'],
    ['name' => 'Blog - Author', 'description' => 'Create, edit, and manage blog posts', 'color' => 'violet', 'icon' => 'pencil-square'],
];

it('seeds all original roles with correct attributes after rename', function () use ($expectedRoles) {
    foreach ($expectedRoles as $expected) {
        $role = Role::where('name', $expected['name'])->first();

        expect($role)->not->toBeNull("Role '{$expected['name']}' should exist")
            ->and($role->description)->toBe($expected['description'])
            ->and($role->color)->toBe($expected['color'])
            ->and($role->icon)->toBe($expected['icon']);
    }
});

it('has all original role names matching the Feature - Tier specification', function () use ($expectedRoles) {
    $expectedNames = collect($expectedRoles)->pluck('name')->sort()->values();
    $actualNames = Role::whereIn('name', $expectedNames)->pluck('name')->sort()->values();

    expect($actualNames->toArray())->toBe($expectedNames->toArray());
});
