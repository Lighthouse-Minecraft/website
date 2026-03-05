<?php

declare(strict_types=1);

use App\Models\User;
use App\Notifications\Channels\PushoverChannel;
use App\Notifications\ParentAccountEnabledNotification;

uses()->group('notifications', 'parent-portal');

it('uses the parent-account-enabled template', function () {
    $child = User::factory()->create();
    $parent = User::factory()->create();
    $notification = new ParentAccountEnabledNotification($child, $parent);

    $mail = $notification->toMail(new stdClass);

    expect($mail->markdown)->toBe('mail.parent-account-enabled');
});

it('includes parent name and dashboard url in template data', function () {
    $parent = User::factory()->create(['name' => 'ParentUser']);
    $child = User::factory()->create();
    $notification = new ParentAccountEnabledNotification($child, $parent);

    $mail = $notification->toMail(new stdClass);

    expect($mail->viewData['parentName'])->toBe('ParentUser')
        ->and($mail->viewData['dashboardUrl'])->toBe(route('dashboard'));
});

it('has correct subject line', function () {
    $child = User::factory()->create();
    $parent = User::factory()->create();
    $notification = new ParentAccountEnabledNotification($child, $parent);

    $mail = $notification->toMail(new stdClass);

    expect($mail->subject)->toBe('Your Account Has Been Enabled');
});

it('pushover includes parent name', function () {
    $parent = User::factory()->create(['name' => 'ParentUser']);
    $child = User::factory()->create();
    $notification = new ParentAccountEnabledNotification($child, $parent);

    $pushover = $notification->toPushover(new stdClass);

    expect($pushover['title'])->toBe('Account Enabled!')
        ->and($pushover['message'])->toContain('ParentUser')
        ->and($pushover['message'])->toContain('enabled');
});

it('sends via mail channel when allowed', function () {
    $child = User::factory()->create();
    $parent = User::factory()->create();
    $notification = new ParentAccountEnabledNotification($child, $parent);
    $notification->setChannels(['mail']);

    expect($notification->via(new stdClass))->toBe(['mail']);
});

it('sends via pushover channel when allowed and key is set', function () {
    $child = User::factory()->create();
    $parent = User::factory()->create();
    $notification = new ParentAccountEnabledNotification($child, $parent);
    $notification->setChannels(['mail', 'pushover'], 'test-key');

    expect($notification->via(new stdClass))->toContain(PushoverChannel::class);
});

it('is queued for background processing', function () {
    $child = User::factory()->create();
    $parent = User::factory()->create();
    $notification = new ParentAccountEnabledNotification($child, $parent);

    expect($notification)->toBeInstanceOf(\Illuminate\Contracts\Queue\ShouldQueue::class);
});
