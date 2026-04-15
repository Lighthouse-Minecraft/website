<?php

declare(strict_types=1);

use App\Actions\RecordActivity;
use App\Models\User;
use Livewire\Volt\Volt;

use function Pest\Laravel\actingAs;

uses()->group('brig', 'livewire');

// ─── Authorization ────────────────────────────────────────────────────────────

it('non-warden cannot mount the brig log page', function () {
    $viewer = User::factory()->create();

    actingAs($viewer);

    Volt::test('admin-manage-brig-log-page')
        ->assertForbidden();
});

it('warden can mount the brig log page', function () {
    $warden = User::factory()->withRole('Brig Warden')->create();

    actingAs($warden);

    Volt::test('admin-manage-brig-log-page')
        ->assertOk()
        ->assertSee('Brig Activity Log');
});

// ─── Log filtering ────────────────────────────────────────────────────────────

it('brig log shows user_put_in_brig entries', function () {
    $warden = User::factory()->withRole('Brig Warden')->create();
    $target = User::factory()->create(['in_brig' => true, 'brig_reason' => 'Test']);

    RecordActivity::handle($target, 'user_put_in_brig', 'Put in brig by admin.', $warden);

    actingAs($warden);

    $component = Volt::test('admin-manage-brig-log-page');
    expect($component->instance()->entries->total())->toBe(1);
});

it('brig log shows brig_status_updated entries', function () {
    $warden = User::factory()->withRole('Brig Warden')->create();
    $target = User::factory()->create(['in_brig' => true, 'brig_reason' => 'Test']);

    RecordActivity::handle($target, 'brig_status_updated', 'Status updated.', $warden);

    actingAs($warden);

    $component = Volt::test('admin-manage-brig-log-page');
    expect($component->instance()->entries->total())->toBe(1);
});

it('brig log shows permanent_brig_set entries', function () {
    $warden = User::factory()->withRole('Brig Warden')->create();
    $target = User::factory()->create(['in_brig' => true, 'brig_reason' => 'Test']);

    RecordActivity::handle($target, 'permanent_brig_set', 'Permanent confinement set.', $warden);

    actingAs($warden);

    $component = Volt::test('admin-manage-brig-log-page');
    expect($component->instance()->entries->total())->toBe(1);
});

it('brig log excludes non-brig activity entries', function () {
    $warden = User::factory()->withRole('Brig Warden')->create();
    $user = User::factory()->create();

    RecordActivity::handle($user, 'user_promoted', 'Promoted to Traveler.', $warden);

    actingAs($warden);

    $component = Volt::test('admin-manage-brig-log-page');
    expect($component->instance()->entries->total())->toBe(0);
});

it('brig log is ordered newest-first', function () {
    $warden = User::factory()->withRole('Brig Warden')->create();
    $target = User::factory()->create(['in_brig' => true, 'brig_reason' => 'Test']);

    $first = RecordActivity::handle($target, 'user_put_in_brig', 'First entry.', $warden);
    $second = RecordActivity::handle($target, 'brig_status_updated', 'Second entry.', $warden);

    actingAs($warden);

    $component = Volt::test('admin-manage-brig-log-page');
    $ids = $component->instance()->entries->pluck('id');

    // Newest first: second entry should appear before first entry
    expect($ids->first())->toBeGreaterThan($ids->last());
});
