<?php

declare(strict_types=1);

use App\Models\User;
use App\Notifications\Channels\PushoverChannel;
use App\Notifications\ParentAccountDisabledNotification;

uses()->group('notifications', 'parent-portal');

it('uses the parent-account-disabled template', function () {
    $child = User::factory()->create();
    $parent = User::factory()->create();
    $notification = new ParentAccountDisabledNotification($child, $parent);

    $mail = $notification->toMail(new stdClass);

    expect($mail->markdown)->toBe('mail.parent-account-disabled');
});

it('includes parent name in template data', function () {
    $parent = User::factory()->create(['name' => 'ParentUser']);
    $child = User::factory()->create();
    $notification = new ParentAccountDisabledNotification($child, $parent);

    $mail = $notification->toMail(new stdClass);

    expect($mail->viewData['parentName'])->toBe('ParentUser');
});

it('has correct subject line', function () {
    $child = User::factory()->create();
    $parent = User::factory()->create();
    $notification = new ParentAccountDisabledNotification($child, $parent);

    $mail = $notification->toMail(new stdClass);

    expect($mail->subject)->toBe('Your Account Has Been Restricted');
});

it('pushover includes parent name', function () {
    $parent = User::factory()->create(['name' => 'ParentUser']);
    $child = User::factory()->create();
    $notification = new ParentAccountDisabledNotification($child, $parent);

    $pushover = $notification->toPushover(new stdClass);

    expect($pushover['title'])->toBe('Account Restricted')
        ->and($pushover['message'])->toContain('ParentUser');
});

it('sends via mail channel when allowed', function () {
    $child = User::factory()->create();
    $parent = User::factory()->create();
    $notification = new ParentAccountDisabledNotification($child, $parent);
    $notification->setChannels(['mail']);

    expect($notification->via(new stdClass))->toBe(['mail']);
});

it('sends via pushover channel when allowed and key is set', function () {
    $child = User::factory()->create();
    $parent = User::factory()->create();
    $notification = new ParentAccountDisabledNotification($child, $parent);
    $notification->setChannels(['mail', 'pushover'], 'test-key');

    expect($notification->via(new stdClass))->toContain(PushoverChannel::class);
});

it('is queued for background processing', function () {
    $child = User::factory()->create();
    $parent = User::factory()->create();
    $notification = new ParentAccountDisabledNotification($child, $parent);

    expect($notification)->toBeInstanceOf(\Illuminate\Contracts\Queue\ShouldQueue::class);
});
