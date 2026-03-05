<?php

use App\Enums\StaffDepartment;
use App\Enums\StaffRank;
use App\Models\StaffPosition;
use App\Models\User;
use App\Services\MinecraftRconService;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->mock(MinecraftRconService::class)->shouldReceive('executeCommand')->andReturn(['success' => true, 'response' => null, 'error' => null]);
});

it('mounts the component and loads staff position relationship', function () {
    $user = User::factory()->create();
    $position = StaffPosition::factory()->officer()->inDepartment(StaffDepartment::Command)->assignedTo($user->id)->create([
        'title' => 'Event Organizer',
    ]);

    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    livewire('users.display-basic-details', ['user' => $user])
        ->assertSee('Event Organizer')
        ->assertSee('Command');
});

it('allows admin to assign a staff position', function () {
    $user = User::factory()->adult()->create();
    $position = StaffPosition::factory()->officer()->inDepartment(StaffDepartment::Command)->create([
        'title' => 'Fleet Admiral',
    ]);

    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    livewire('users.display-basic-details', ['user' => $user])
        ->call('assignToPosition', $position->id);

    expect($position->fresh()->user_id)->toBe($user->id)
        ->and($user->fresh()->staff_title)->toBe('Fleet Admiral');
});

it('allows admin to remove a staff position', function () {
    $user = User::factory()->adult()->create([
        'staff_rank' => StaffRank::Officer,
        'staff_department' => StaffDepartment::Command,
        'staff_title' => 'Captain',
    ]);
    $position = StaffPosition::factory()->officer()->inDepartment(StaffDepartment::Command)->assignedTo($user->id)->create();

    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    livewire('users.display-basic-details', ['user' => $user])
        ->call('removeFromPosition');

    expect($position->fresh()->user_id)->toBeNull()
        ->and($user->fresh()->staff_rank)->toBe(StaffRank::None);
});
