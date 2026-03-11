<?php

declare(strict_types=1);

use App\Actions\ToggleCommunityReaction;
use App\Models\CommunityReaction;
use App\Models\CommunityResponse;
use App\Models\User;

uses()->group('community-stories', 'actions');

it('adds a reaction', function () {
    $user = loginAs(User::factory()->create());
    $response = CommunityResponse::factory()->approved()->create();

    $result = ToggleCommunityReaction::run($response, $user, '❤️');

    expect($result)->toBeTrue();
    $this->assertDatabaseHas('community_reactions', [
        'community_response_id' => $response->id,
        'user_id' => $user->id,
        'emoji' => '❤️',
    ]);
});

it('removes an existing reaction', function () {
    $user = loginAs(User::factory()->create());
    $response = CommunityResponse::factory()->approved()->create();

    ToggleCommunityReaction::run($response, $user, '❤️');
    $result = ToggleCommunityReaction::run($response, $user, '❤️');

    expect($result)->toBeFalse();
    $this->assertDatabaseMissing('community_reactions', [
        'community_response_id' => $response->id,
        'user_id' => $user->id,
        'emoji' => '❤️',
    ]);
});

it('allows multiple different emojis from same user', function () {
    $user = loginAs(User::factory()->create());
    $response = CommunityResponse::factory()->approved()->create();

    ToggleCommunityReaction::run($response, $user, '❤️');
    ToggleCommunityReaction::run($response, $user, '🙏');

    expect(CommunityReaction::where('user_id', $user->id)->where('community_response_id', $response->id)->count())->toBe(2);
});

it('rejects emoji not in allowed set', function () {
    $user = loginAs(User::factory()->create());
    $response = CommunityResponse::factory()->approved()->create();

    ToggleCommunityReaction::run($response, $user, '💀');
})->throws(\InvalidArgumentException::class);
