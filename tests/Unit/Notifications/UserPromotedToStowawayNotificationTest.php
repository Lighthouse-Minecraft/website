<?php

declare(strict_types=1);

use App\Models\User;
use App\Notifications\Channels\PushoverChannel;
use App\Notifications\UserPromotedToStowawayNotification;

it('sends via mail channel when mail is allowed', function () {
    $user = User::factory()->create();
    $notification = new UserPromotedToStowawayNotification($user);
    $notification->setChannels(['mail']);

    expect($notification->via(new stdClass))->toBe(['mail']);
});

it('sends via pushover channel when allowed and key is set', function () {
    $user = User::factory()->create();
    $notification = new UserPromotedToStowawayNotification($user);
    $notification->setChannels(['mail', 'pushover'], 'test-key');

    expect($notification->via(new stdClass))->toContain(PushoverChannel::class);
});

it('does not send via pushover without a key', function () {
    $user = User::factory()->create();
    $notification = new UserPromotedToStowawayNotification($user);
    $notification->setChannels(['mail', 'pushover'], null);

    expect($notification->via(new stdClass))->not->toContain(PushoverChannel::class);
});

it('is queued for background processing', function () {
    $user = User::factory()->create();
    $notification = new UserPromotedToStowawayNotification($user);

    expect($notification)->toBeInstanceOf(\Illuminate\Contracts\Queue\ShouldQueue::class);
});

it('toMail subject includes stowaway username', function () {
    $user = User::factory()->create(['name' => 'TestPlayer']);
    $notification = new UserPromotedToStowawayNotification($user);

    $mail = $notification->toMail($user);

    expect($mail->subject)->toContain('TestPlayer');
});

it('toMail body references the new stowaway', function () {
    $user = User::factory()->create(['name' => 'JohnDoe']);
    $notification = new UserPromotedToStowawayNotification($user);

    $mail = $notification->toMail($user);

    $content = implode(' ', $mail->introLines);
    expect($content)->toContain('JohnDoe');
});

it('toPushover title includes stowaway username', function () {
    $user = User::factory()->create(['name' => 'NewPlayer']);
    $notification = new UserPromotedToStowawayNotification($user);

    $pushover = $notification->toPushover($user);

    expect($pushover['title'])->toContain('NewPlayer');
});

it('toPushover message references the stowaway', function () {
    $user = User::factory()->create(['name' => 'AwesomePlayer']);
    $notification = new UserPromotedToStowawayNotification($user);

    $pushover = $notification->toPushover($user);

    expect($pushover['message'])->toContain('AwesomePlayer');
});

it('setChannels returns self for fluent chaining', function () {
    $user = User::factory()->create();
    $notification = new UserPromotedToStowawayNotification($user);

    $result = $notification->setChannels(['mail']);

    expect($result)->toBe($notification);
});
