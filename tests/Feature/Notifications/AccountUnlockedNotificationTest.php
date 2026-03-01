<?php

declare(strict_types=1);

use App\Models\User;
use App\Notifications\AccountUnlockedNotification;
use App\Notifications\Channels\DiscordChannel;
use App\Notifications\Channels\PushoverChannel;

uses()->group('parent-portal', 'notifications');

it('defaults to mail channel only', function () {
    $notification = new AccountUnlockedNotification;
    $user = User::factory()->create();

    expect($notification->via($user))->toBe(['mail']);
});

it('includes pushover channel when configured', function () {
    $notification = new AccountUnlockedNotification;
    $notification->setChannels(['mail', 'pushover'], 'test-pushover-key');

    $user = User::factory()->create();

    expect($notification->via($user))->toContain('mail')
        ->and($notification->via($user))->toContain(PushoverChannel::class)
        ->and($notification->getPushoverKey())->toBe('test-pushover-key');
});

it('includes discord channel when configured', function () {
    $notification = new AccountUnlockedNotification;
    $notification->setChannels(['mail', 'discord']);

    $user = User::factory()->create();

    expect($notification->via($user))->toContain(DiscordChannel::class);
});

it('does not include pushover without key', function () {
    $notification = new AccountUnlockedNotification;
    $notification->setChannels(['mail', 'pushover']);

    $user = User::factory()->create();

    expect($notification->via($user))->not->toContain(PushoverChannel::class);
});

it('generates mail with correct subject', function () {
    $notification = new AccountUnlockedNotification;
    $user = User::factory()->create();

    $mail = $notification->toMail($user);

    expect($mail->subject)->toBe('Your Lighthouse Account Has Been Unlocked!');
});

it('generates pushover payload', function () {
    $notification = new AccountUnlockedNotification;
    $user = User::factory()->create();

    $payload = $notification->toPushover($user);

    expect($payload['title'])->toBe('Account Unlocked!');
});

it('generates discord message', function () {
    $notification = new AccountUnlockedNotification;
    $user = User::factory()->create();

    $message = $notification->toDiscord($user);

    expect($message)->toContain('Account Unlocked!');
});
