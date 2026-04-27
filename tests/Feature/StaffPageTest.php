<?php

declare(strict_types=1);

use App\Actions\AssignStaffPosition;
use App\Enums\StaffDepartment;
use App\Models\BoardMember;
use App\Models\StaffPosition;
use App\Models\User;
use App\Services\MinecraftRconService;
use Livewire\Volt\Volt;

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

it('displays board members section on the public page', function () {
    BoardMember::factory()->create([
        'display_name' => 'Board Test User',
        'title' => 'Board Chair',
    ]);

    $this->get(route('staff.index'))
        ->assertOk()
        ->assertSee('Board of Directors')
        ->assertSee('Board Test User')
        ->assertSee('Board Chair');
});

it('does not display board members section when no board members exist', function () {
    $this->get(route('staff.index'))
        ->assertOk()
        ->assertDontSee('Board of Directors');
});

it('shows linked board member with user staff name', function () {
    $user = User::factory()->create([
        'staff_first_name' => 'Jane',
        'staff_last_initial' => 'D',
    ]);
    BoardMember::factory()->linkedTo($user->id)->create([
        'display_name' => 'Fallback Name',
    ]);

    $this->get(route('staff.index'))
        ->assertOk()
        ->assertSee('Jane D.');
});

it('shows unlinked board member with display name', function () {
    BoardMember::factory()->create([
        'display_name' => 'External Board Member',
    ]);

    $this->get(route('staff.index'))
        ->assertOk()
        ->assertSee('External Board Member');
});

it('does not render the old sidebar panel markup', function () {
    $this->get(route('staff.index'))
        ->assertOk()
        ->assertDontSee('lg:w-1/4 lg:sticky', false);
});

it('selectPosition sets selectedPositionId and selectedPosition returns the correct model', function () {
    $position = StaffPosition::factory()->officer()->inDepartment(StaffDepartment::Command)->create([
        'title' => 'Unique Test Officer Title XYZ',
    ]);

    $component = Volt::test('staff.page');
    $component->call('selectPosition', $position->id);

    expect($component->get('selectedPositionId'))->toBe($position->id);
    $component->assertSee('Unique Test Officer Title XYZ');
});

it('renders markdown responsibilities as HTML in the modal content', function () {
    $position = StaffPosition::factory()->officer()->inDepartment(StaffDepartment::Command)->create([
        'responsibilities' => '**bold text**',
    ]);

    $component = Volt::test('staff.page');
    $component->call('selectPosition', $position->id);

    $component->assertSee('<strong>bold text</strong>', false);
});
