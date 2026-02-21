<?php

declare(strict_types=1);

use App\Models\User;
use App\Notifications\BrigTimerExpiredNotification;
use App\Notifications\Channels\PushoverChannel;

it('sends via mail channel when mail is allowed', function () {
    $user = User::factory()->create();
    $notification = new BrigTimerExpiredNotification($user);
    $notification->setChannels(['mail']);

    expect($notification->via(new stdClass))->toBe(['mail']);
});

it('sends via pushover channel when allowed and key is set', function () {
    $user = User::factory()->create();
    $notification = new BrigTimerExpiredNotification($user);
    $notification->setChannels(['mail', 'pushover'], 'test-key');

    expect($notification->via(new stdClass))->toContain(PushoverChannel::class);
});

it('does not send via pushover without a key', function () {
    $user = User::factory()->create();
    $notification = new BrigTimerExpiredNotification($user);
    $notification->setChannels(['mail', 'pushover'], null);

    expect($notification->via(new stdClass))->not->toContain(PushoverChannel::class);
});

it('is queued for background processing', function () {
    $user = User::factory()->create();
    $notification = new BrigTimerExpiredNotification($user);

    expect($notification)->toBeInstanceOf(\Illuminate\Contracts\Queue\ShouldQueue::class);
});

it('toMail has correct subject', function () {
    $user = User::factory()->create();
    $notification = new BrigTimerExpiredNotification($user);

    $mail = $notification->toMail($user);

    expect($mail->subject)->toBe('Your Brig Period Has Ended — You May Now Appeal');
});

it('toMail mentions appeal can be submitted', function () {
    $user = User::factory()->create();
    $notification = new BrigTimerExpiredNotification($user);

    $mail = $notification->toMail($user);

    $content = implode(' ', $mail->introLines);
    expect($content)->toContain('appeal');
});

it('toPushover has correct title', function () {
    $user = User::factory()->create();
    $notification = new BrigTimerExpiredNotification($user);

    $pushover = $notification->toPushover($user);

    expect($pushover['title'])->toBe('Brig Period Ended');
});

it('toPushover includes dashboard url', function () {
    $user = User::factory()->create();
    $notification = new BrigTimerExpiredNotification($user);

    $pushover = $notification->toPushover($user);

    expect($pushover['url'])->toContain('dashboard');
});

it('toPushover message mentions appeal', function () {
    $user = User::factory()->create();
    $notification = new BrigTimerExpiredNotification($user);

    $pushover = $notification->toPushover($user);

    expect($pushover['message'])->toContain('appeal');
});

it('setChannels returns self for fluent chaining', function () {
    $user = User::factory()->create();
    $notification = new BrigTimerExpiredNotification($user);

    $result = $notification->setChannels(['mail']);

    expect($result)->toBe($notification);
});
