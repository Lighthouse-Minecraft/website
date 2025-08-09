<?php

use App\Enums\StaffDepartment;
use App\Enums\StaffRank;
use App\Models\User;

use function Pest\Livewire\livewire;

it('mounts the component and sets initial state', function () {
    $user = User::factory()->create([
        'staff_department' => StaffDepartment::Command,
        'staff_rank' => StaffRank::Officer,
        'staff_title' => 'Event Organizer',
    ]);

    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    livewire('users.display-basic-details', ['user' => $user])
        ->assertSet('currentTitle', 'Event Organizer')
        ->assertSet('currentDepartment', 'Command')
        ->assertSet('currentDepartmentValue', StaffDepartment::Command->value)
        ->assertSet('currentRank', StaffRank::Officer->value);
});
