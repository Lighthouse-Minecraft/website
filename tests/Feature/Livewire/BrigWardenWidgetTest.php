<?php

declare(strict_types=1);

use App\Enums\ThreadStatus;
use App\Models\Thread;
use App\Models\User;
use App\Services\DiscordApiService;
use App\Services\FakeDiscordApiService;
use Livewire\Volt\Volt;

use function Pest\Laravel\actingAs;

uses()->group('brig', 'livewire');

beforeEach(function () {
    app()->instance(DiscordApiService::class, new FakeDiscordApiService);
});

// ─── Authorization ────────────────────────────────────────────────────────────

it('non-warden cannot mount the widget', function () {
    $viewer = User::factory()->create();

    actingAs($viewer);

    Volt::test('dashboard.brig-warden-widget')
        ->assertForbidden();
});

it('warden can mount the widget', function () {
    $warden = User::factory()->withRole('Brig Warden')->create();

    actingAs($warden);

    Volt::test('dashboard.brig-warden-widget')
        ->assertOk();
});

// ─── Approaching release list ─────────────────────────────────────────────────

it('approaching release list includes users expiring within 7 days', function () {
    $warden = User::factory()->withRole('Brig Warden')->create();
    $included = User::factory()->create([
        'in_brig' => true,
        'brig_reason' => 'Test',
        'brig_expires_at' => now()->addDays(3),
        'permanent_brig_at' => null,
    ]);

    actingAs($warden);

    $component = Volt::test('dashboard.brig-warden-widget');
    expect($component->instance()->approachingRelease->pluck('id'))->toContain($included->id);
});

it('approaching release list excludes users expiring beyond 7 days', function () {
    $warden = User::factory()->withRole('Brig Warden')->create();
    $excluded = User::factory()->create([
        'in_brig' => true,
        'brig_reason' => 'Test',
        'brig_expires_at' => now()->addDays(10),
        'permanent_brig_at' => null,
    ]);

    actingAs($warden);

    $component = Volt::test('dashboard.brig-warden-widget');
    expect($component->instance()->approachingRelease->pluck('id'))->not->toContain($excluded->id);
});

it('approaching release list excludes users with no expiry', function () {
    $warden = User::factory()->withRole('Brig Warden')->create();
    $excluded = User::factory()->create([
        'in_brig' => true,
        'brig_reason' => 'Test',
        'brig_expires_at' => null,
        'permanent_brig_at' => null,
    ]);

    actingAs($warden);

    $component = Volt::test('dashboard.brig-warden-widget');
    expect($component->instance()->approachingRelease->pluck('id'))->not->toContain($excluded->id);
});

it('approaching release list excludes permanently confined users', function () {
    $warden = User::factory()->withRole('Brig Warden')->create();
    $excluded = User::factory()->create([
        'in_brig' => true,
        'brig_reason' => 'Test',
        'brig_expires_at' => now()->addDays(3),
        'permanent_brig_at' => now(),
    ]);

    actingAs($warden);

    $component = Volt::test('dashboard.brig-warden-widget');
    expect($component->instance()->approachingRelease->pluck('id'))->not->toContain($excluded->id);
});

// ─── Open appeals badge ───────────────────────────────────────────────────────

it('open appeals count reflects open BrigAppeal threads', function () {
    $warden = User::factory()->withRole('Brig Warden')->create();
    $creator = User::factory()->create(['in_brig' => true, 'brig_reason' => 'Test']);

    Thread::factory()->brigAppeal()->create([
        'status' => ThreadStatus::Open,
        'created_by_user_id' => $creator->id,
    ]);

    actingAs($warden);

    $component = Volt::test('dashboard.brig-warden-widget');
    expect($component->instance()->openAppealsCount)->toBe(1);
});

it('closed BrigAppeal threads are not counted in open appeals', function () {
    $warden = User::factory()->withRole('Brig Warden')->create();
    $creator = User::factory()->create(['in_brig' => true, 'brig_reason' => 'Test']);

    Thread::factory()->brigAppeal()->create([
        'status' => ThreadStatus::Resolved,
        'created_by_user_id' => $creator->id,
    ]);

    actingAs($warden);

    $component = Volt::test('dashboard.brig-warden-widget');
    expect($component->instance()->openAppealsCount)->toBe(0);
});

// ─── Total brigged count ──────────────────────────────────────────────────────

it('total brigged count is accurate', function () {
    $warden = User::factory()->withRole('Brig Warden')->create();

    User::factory()->count(3)->create(['in_brig' => true, 'brig_reason' => 'Test']);

    actingAs($warden);

    $component = Volt::test('dashboard.brig-warden-widget');
    expect($component->instance()->totalBriggedCount)->toBe(3);
});

// ─── All-users modal ─────────────────────────────────────────────────────────

it('all brigged users list is searchable by name', function () {
    $warden = User::factory()->withRole('Brig Warden')->create();
    $findable = User::factory()->create(['name' => 'FindableUser', 'in_brig' => true, 'brig_reason' => 'Test']);
    $other = User::factory()->create(['name' => 'OtherUser', 'in_brig' => true, 'brig_reason' => 'Test']);

    actingAs($warden);

    $component = Volt::test('dashboard.brig-warden-widget')
        ->set('search', 'FindableUser');

    $ids = $component->instance()->allBriggedUsers->pluck('id');
    expect($ids)->toContain($findable->id)
        ->and($ids)->not->toContain($other->id);
});

it('widget is visible on dashboard for wardens', function () {
    $warden = User::factory()->withRole('Brig Warden')->create();

    actingAs($warden);

    Volt::test('dashboard.brig-warden-widget')
        ->assertSee('Brig Warden');
});
