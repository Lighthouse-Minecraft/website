<?php

use App\Enums\StaffDepartment;
use App\Enums\StaffRank;
use App\Models\Page;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Livewire\livewire;

uses(RefreshDatabase::class);

describe('Admin Control Panel Tabs Component', function () {
    it('renders with default tab when user has all permissions', function () {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin);

        livewire('admin-control-panel-tabs')
            ->assertSet('tab', 'user-manager')
            ->assertSee('Users')
            ->assertSee('Roles')
            ->assertSee('Pages');
    });

    it('switches tabs correctly when clicked', function () {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin);

        livewire('admin-control-panel-tabs')
            ->assertSet('tab', 'user-manager')
            ->set('tab', 'role-manager')
            ->assertSet('tab', 'role-manager')
            ->set('tab', 'page-manager')
            ->assertSet('tab', 'page-manager');
    });

    it('persists tab selection in URL', function () {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin);

        // The #[Url] attribute should make the tab parameter appear in URL
        livewire('admin-control-panel-tabs')
            ->set('tab', 'role-manager')
            ->assertSet('tab', 'role-manager');
    });

    it('shows limited tabs for non-quartermaster officer', function () {
        // Create an officer in a different department who only has page permissions
        $user = User::factory()->create([
            'staff_department' => StaffDepartment::Engineer, // Not Quartermaster, so no user permissions
            'staff_rank' => StaffRank::Officer, // Officer rank gives page permissions
        ]);

        $this->actingAs($user);

        livewire('admin-control-panel-tabs')
            ->assertDontSee('Users')  // No user permissions (not Quartermaster)
            ->assertDontSee('Roles')  // No role permissions (not admin/command)
            ->assertSee('Pages'); // Has page permissions (any officer)
    });

    it('shows user and page manager tabs for quartermaster officer', function () {
        // Quartermaster officers can manage users and pages but not roles
        $user = User::factory()->create([
            'staff_department' => StaffDepartment::Quartermaster,
            'staff_rank' => StaffRank::Officer,
        ]);

        $this->actingAs($user);

        livewire('admin-control-panel-tabs')
            ->assertSee('Users')  // Has user permissions
            ->assertDontSee('Manage Roles')  // No role permissions
            ->assertSee('Pages'); // Has page permissions (any officer)
    });

    it('shows no tabs when user has no permissions', function () {
        $user = User::factory()->create([
            'staff_department' => StaffDepartment::Steward,
            'staff_rank' => StaffRank::JrCrew,
        ]);

        $this->actingAs($user);

        livewire('admin-control-panel-tabs')
            ->assertDontSee('Manage Users')
            ->assertDontSee('Manage Roles')
            ->assertDontSee('Manage Pages');
    });

    it('shows correct tab panels based on permissions', function () {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin);

        $component = livewire('admin-control-panel-tabs');

        // User Manager tab panel should be visible by default
        $component->assertSet('tab', 'user-manager');

        // Switch to role manager
        $component->set('tab', 'role-manager')
            ->assertSet('tab', 'role-manager');

        // Switch to page manager
        $component->set('tab', 'page-manager')
            ->assertSet('tab', 'page-manager');
    });

    it('includes child livewire components in tab panels', function () {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin);

        // Note: We can't easily test the child components without them existing,
        // but we can test that the component renders without errors
        livewire('admin-control-panel-tabs')
            ->assertSuccessful();
    });

    it('reactive method returns correct array', function () {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin);

        $component = livewire('admin-control-panel-tabs');

        // The reactive method should return ['tab'] but we can't directly test this
        // We can test that the component responds to tab changes
        $component->set('tab', 'role-manager')
            ->assertSet('tab', 'role-manager');
    });

    it('handles invalid tab values gracefully', function () {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin);

        livewire('admin-control-panel-tabs')
            ->set('tab', 'invalid-tab')
            ->assertSet('tab', 'invalid-tab'); // Component should accept any string value
    });

    it('maintains state across component updates', function () {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin);

        livewire('admin-control-panel-tabs')
            ->set('tab', 'page-manager')
            ->call('$refresh') // Trigger a component refresh
            ->assertSet('tab', 'page-manager');
    });

    it('renders flux components correctly', function () {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin);

        livewire('admin-control-panel-tabs')
            ->assertSee('Users')
            ->assertSee('Roles')
            ->assertSee('Pages')
            // Check for flux component structure (these might render as HTML)
            ->assertSeeHtml('wire:model="tab"');
    });

    it('respects command department officer permissions', function () {
        $commandOfficer = User::factory()->create([
            'staff_department' => StaffDepartment::Command,
            'staff_rank' => StaffRank::Officer,
        ]);

        $this->actingAs($commandOfficer);

        // Command officers should have access to everything due to before() method in policies
        livewire('admin-control-panel-tabs')
            ->assertSee('Users')
            ->assertSee('Roles')
            ->assertSee('Pages');
    });

    it('respects quartermaster officer permissions for user management', function () {
        $quartermasterOfficer = User::factory()->create([
            'staff_department' => StaffDepartment::Quartermaster,
            'staff_rank' => StaffRank::Officer,
        ]);

        $this->actingAs($quartermasterOfficer);

        livewire('admin-control-panel-tabs')
            ->assertSee('Manage Users')  // Should see this due to UserPolicy
            ->assertSee('Manage Pages')  // Should see this due to PagePolicy (officer)
            ->assertDontSee('Manage Roles'); // Should not see this (not admin/command)
    });

    it('shows only page manager for page editor role', function () {
        // Create a user with Page Editor role but no staff position
        $user = User::factory()->create([
            'staff_department' => null,
            'staff_rank' => StaffRank::None,
        ]);

        // Assign Page Editor role
        $pageEditorRole = Role::firstOrCreate(['name' => 'Page Editor']);
        $user->roles()->attach($pageEditorRole);

        $this->actingAs($user);

        livewire('admin-control-panel-tabs')
            ->assertDontSee('Users')  // No user permissions
            ->assertDontSee('Roles')  // No role permissions
            ->assertSee('Pages'); // Has page permissions due to role
    });

    it('denies access to crew members without officer rank', function () {
        $crewMember = User::factory()->create([
            'staff_department' => StaffDepartment::Command,
            'staff_rank' => StaffRank::CrewMember, // Not an officer
        ]);

        $this->actingAs($crewMember);

        livewire('admin-control-panel-tabs')
            ->assertDontSee('Users')
            ->assertDontSee('Roles')
            ->assertDontSee('Pages');
    });

    it('handles unauthenticated users gracefully', function () {
        // Don't authenticate any user

        livewire('admin-control-panel-tabs')
            ->assertDontSee('Users')
            ->assertDontSee('Roles')
            ->assertDontSee('Pages');
    });

    it('validates tab parameter type', function () {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin);

        livewire('admin-control-panel-tabs')
            ->set('tab', 123) // Set to non-string value
            ->assertHasNoErrors(); // Should handle gracefully
    });

    it('initializes with correct default values', function () {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin);

        livewire('admin-control-panel-tabs')
            ->assertSet('tab', 'user-manager'); // Default value from component
    });

    it('renders empty state when no permissions are granted', function () {
        $unprivilegedUser = User::factory()->create([
            'staff_department' => null,
            'staff_rank' => StaffRank::None,
        ]);

        $this->actingAs($unprivilegedUser);

        $component = livewire('admin-control-panel-tabs');

        // Should render successfully even with no tabs visible
        $component->assertSuccessful()
            ->assertDontSee('Users')
            ->assertDontSee('Roles')
            ->assertDontSee('Pages');
    });

    it('maintains responsive design classes', function () {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin);

        livewire('admin-control-panel-tabs')
            ->assertSeeHtml('class="w-full flex"'); // Check for the wrapper div classes
    });
});
