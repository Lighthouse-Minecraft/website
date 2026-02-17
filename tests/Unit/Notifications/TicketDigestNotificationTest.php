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

it('formats email body with ticket bullets', function () {
    $tickets = [
        ['subject' => 'Server Down', 'count' => 3],
        ['subject' => 'Permission Issue', 'count' => 1],
    ];

    $notification = new TicketDigestNotification($tickets);
    $mail = $notification->toMail(new stdClass);

    expect($mail->introLines)->toContain('Here\'s a summary of ticket activity:')
        ->and($mail->introLines)->toContain('• **Server Down** (3 updates)')
        ->and($mail->introLines)->toContain('• **Permission Issue** (1 update)');
});

it('uses correct singular/plural for update count', function () {
    $tickets = [
        ['subject' => 'One Update', 'count' => 1],
        ['subject' => 'Many Updates', 'count' => 5],
    ];

    $notification = new TicketDigestNotification($tickets);
    $mail = $notification->toMail(new stdClass);

    expect($mail->introLines)->toContain('• **One Update** (1 update)')
        ->and($mail->introLines)->toContain('• **Many Updates** (5 updates)');
});

it('truncates to 10 tickets maximum', function () {
    $tickets = [];
    for ($i = 1; $i <= 15; $i++) {
        $tickets[] = ['subject' => "Ticket $i", 'count' => 1];
    }

    $notification = new TicketDigestNotification($tickets);
    $mail = $notification->toMail(new stdClass);

    // Should show tickets 1-10
    expect($mail->introLines)->toContain('• **Ticket 1** (1 update)')
        ->and($mail->introLines)->toContain('• **Ticket 10** (1 update)');

    // Should NOT show ticket 11
    $hasTicket11 = false;
    foreach ($mail->introLines as $line) {
        if (str_contains($line, 'Ticket 11')) {
            $hasTicket11 = true;
            break;
        }
    }
    expect($hasTicket11)->toBeFalse();
});

it('shows "and N more" message when more than 10 tickets', function () {
    $tickets = [];
    for ($i = 1; $i <= 15; $i++) {
        $tickets[] = ['subject' => "Ticket $i", 'count' => 1];
    }

    $notification = new TicketDigestNotification($tickets);
    $mail = $notification->toMail(new stdClass);

    expect($mail->introLines)->toContain('...and 5 more tickets');
});

it('uses correct singular/plural for remaining ticket count', function () {
    // Test with exactly 11 tickets (1 remaining)
    $tickets = [];
    for ($i = 1; $i <= 11; $i++) {
        $tickets[] = ['subject' => "Ticket $i", 'count' => 1];
    }

    $notification = new TicketDigestNotification($tickets);
    $mail = $notification->toMail(new stdClass);

    expect($mail->introLines)->toContain('...and 1 more ticket');

    // Test with 12 tickets (2 remaining)
    $tickets = [];
    for ($i = 1; $i <= 12; $i++) {
        $tickets[] = ['subject' => "Ticket $i", 'count' => 1];
    }

    $notification = new TicketDigestNotification($tickets);
    $mail = $notification->toMail(new stdClass);

    expect($mail->introLines)->toContain('...and 2 more tickets');
});

it('does not show "and N more" when 10 or fewer tickets', function () {
    $tickets = [];
    for ($i = 1; $i <= 10; $i++) {
        $tickets[] = ['subject' => "Ticket $i", 'count' => 1];
    }

    $notification = new TicketDigestNotification($tickets);
    $mail = $notification->toMail(new stdClass);

    $hasMoreMessage = false;
    foreach ($mail->introLines as $line) {
        if (str_contains($line, '...and') && str_contains($line, 'more ticket')) {
            $hasMoreMessage = true;
            break;
        }
    }
    expect($hasMoreMessage)->toBeFalse();
});
