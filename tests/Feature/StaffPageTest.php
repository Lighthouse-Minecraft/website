<?php

declare(strict_types=1);

use App\Actions\AssignStaffPosition;
use App\Enums\StaffDepartment;
use App\Models\StaffPosition;
use App\Models\User;
use App\Services\MinecraftRconService;

uses()->group('staff');

beforeEach(function () {
    $this->mock(MinecraftRconService::class)->shouldReceive('executeCommand')->andReturn(['success' => true, 'response' => null, 'error' => null]);
});

it('loads the public staff page without authentication', function () {
    $this->get(route('staff.index'))
        ->assertOk();
});

it('displays filled positions with user names', function () {
    $user = User::factory()->adult()->create(['name' => 'TestStaffUser']);
    $position = StaffPosition::factory()->officer()->inDepartment(StaffDepartment::Command)->create([
        'title' => 'Community Leader',
    ]);

    AssignStaffPosition::run($position, $user);

    $this->get(route('staff.index'))
        ->assertOk()
        ->assertSee('TestStaffUser')
        ->assertSee('Community Leader');
});

it('displays vacant positions as open', function () {
    StaffPosition::factory()->officer()->inDepartment(StaffDepartment::Engineer)->create([
        'title' => 'Senior Engineer',
    ]);

    $this->get(route('staff.index'))
        ->assertOk()
        ->assertSee('Open Position')
        ->assertSee('Senior Engineer');
});

it('groups positions by department', function () {
    StaffPosition::factory()->officer()->inDepartment(StaffDepartment::Command)->create();
    StaffPosition::factory()->officer()->inDepartment(StaffDepartment::Engineer)->create();

    $this->get(route('staff.index'))
        ->assertOk()
        ->assertSee('Command Department')
        ->assertSee('Engineer Department');
});

it('does not display departments with no positions', function () {
    StaffPosition::factory()->officer()->inDepartment(StaffDepartment::Command)->create();

    $response = $this->get(route('staff.index'));

    $response->assertOk()
        ->assertSee('Command Department')
        ->assertDontSee('Engineer Department');
});
