<?php

declare(strict_types=1);

use App\Enums\StaffDepartment;
use App\Enums\StaffRank;
use App\Models\MinecraftAccount;
use App\Models\User;

// ─── User::minecraftStaffPosition() ──────────────────────────────────────────

test('minecraftStaffPosition returns none when user has no department', function () {
    $user = User::factory()->create([
        'staff_department' => null,
        'staff_rank' => StaffRank::Officer,
    ]);

    expect($user->minecraftStaffPosition())->toBe('none');
});

test('minecraftStaffPosition returns department value for Officer', function () {
    $user = User::factory()->create([
        'staff_rank' => StaffRank::Officer,
        'staff_department' => StaffDepartment::Engineer,
    ]);

    expect($user->minecraftStaffPosition())->toBe('engineer');
});

test('minecraftStaffPosition returns department_crew for Crew Member', function () {
    $user = User::factory()->create([
        'staff_rank' => StaffRank::CrewMember,
        'staff_department' => StaffDepartment::Engineer,
    ]);

    expect($user->minecraftStaffPosition())->toBe('engineer_crew');
});

test('minecraftStaffPosition returns department_crew for Jr Crew', function () {
    $user = User::factory()->create([
        'staff_rank' => StaffRank::JrCrew,
        'staff_department' => StaffDepartment::Engineer,
    ]);

    expect($user->minecraftStaffPosition())->toBe('engineer_crew');
});

// ─── MinecraftAccount::syncUserCommand() ─────────────────────────────────────

test('syncUserCommand returns correct string for Java account', function () {
    $account = MinecraftAccount::factory()->java()->create(['username' => 'TestPlayer']);

    expect($account->syncUserCommand('traveler', 'engineer'))
        ->toBe('lh syncuser TestPlayer traveler engineer');
});

test('syncUserCommand returns correct string with none staff position for Java account', function () {
    $account = MinecraftAccount::factory()->java()->create(['username' => 'TestPlayer']);

    expect($account->syncUserCommand('resident', 'none'))
        ->toBe('lh syncuser TestPlayer resident none');
});

test('syncUserCommand returns correct string for Bedrock account with -bedrock suffix', function () {
    $account = MinecraftAccount::factory()->bedrock()->create([
        'username' => 'BedrockPlayer',
        'uuid' => '00000000-0000-0000-0009-01f4c5fb33de',
    ]);

    expect($account->syncUserCommand('traveler', 'engineer_crew'))
        ->toBe('lh syncuser BedrockPlayer traveler engineer_crew -bedrock 00000000-0000-0000-0009-01f4c5fb33de');
});
