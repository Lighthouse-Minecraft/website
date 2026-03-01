<?php

declare(strict_types=1);

use App\Models\User;
use App\Notifications\ParentAccountNotification;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;

uses()->group('parent-portal', 'notifications');

it('sends via mail channel', function () {
    $child = User::factory()->minor()->create();
    $notification = new ParentAccountNotification($child, requiresApproval: true);

    expect($notification->via(new AnonymousNotifiable))->toBe(['mail']);
});

it('includes approval language for under-13 child', function () {
    $child = User::factory()->underThirteen()->create(['name' => 'YoungChild']);
    $notification = new ParentAccountNotification($child, requiresApproval: true);

    $mail = $notification->toMail(new AnonymousNotifiable);

    expect($mail->subject)->toBe('Your Child Has Created a Lighthouse Account');

    $rendered = implode(' ', $mail->introLines);
    expect($rendered)->toContain('approval is required')
        ->and($rendered)->toContain('YoungChild');
});

it('includes informational language for 13+ child', function () {
    $child = User::factory()->minor(14)->create(['name' => 'TeenChild']);
    $notification = new ParentAccountNotification($child, requiresApproval: false);

    $mail = $notification->toMail(new AnonymousNotifiable);

    $rendered = implode(' ', $mail->introLines);
    expect($rendered)->toContain('has created an account')
        ->and($rendered)->not->toContain('approval is required');
});

it('can be sent as an on-demand notification', function () {
    Notification::fake();

    $child = User::factory()->minor()->create();
    $notification = new ParentAccountNotification($child, requiresApproval: true);

    Notification::route('mail', 'parent@example.com')->notify($notification);

    Notification::assertSentOnDemand(ParentAccountNotification::class);
});
