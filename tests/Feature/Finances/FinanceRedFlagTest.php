<?php

declare(strict_types=1);

use App\Enums\StaffRank;
use App\Models\FinancialPeriodReport;
use App\Models\Meeting;
use App\Models\User;
use Livewire\Volt\Volt;

uses()->group('finances', 'finance-red-flag');

it('shows red flag when no period report published in last 14 days', function () {
    $user = User::factory()->withRole('Meeting - Manager')->create([
        'staff_rank' => StaffRank::Officer,
    ]);
    $meeting = Meeting::factory()->create();
    $this->actingAs($user);

    // No published reports at all
    Volt::test('meetings.manage-meeting', ['meeting' => $meeting])
        ->assertSee('Finance Report Overdue');
});

it('shows red flag when last published report was more than 14 days ago', function () {
    $user = User::factory()->withRole('Meeting - Manager')->create([
        'staff_rank' => StaffRank::Officer,
    ]);
    $meeting = Meeting::factory()->create();
    $this->actingAs($user);

    FinancialPeriodReport::factory()->published()->create([
        'published_at' => now()->subDays(15),
    ]);

    Volt::test('meetings.manage-meeting', ['meeting' => $meeting])
        ->assertSee('Finance Report Overdue');
});

it('does not show red flag when a period report was published within last 14 days', function () {
    $user = User::factory()->withRole('Meeting - Manager')->create([
        'staff_rank' => StaffRank::Officer,
    ]);
    $meeting = Meeting::factory()->create();
    $this->actingAs($user);

    FinancialPeriodReport::factory()->published()->create([
        'published_at' => now()->subDays(7),
    ]);

    Volt::test('meetings.manage-meeting', ['meeting' => $meeting])
        ->assertDontSee('Finance Report Overdue');
});

it('does not show red flag when a period report was published today', function () {
    $user = User::factory()->withRole('Meeting - Manager')->create([
        'staff_rank' => StaffRank::Officer,
    ]);
    $meeting = Meeting::factory()->create();
    $this->actingAs($user);

    FinancialPeriodReport::factory()->published()->create([
        'published_at' => now(),
    ]);

    Volt::test('meetings.manage-meeting', ['meeting' => $meeting])
        ->assertDontSee('Finance Report Overdue');
});

it('finance red flag method returns true when no recent report', function () {
    $user = User::factory()->withRole('Meeting - Manager')->create([
        'staff_rank' => StaffRank::Officer,
    ]);
    $meeting = Meeting::factory()->create();
    $this->actingAs($user);

    $component = Volt::test('meetings.manage-meeting', ['meeting' => $meeting]);

    expect($component->instance()->financeRedFlag())->toBeTrue();
});

it('finance red flag method returns false when recent report exists', function () {
    $user = User::factory()->withRole('Meeting - Manager')->create([
        'staff_rank' => StaffRank::Officer,
    ]);
    $meeting = Meeting::factory()->create();
    $this->actingAs($user);

    FinancialPeriodReport::factory()->published()->create([
        'published_at' => now()->subDays(3),
    ]);

    $component = Volt::test('meetings.manage-meeting', ['meeting' => $meeting]);

    expect($component->instance()->financeRedFlag())->toBeFalse();
});
