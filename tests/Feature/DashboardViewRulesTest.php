<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Role;
use App\Models\ActivityLog;
use App\Enums\MembershipLevel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Livewire\Volt\Volt as LivewireVolt;
use Tests\TestCase;

class DashboardViewRulesTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $stowawayRole;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test user with Drifter level and no rules acceptance
        $this->user = User::factory()->create([
            'membership_level' => MembershipLevel::Drifter,
            'rules_accepted_at' => null,
        ]);

        // Create required roles
        $this->stowawayRole = Role::create([
            'name' => 'Stowaway',
            'description' => 'New member role',
            'color' => 'blue',
            'icon' => 'user'
        ]);
    }

    public function test_dashboard_page_renders_with_view_rules_component_for_authenticated_users(): void
    {
        $response = $this->actingAs($this->user)
            ->get('/dashboard');

        $response->assertStatus(200)
                 ->assertSee('livewire:dashboard.view-rules');
    }

    public function test_dashboard_redirects_unauthenticated_users_to_login(): void
    {
        $response = $this->get('/dashboard');

        $response->assertRedirect('/login');
    }

    public function test_view_rules_component_can_be_rendered(): void
    {
        $component = LivewireVolt::test('dashboard.view-rules')
            ->actingAs($this->user);

        $component->assertStatus(200);
    }

    public function test_shows_primary_button_for_users_who_have_not_accepted_rules(): void
    {
        $component = LivewireVolt::test('dashboard.view-rules')
            ->actingAs($this->user);

        $component->assertSee('Read & Accept Rules');
    }

    public function test_shows_secondary_button_for_users_who_have_accepted_rules(): void
    {
        $this->user->update(['rules_accepted_at' => now()]);

        $component = LivewireVolt::test('dashboard.view-rules')
            ->actingAs($this->user);

        $component->assertSee('View Rules')
                  ->assertDontSee('Read & Accept Rules');
    }

    public function test_displays_community_rules_content(): void
    {
        $component = LivewireVolt::test('dashboard.view-rules')
            ->actingAs($this->user);

        $component->assertSee('Lighthouse Community Rules')
                  ->assertSee('Honor God')
                  ->assertSee('Be Respectful of Others')
                  ->assertSee('Keep Language Clean')
                  ->assertSee('In-Game Conduct')
                  ->assertSee('Duping Rules');
    }

    public function test_displays_biblical_quotes_in_the_rules(): void
    {
        $component = LivewireVolt::test('dashboard.view-rules')
            ->actingAs($this->user);

        $component->assertSee('Love one another with brotherly affection')
                  ->assertSee('Romans 12:10 (ESV)')
                  ->assertSee('You shall love the Lord your God')
                  ->assertSee('Matthew 22:37–39 (ESV)');
    }

    public function test_shows_accept_button_for_users_who_have_not_accepted_rules(): void
    {
        $component = LivewireVolt::test('dashboard.view-rules')
            ->actingAs($this->user);

        $component->assertSee('I Have Read the Rules and Agree to Follow Them');
    }

    public function test_shows_accept_button_for_drifter_level_users_even_if_they_accepted_before(): void
    {
        $this->user->update([
            'rules_accepted_at' => now(),
            'membership_level' => MembershipLevel::Drifter,
        ]);

        $component = LivewireVolt::test('dashboard.view-rules')
            ->actingAs($this->user);

        $component->assertSee('I Have Read the Rules and Agree to Follow Them');
    }

    public function test_hides_accept_button_for_users_who_have_accepted_rules_and_are_above_drifter_level(): void
    {
        $this->user->update([
            'rules_accepted_at' => now(),
            'membership_level' => MembershipLevel::Stowaway,
        ]);

        $component = LivewireVolt::test('dashboard.view-rules')
            ->actingAs($this->user);

        $component->assertDontSee('I Have Read the Rules and Agree to Follow Them');
    }

    public function test_allows_users_to_accept_rules_and_updates_timestamp(): void
    {
        $component = LivewireVolt::test('dashboard.view-rules')
            ->actingAs($this->user);

        $this->assertNull($this->user->fresh()->rules_accepted_at);

        $component->call('acceptRules');

        $this->assertNotNull($this->user->fresh()->rules_accepted_at);
    }

    public function test_promotes_user_to_stowaway_when_accepting_rules(): void
    {
        $this->assertEquals(MembershipLevel::Drifter, $this->user->membership_level);

        $component = LivewireVolt::test('dashboard.view-rules')
            ->actingAs($this->user);

        $component->call('acceptRules');

        $this->assertEquals(MembershipLevel::Stowaway, $this->user->fresh()->membership_level);
    }

    public function test_records_activity_when_user_accepts_rules(): void
    {
        $component = LivewireVolt::test('dashboard.view-rules')
            ->actingAs($this->user);

        $component->call('acceptRules');

        $this->assertDatabaseHas('activity_logs', [
            'causer_id' => $this->user->id,
            'action' => 'rules_accepted',
            'description' => 'User accepted community rules and was promoted to Stowaway',
        ]);
    }

    public function test_only_allows_authenticated_users_to_accept_rules(): void
    {
        $component = LivewireVolt::test('dashboard.view-rules');

        $component->call('acceptRules')
                  ->assertRedirect('/login');
    }

    public function test_handles_multiple_rule_acceptance_attempts_gracefully(): void
    {
        $component = LivewireVolt::test('dashboard.view-rules')
            ->actingAs($this->user);

        // Accept rules first time
        $component->call('acceptRules');
        $firstAcceptedAt = $this->user->fresh()->rules_accepted_at;

        // Try to accept again
        $component->call('acceptRules');
        $secondAcceptedAt = $this->user->fresh()->rules_accepted_at;

        // Should have updated the timestamp
        $this->assertGreaterThanOrEqual($firstAcceptedAt, $secondAcceptedAt);
        $this->assertEquals(MembershipLevel::Stowaway, $this->user->fresh()->membership_level);
    }

    public function test_clears_cache_when_user_accepts_rules(): void
    {
        Cache::put('user:' . $this->user->id . ':is_stowaway', false);

        $component = LivewireVolt::test('dashboard.view-rules')
            ->actingAs($this->user);

        $component->call('acceptRules');

        $this->assertNull(Cache::get('user:' . $this->user->id . ':is_stowaway'));
    }

    public function test_maintains_proper_component_state_after_accepting_rules(): void
    {
        $component = LivewireVolt::test('dashboard.view-rules')
            ->actingAs($this->user);

        // Before accepting rules
        $component->assertSee('Read & Accept Rules');

        // Accept rules
        $component->call('acceptRules');

        // After accepting - should show different button text when re-rendered
        $this->user->refresh();
        $newComponent = LivewireVolt::test('dashboard.view-rules')
            ->actingAs($this->user);

        $newComponent->assertSee('View Rules')
                     ->assertDontSee('Read & Accept Rules');
    }

    public function test_displays_rules_component_within_app_layout(): void
    {
        $response = $this->actingAs($this->user)
            ->get('/dashboard');

        $response->assertStatus(200)
                 ->assertSee('livewire:dashboard.view-rules');
    }

    public function test_maintains_authentication_requirements_for_dashboard_access(): void
    {
        $response = $this->get('/dashboard');
        $response->assertRedirect('/login');

        $response = $this->actingAs($this->user)
            ->get('/dashboard');
        $response->assertStatus(200);
    }

    public function test_includes_necessary_content_for_rules_component(): void
    {
        $response = $this->actingAs($this->user)
            ->get('/dashboard');

        $response->assertStatus(200)
                 ->assertSee('Lighthouse Community Rules')
                 ->assertSee('Read & Accept Rules');
    }

    public function test_dashboard_works_with_users_at_different_membership_levels(): void
    {
        // Test with Stowaway level user
        $stowawayUser = User::factory()->create([
            'membership_level' => MembershipLevel::Stowaway,
            'rules_accepted_at' => now(),
        ]);

        $response = $this->actingAs($stowawayUser)
            ->get('/dashboard');

        $response->assertStatus(200)
                 ->assertSee('View Rules')
                 ->assertDontSee('I Have Read the Rules and Agree to Follow Them');
    }

    public function test_dashboard_component_handles_flux_modals_correctly(): void
    {
        $response = $this->actingAs($this->user)
            ->get('/dashboard');

        $response->assertStatus(200)
                 ->assertSee('flux:modal.trigger')
                 ->assertSee('view-rules-modal');
    }

    public function test_component_requires_stowaway_role_to_exist(): void
    {
        // Delete the role to test error handling
        $this->stowawayRole->delete();

        $component = LivewireVolt::test('dashboard.view-rules')
            ->actingAs($this->user);

        // This should gracefully handle missing role
        $component->call('acceptRules');

        // User should still get timestamp updated even if role assignment fails
        $this->assertNotNull($this->user->fresh()->rules_accepted_at);
    }
}
