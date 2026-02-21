<?php

declare(strict_types=1);

use App\Models\User;
use App\Notifications\Channels\PushoverChannel;
use App\Notifications\UserPutInBrigNotification;

it('sends via mail channel when mail is allowed', function () {
    $user = User::factory()->create();
    $notification = new UserPutInBrigNotification($user, 'Bad behavior');
    $notification->setChannels(['mail']);

    expect($notification->via(new stdClass))->toBe(['mail']);
});

it('sends via pushover channel when allowed and key is set', function () {
    $user = User::factory()->create();
    $notification = new UserPutInBrigNotification($user, 'Bad behavior');
    $notification->setChannels(['mail', 'pushover'], 'test-key');

    expect($notification->via(new stdClass))->toContain(PushoverChannel::class);
});

it('does not send via pushover without a key', function () {
    $user = User::factory()->create();
    $notification = new UserPutInBrigNotification($user, 'Bad behavior');
    $notification->setChannels(['mail', 'pushover'], null);

    expect($notification->via(new stdClass))->not->toContain(PushoverChannel::class);
});

it('is queued for background processing', function () {
    $user = User::factory()->create();
    $notification = new UserPutInBrigNotification($user, 'Bad behavior');

    expect($notification)->toBeInstanceOf(\Illuminate\Contracts\Queue\ShouldQueue::class);
});

it('toMail contains brig reason', function () {
    $user = User::factory()->create();
    $notification = new UserPutInBrigNotification($user, 'Griefing the spawn');

    $mail = $notification->toMail($user);

    expect(implode(' ', $mail->introLines))->toContain('Griefing the spawn');
});

it('toMail includes appeal-now message when no expiry set', function () {
    $user = User::factory()->create();
    $notification = new UserPutInBrigNotification($user, 'Bad behavior', null);

    $mail = $notification->toMail($user);

    $content = implode(' ', $mail->introLines);
    expect($content)->toContain('appeal at any time');
});

it('toMail includes expiry date when expiresAt is set', function () {
    $user = User::factory()->create();
    $expiresAt = now()->addDays(7);
    $notification = new UserPutInBrigNotification($user, 'Bad behavior', $expiresAt);

    $mail = $notification->toMail($user);

    $content = implode(' ', $mail->introLines);
    expect($content)->toContain('Appeal available after');
});

it('toPushover includes reason in message', function () {
    $user = User::factory()->create();
    $notification = new UserPutInBrigNotification($user, 'Toxic chat');

    $pushover = $notification->toPushover($user);

    expect($pushover['message'])->toContain('Toxic chat')
        ->and($pushover['title'])->toBe('Placed in the Brig');
});

it('toPushover includes expiry when set', function () {
    $user = User::factory()->create();
    $expiresAt = now()->addDays(30);
    $notification = new UserPutInBrigNotification($user, 'Bad behavior', $expiresAt);

    $pushover = $notification->toPushover($user);

    expect($pushover['message'])->toContain('appeal after');
});

it('toPushover includes appeal-now message when no expiry', function () {
    $user = User::factory()->create();
    $notification = new UserPutInBrigNotification($user, 'Bad behavior', null);

    $pushover = $notification->toPushover($user);

    expect($pushover['message'])->toContain('any time');
});

it('setChannels returns self for fluent chaining', function () {
    $user = User::factory()->create();
    $notification = new UserPutInBrigNotification($user, 'Test');

    $result = $notification->setChannels(['mail']);

    expect($result)->toBe($notification);
});
