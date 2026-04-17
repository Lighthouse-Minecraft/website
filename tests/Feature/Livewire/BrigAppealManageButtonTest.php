<?php

declare(strict_types=1);

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

// ─── Manage Brig Status button in BrigAppeal thread view ─────────────────────

it('warden sees Manage Brig Status button on BrigAppeal thread', function () {
    $warden = User::factory()->withRole('Brig Warden')->create();
    $brigged = User::factory()->create(['in_brig' => true, 'brig_reason' => 'Test']);

    $thread = Thread::factory()->brigAppeal()->create([
        'created_by_user_id' => $brigged->id,
    ]);
    $thread->addParticipant($warden);
    $thread->addParticipant($brigged);

    actingAs($warden);

    Volt::test('topics.view-topic', ['thread' => $thread])
        ->assertSee('Manage Brig Status');
});

it('non-warden does not see Manage Brig Status button on BrigAppeal thread', function () {
    $viewer = User::factory()->withRole('Crew Member')->create();
    $brigged = User::factory()->create(['in_brig' => true, 'brig_reason' => 'Test']);

    $thread = Thread::factory()->brigAppeal()->create([
        'created_by_user_id' => $brigged->id,
    ]);
    $thread->addParticipant($viewer);
    $thread->addParticipant($brigged);

    actingAs($viewer);

    Volt::test('topics.view-topic', ['thread' => $thread])
        ->assertDontSee('Manage Brig Status');
});

it('Manage Brig Status button does not appear on regular Topic threads', function () {
    $warden = User::factory()->withRole('Brig Warden')->create();
    $author = User::factory()->create();

    $thread = Thread::factory()->topic()->create([
        'created_by_user_id' => $author->id,
    ]);
    $thread->addParticipant($warden);
    $thread->addParticipant($author);

    actingAs($warden);

    Volt::test('topics.view-topic', ['thread' => $thread])
        ->assertDontSee('Manage Brig Status');
});
