<?php

declare(strict_types=1);

use App\Notifications\TicketDigestNotification;

it('creates digest with ticket data', function () {
    $tickets = [
        ['subject' => 'Ticket 1', 'count' => 3],
        ['subject' => 'Ticket 2', 'count' => 1],
    ];

    $notification = new TicketDigestNotification($tickets);

    expect($notification->tickets)->toBe($tickets);
});

it('sends via mail channel only', function () {
    $tickets = [
        ['subject' => 'Ticket 1', 'count' => 3],
    ];

    $notification = new TicketDigestNotification($tickets);

    $channels = $notification->via(new stdClass);

    expect($channels)->toBe(['mail']);
});

it('is queued for background processing', function () {
    $notification = new TicketDigestNotification([]);

    expect($notification)->toBeInstanceOf(\Illuminate\Contracts\Queue\ShouldQueue::class);
});

it('formats email subject with current date', function () {
    $tickets = [
        ['subject' => 'Ticket 1', 'count' => 1],
    ];

    $notification = new TicketDigestNotification($tickets);
    $mail = $notification->toMail(new stdClass);

    expect($mail->subject)->toBe('Ticket Digest - '.now()->format('M j, Y'));
});

it('uses ticket-digest markdown template with correct data', function () {
    $tickets = [
        ['subject' => 'Server Down', 'count' => 3],
        ['subject' => 'Permission Issue', 'count' => 1],
    ];

    $notification = new TicketDigestNotification($tickets);
    $mail = $notification->toMail(new stdClass);

    expect($mail->markdown)->toBe('mail.ticket-digest')
        ->and($mail->viewData['displayedTickets'])->toHaveCount(2)
        ->and($mail->viewData['displayedTickets'][0]['subject'])->toBe('Server Down')
        ->and($mail->viewData['remainingCount'])->toBe(0);
});

it('truncates to 10 tickets maximum in view data', function () {
    $tickets = [];
    for ($i = 1; $i <= 15; $i++) {
        $tickets[] = ['subject' => "Ticket $i", 'count' => 1];
    }

    $notification = new TicketDigestNotification($tickets);
    $mail = $notification->toMail(new stdClass);

    expect($mail->viewData['displayedTickets'])->toHaveCount(10)
        ->and($mail->viewData['remainingCount'])->toBe(5);
});

it('calculates remaining count correctly for 11 tickets', function () {
    $tickets = [];
    for ($i = 1; $i <= 11; $i++) {
        $tickets[] = ['subject' => "Ticket $i", 'count' => 1];
    }

    $notification = new TicketDigestNotification($tickets);
    $mail = $notification->toMail(new stdClass);

    expect($mail->viewData['remainingCount'])->toBe(1);
});

it('has zero remaining when 10 or fewer tickets', function () {
    $tickets = [];
    for ($i = 1; $i <= 10; $i++) {
        $tickets[] = ['subject' => "Ticket $i", 'count' => 1];
    }

    $notification = new TicketDigestNotification($tickets);
    $mail = $notification->toMail(new stdClass);

    expect($mail->viewData['remainingCount'])->toBe(0);
});
