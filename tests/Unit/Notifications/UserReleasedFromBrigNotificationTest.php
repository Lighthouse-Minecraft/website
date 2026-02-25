<?php

declare(strict_types=1);

use App\Models\User;
use App\Notifications\Channels\PushoverChannel;
use App\Notifications\UserReleasedFromBrigNotification;

it('sends via mail channel when mail is allowed', function () {
    $user = User::factory()->create();
    $notification = new UserReleasedFromBrigNotification($user);
    $notification->setChannels(['mail']);

    expect($notification->via(new stdClass))->toBe(['mail']);
});

it('sends via pushover channel when allowed and key is set', function () {
    $user = User::factory()->create();
    $notification = new UserReleasedFromBrigNotification($user);
    $notification->setChannels(['mail', 'pushover'], 'test-key');

    expect($notification->via(new stdClass))->toContain(PushoverChannel::class);
});

it('does not send via pushover without a key', function () {
    $user = User::factory()->create();
    $notification = new UserReleasedFromBrigNotification($user);
    $notification->setChannels(['mail', 'pushover'], null);

    expect($notification->via(new stdClass))->not->toContain(PushoverChannel::class);
});

it('is queued for background processing', function () {
    $user = User::factory()->create();
    $notification = new UserReleasedFromBrigNotification($user);

    expect($notification)->toBeInstanceOf(\Illuminate\Contracts\Queue\ShouldQueue::class);
});

it('toMail has correct subject', function () {
    $user = User::factory()->create();
    $notification = new UserReleasedFromBrigNotification($user);

    $mail = $notification->toMail($user);

    expect($mail->subject)->toBe('You Have Been Released from the Brig');
});

it('toMail mentions account restoration', function () {
    $user = User::factory()->create();
    $notification = new UserReleasedFromBrigNotification($user);

    $mail = $notification->toMail($user);

    $content = implode(' ', $mail->introLines);
    expect($content)->toContain('released from the Brig');
});

it('toPushover has correct title', function () {
    $user = User::factory()->create();
    $notification = new UserReleasedFromBrigNotification($user);

    $pushover = $notification->toPushover($user);

    expect($pushover['title'])->toBe('Released from the Brig!');
});

it('toPushover includes dashboard url', function () {
    $user = User::factory()->create();
    $notification = new UserReleasedFromBrigNotification($user);

    $pushover = $notification->toPushover($user);

    expect($pushover['url'])->toContain('dashboard');
});

it('setChannels returns self for fluent chaining', function () {
    $user = User::factory()->create();
    $notification = new UserReleasedFromBrigNotification($user);

    $result = $notification->setChannels(['mail']);

    expect($result)->toBe($notification);
});
