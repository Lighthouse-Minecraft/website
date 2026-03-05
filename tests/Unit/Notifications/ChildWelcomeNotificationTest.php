<?php

declare(strict_types=1);

use App\Models\User;
use App\Notifications\Channels\PushoverChannel;
use App\Notifications\ChildWelcomeNotification;

uses()->group('notifications', 'parent-portal');

it('uses the child-welcome template', function () {
    $child = User::factory()->create();
    $parent = User::factory()->create();
    $notification = new ChildWelcomeNotification($child, $parent);

    $mail = $notification->toMail(new stdClass);

    expect($mail->markdown)->toBe('mail.child-welcome');
});

it('includes parent name in template data', function () {
    $parent = User::factory()->create(['name' => 'ParentUser']);
    $child = User::factory()->create(['name' => 'ChildUser']);
    $notification = new ChildWelcomeNotification($child, $parent);

    $mail = $notification->toMail(new stdClass);

    expect($mail->viewData['parentName'])->toBe('ParentUser')
        ->and($mail->viewData['childName'])->toBe('ChildUser');
});

it('has correct subject line', function () {
    $child = User::factory()->create();
    $parent = User::factory()->create();
    $notification = new ChildWelcomeNotification($child, $parent);

    $mail = $notification->toMail(new stdClass);

    expect($mail->subject)->toBe('Welcome to Lighthouse!');
});

it('pushover includes parent name', function () {
    $parent = User::factory()->create(['name' => 'ParentUser']);
    $child = User::factory()->create();
    $notification = new ChildWelcomeNotification($child, $parent);

    $pushover = $notification->toPushover(new stdClass);

    expect($pushover['title'])->toBe('Welcome to Lighthouse!')
        ->and($pushover['message'])->toContain('ParentUser');
});

it('sends via mail channel when allowed', function () {
    $child = User::factory()->create();
    $parent = User::factory()->create();
    $notification = new ChildWelcomeNotification($child, $parent);
    $notification->setChannels(['mail']);

    expect($notification->via(new stdClass))->toBe(['mail']);
});

it('sends via pushover channel when allowed and key is set', function () {
    $child = User::factory()->create();
    $parent = User::factory()->create();
    $notification = new ChildWelcomeNotification($child, $parent);
    $notification->setChannels(['mail', 'pushover'], 'test-key');

    expect($notification->via(new stdClass))->toContain(PushoverChannel::class);
});

it('is queued for background processing', function () {
    $child = User::factory()->create();
    $parent = User::factory()->create();
    $notification = new ChildWelcomeNotification($child, $parent);

    expect($notification)->toBeInstanceOf(\Illuminate\Contracts\Queue\ShouldQueue::class);
});
