<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\DiscordApiService;
use App\Services\FakeDiscordApiService;
use App\Services\MinecraftRconService;
use Livewire\Volt\Volt;

use function Pest\Laravel\actingAs;

uses()->group('brig', 'livewire');

beforeEach(function () {
    app()->instance(DiscordApiService::class, new FakeDiscordApiService);
});

// ─── Authorization ────────────────────────────────────────────────────────────

it('non-warden cannot mount the component', function () {
    $viewer = User::factory()->create();
    $target = User::factory()->create(['in_brig' => true, 'brig_reason' => 'Test']);

    actingAs($viewer);

    Volt::test('brig.brig-status-manager', ['user' => $target])
        ->assertForbidden();
});

it('warden can mount the component', function () {
    $warden = User::factory()->withRole('Brig Warden')->create();
    $target = User::factory()->create(['in_brig' => true, 'brig_reason' => 'Test reason']);

    actingAs($warden);

    Volt::test('brig.brig-status-manager', ['user' => $target])
        ->assertSet('brigReason', 'Test reason')
        ->assertSet('brigNotify', true);
});

// ─── Reason edit ─────────────────────────────────────────────────────────────

it('warden can update the brig reason', function () {
    $warden = User::factory()->withRole('Brig Warden')->create();
    $target = User::factory()->create(['in_brig' => true, 'brig_reason' => 'Old reason']);

    actingAs($warden);

    Volt::test('brig.brig-status-manager', ['user' => $target])
        ->set('brigReason', 'New updated reason')
        ->call('saveStatus')
        ->assertHasNoErrors();

    expect($target->fresh()->brig_reason)->toBe('New updated reason');
});

it('reason is required and at least 5 chars', function () {
    $warden = User::factory()->withRole('Brig Warden')->create();
    $target = User::factory()->create(['in_brig' => true, 'brig_reason' => 'Old reason']);

    actingAs($warden);

    Volt::test('brig.brig-status-manager', ['user' => $target])
        ->set('brigReason', 'Hi')
        ->call('saveStatus')
        ->assertHasErrors(['brigReason']);
});

it('non-warden cannot call saveStatus', function () {
    $viewer = User::factory()->create();
    $target = User::factory()->create(['in_brig' => true, 'brig_reason' => 'Test', 'brig_type' => null]);

    actingAs($viewer);

    // Mount will throw 403 before we can even call saveStatus
    Volt::test('brig.brig-status-manager', ['user' => $target])
        ->assertForbidden();
});

// ─── Permanent toggle ─────────────────────────────────────────────────────────

it('permanent toggle mounts as true when user has permanent_brig_at', function () {
    $warden = User::factory()->withRole('Brig Warden')->create();
    $target = User::factory()->create([
        'in_brig' => true,
        'brig_reason' => 'Dupe account',
        'permanent_brig_at' => now(),
    ]);

    actingAs($warden);

    Volt::test('brig.brig-status-manager', ['user' => $target])
        ->assertSet('brigPermanent', true);
});

it('changing brigPermanent forces brigNotify to true', function () {
    $warden = User::factory()->withRole('Brig Warden')->create();
    $target = User::factory()->create([
        'in_brig' => true,
        'brig_reason' => 'Test reason',
        'permanent_brig_at' => null,
    ]);

    actingAs($warden);

    Volt::test('brig.brig-status-manager', ['user' => $target])
        ->set('brigNotify', false)
        ->set('brigPermanent', true)
        ->assertSet('brigNotify', true);
});

// ─── Quick release ────────────────────────────────────────────────────────────

it('quick release requires a reason', function () {
    $warden = User::factory()->withRole('Brig Warden')->create();
    $target = User::factory()->create(['in_brig' => true, 'brig_reason' => 'Test']);

    actingAs($warden);

    Volt::test('brig.brig-status-manager', ['user' => $target])
        ->set('brigReleaseReason', '')
        ->call('quickRelease')
        ->assertHasErrors(['brigReleaseReason']);
});

it('quick release releases the user from brig', function () {
    $warden = User::factory()->withRole('Brig Warden')->create();
    $target = User::factory()->create(['in_brig' => true, 'brig_reason' => 'Test']);

    $this->mock(MinecraftRconService::class)->shouldReceive('executeCommand')->andReturn(['success' => true, 'response' => null, 'error' => null]);

    actingAs($warden);

    Volt::test('brig.brig-status-manager', ['user' => $target])
        ->set('brigReleaseReason', 'Good behavior by user')
        ->call('quickRelease')
        ->assertHasNoErrors();

    expect($target->fresh()->in_brig)->toBeFalse();
});

// ─── Profile page embed ───────────────────────────────────────────────────────

it('profile page shows Manage Brig Status item for wardens when user is brigged', function () {
    $warden = User::factory()->withRole('Brig Warden')->create();
    $target = User::factory()->create(['in_brig' => true, 'brig_reason' => 'Test']);

    actingAs($warden);

    Volt::test('users.display-basic-details', ['user' => $target])
        ->assertSee('Manage Brig Status');
});

it('profile page does not show Manage Brig Status for non-wardens', function () {
    $viewer = User::factory()->create();
    $target = User::factory()->create(['in_brig' => true, 'brig_reason' => 'Test']);

    actingAs($viewer);

    Volt::test('users.display-basic-details', ['user' => $target])
        ->assertDontSee('Manage Brig Status');
});
