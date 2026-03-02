<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TicketDigestNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public array $tickets
    ) {}

    /**
     * Get the notification's delivery channels.
     */
    public function via(object $notifiable): array
    {
        // Digest only via email, not Pushover
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $displayedTickets = array_slice($this->tickets, 0, 10);
        $remainingCount = max(0, count($this->tickets) - 10);

        return (new MailMessage)
            ->subject('Ticket Digest - '.now()->format('M j, Y'))
            ->markdown('mail.ticket-digest', [
                'displayedTickets' => $displayedTickets,
                'remainingCount' => $remainingCount,
                'ticketsUrl' => route('tickets.index'),
            ]);
    }
}
