<?php

declare(strict_types=1);

use App\Actions\PromoteUserToAdmin;
use App\Actions\RecordActivity;
use App\Models\User;

it('returns true when user is already an admin', function () {
    $user = User::factory()->admin()->create();

    $recordActivityMock = $this->mock(RecordActivity::class);
    $recordActivityMock->shouldNotReceive('handle');

    $result = PromoteUserToAdmin::run($user);

    expect($result)->toBeTrue()
        ->and($user->fresh()->isAdmin())->toBeTrue();
});

it('promotes user to admin when user is not admin', function () {
    $user = User::factory()->create();

    expect($user->isAdmin())->toBeFalse();

    $recordActivityMock = $this->mock(RecordActivity::class);
    $recordActivityMock->shouldReceive('handle')
        ->once()
        ->with($user, 'user_promoted_to_admin', 'Promoted to Admin role.');

    $result = PromoteUserToAdmin::run($user);

    expect($result)->toBeTrue()
        ->and($user->fresh()->isAdmin())->toBeTrue()
        ->and($user->fresh()->admin_granted_at)->not->toBeNull()
        ->and($user->fresh()->promoted_at)->not->toBeNull();
});

it('does not re-promote when user is already admin', function () {
    $user = User::factory()->admin()->create();
    $originalGrantedAt = $user->admin_granted_at;

    $recordActivityMock = $this->mock(RecordActivity::class);
    $recordActivityMock->shouldNotReceive('handle');

    $result = PromoteUserToAdmin::run($user);

    expect($result)->toBeTrue()
        ->and($user->fresh()->admin_granted_at->toDateTimeString())
        ->toBe($originalGrantedAt->toDateTimeString());
});
