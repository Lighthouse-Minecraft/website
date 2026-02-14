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
