<?php

declare(strict_types=1);

use App\Enums\BrigType;
use App\Models\ParentChildLink;
use App\Models\User;
use App\Services\MinecraftRconService;
use Illuminate\Support\Facades\Notification;

uses()->group('parent-portal', 'commands');

it('releases 13-year-olds with no parent from parental pending brig', function () {
    Notification::fake();
    $this->mock(MinecraftRconService::class)->shouldReceive('executeCommand')->andReturn(['success' => true, 'response' => null, 'error' => null]);

    $user = User::factory()->create([
        'in_brig' => true,
        'brig_type' => BrigType::ParentalPending,
        'brig_reason' => 'Pending parental approval',
        'date_of_birth' => now()->subYears(13)->subMonth(),
    ]);

    $this->artisan('parent-portal:process-age-transitions')->assertSuccessful();

    expect($user->fresh()->in_brig)->toBeFalse();
});

it('does not release 13-year-olds who have a linked parent', function () {
    Notification::fake();

    $parent = User::factory()->adult()->create();
    $child = User::factory()->create([
        'in_brig' => true,
        'brig_type' => BrigType::ParentalPending,
        'brig_reason' => 'Pending parental approval',
        'date_of_birth' => now()->subYears(13)->subMonth(),
    ]);
    ParentChildLink::factory()->create(['parent_user_id' => $parent->id, 'child_user_id' => $child->id]);

    $this->artisan('parent-portal:process-age-transitions')->assertSuccessful();

    expect($child->fresh()->in_brig)->toBeTrue();
});

it('does not release users under 13', function () {
    Notification::fake();

    $user = User::factory()->create([
        'in_brig' => true,
        'brig_type' => BrigType::ParentalPending,
        'brig_reason' => 'Pending parental approval',
        'date_of_birth' => now()->subYears(12)->subMonth(),
    ]);

    $this->artisan('parent-portal:process-age-transitions')->assertSuccessful();

    expect($user->fresh()->in_brig)->toBeTrue();
});

it('does not release users in discipline brig', function () {
    Notification::fake();

    $user = User::factory()->create([
        'in_brig' => true,
        'brig_type' => BrigType::Discipline,
        'brig_reason' => 'Bad behavior',
        'date_of_birth' => now()->subYears(14)->subMonth(),
    ]);

    $this->artisan('parent-portal:process-age-transitions')->assertSuccessful();

    expect($user->fresh()->in_brig)->toBeTrue();
});

it('releases 19-year-olds from parent links', function () {
    Notification::fake();
    $this->mock(MinecraftRconService::class)->shouldReceive('executeCommand')->andReturn(['success' => true, 'response' => null, 'error' => null]);

    $parent = User::factory()->adult()->create();
    $child = User::factory()->create([
        'date_of_birth' => now()->subYears(19)->subMonth(),
        'parent_email' => $parent->email,
    ]);
    ParentChildLink::factory()->create(['parent_user_id' => $parent->id, 'child_user_id' => $child->id]);

    $this->artisan('parent-portal:process-age-transitions')->assertSuccessful();

    expect(ParentChildLink::where('child_user_id', $child->id)->exists())->toBeFalse()
        ->and($child->fresh()->parent_email)->toBeNull();
});

it('does not release users under 19 from parent links', function () {
    $parent = User::factory()->adult()->create();
    $child = User::factory()->minor(17)->create([
        'parent_email' => $parent->email,
    ]);
    ParentChildLink::factory()->create(['parent_user_id' => $parent->id, 'child_user_id' => $child->id]);

    $this->artisan('parent-portal:process-age-transitions')->assertSuccessful();

    expect(ParentChildLink::where('child_user_id', $child->id)->exists())->toBeTrue();
});
