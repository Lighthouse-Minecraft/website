<?php

use App\Enums\StaffDepartment;
use App\Enums\StaffRank;
use App\Models\Role;
use App\Models\StaffPosition;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Livewire\livewire;

uses(RefreshDatabase::class);

describe('Admin Control Panel Tabs Component', function () {
    it('renders with default category and tab when user has all permissions', function () {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin);

        livewire('admin-control-panel-tabs')
            ->assertSet('category', 'users')
            ->assertSet('tab', 'user-manager')
            ->assertSee('Users')
            ->assertSee('Content')
            ->assertSee('Logs')
            ->assertSee('Config');
    });

    it('switches categories correctly', function () {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin);

        livewire('admin-control-panel-tabs')
            ->assertSet('category', 'users')
            ->set('category', 'logs')
            ->assertSet('category', 'logs')
            ->assertSet('tab', 'mc-command-log');
    });

    it('resets sub-tab when category changes', function () {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin);

        livewire('admin-control-panel-tabs')
            ->set('category', 'content')
            ->assertSet('tab', 'page-manager')
            ->set('category', 'config')
            ->assertSet('tab', 'site-settings')
            ->set('category', 'users')
            ->assertSet('tab', 'user-manager');
    });

    it('switches sub-tabs within a category', function () {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin);

        livewire('admin-control-panel-tabs')
            ->assertSet('tab', 'user-manager')
            ->set('tab', 'mc-user-manager')
            ->assertSet('tab', 'mc-user-manager')
            ->set('tab', 'discord-user-manager')
            ->assertSet('tab', 'discord-user-manager');
    });

    it('allows setting category and tab independently', function () {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin);

        livewire('admin-control-panel-tabs')
            ->set('category', 'logs')
            ->assertSet('category', 'logs')
            ->set('tab', 'activity-log')
            ->assertSet('tab', 'activity-log');
    });

    it('shows only categories the user has access to', function () {
        $user = User::factory()->create([
            'staff_department' => StaffDepartment::Engineer,
            'staff_rank' => StaffRank::Officer,
        ]);

        $this->actingAs($user);

        // Officer sees Users (via UserPolicy) and Content, but not Logs (now role-based)
        livewire('admin-control-panel-tabs')
            ->assertSee('Users')
            ->assertSee('Content')
            ->assertDontSee('Logs')
            ->assertSee('Config');
    });

    it('shows logs category for user with View Logs role', function () {
        $user = User::factory()
            ->withStaffPosition(StaffDepartment::Engineer, StaffRank::JrCrew)
            ->withRole('View Logs')
            ->create();

        $this->actingAs($user);

        livewire('admin-control-panel-tabs')
            ->assertSee('Content')
            ->assertSee('Logs');
    });

    it('defaults to content category when user lacks users permissions', function () {
        $user = User::factory()->create([
            'staff_department' => StaffDepartment::Engineer,
            'staff_rank' => StaffRank::JrCrew,
        ]);

        $this->actingAs($user);

        livewire('admin-control-panel-tabs')
            ->assertSet('category', 'content')
            ->assertDontSee('Users')
            ->assertSee('Content')
            ->assertDontSee('Logs');
    });

    it('shows only content for users with no staff position', function () {
        // AnnouncementPolicy::viewAny returns true for all authenticated users
        $user = User::factory()->create([
            'staff_department' => null,
            'staff_rank' => StaffRank::None,
        ]);

        $this->actingAs($user);

        livewire('admin-control-panel-tabs')
            ->assertDontSee('Users')
            ->assertSee('Content')
            ->assertDontSee('Logs')
            ->assertDontSee('Config');
    });

    it('includes child livewire components in tab panels', function () {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin);

        livewire('admin-control-panel-tabs')
            ->assertSuccessful();
    });

    it('handles unauthenticated users gracefully', function () {
        livewire('admin-control-panel-tabs')
            ->assertDontSee('Users')
            ->assertDontSee('Content')
            ->assertDontSee('Logs')
            ->assertDontSee('Config');
    });

    it('shows only content category for page editor role', function () {
        $user = User::factory()->create([
            'staff_department' => null,
            'staff_rank' => StaffRank::None,
        ]);

        $position = StaffPosition::factory()->assignedTo($user->id)->create();
        $pageEditorRole = Role::firstOrCreate(['name' => 'Page Editor']);
        $position->roles()->attach($pageEditorRole);

        $this->actingAs($user);

        livewire('admin-control-panel-tabs')
            ->assertDontSee('Users')
            ->assertSee('Content')
            ->assertDontSee('Logs')
            ->assertDontSee('Config');
    });

    // TODO: Re-enable after PRD #280 policy refactor — command officers need roles assigned via positions
    it('respects admin permissions for all categories', function () {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin);

        livewire('admin-control-panel-tabs')
            ->assertSee('Users')
            ->assertSee('Content')
            ->assertSee('Logs')
            ->assertSee('Config');
    });

    it('maintains wrapper div classes', function () {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin);

        livewire('admin-control-panel-tabs')
            ->assertSeeHtml('class="w-full flex flex-col gap-4"');
    });
});
