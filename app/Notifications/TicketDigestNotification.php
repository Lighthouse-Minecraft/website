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
        $message = (new MailMessage)
            ->subject('Ticket Digest - '.now()->format('M j, Y'))
            ->line('Here\'s a summary of ticket activity:');

        $displayedCount = 0;
        foreach ($this->tickets as $ticket) {
            if ($displayedCount >= 10) {
                break;
            }
            $message->line('• **'.$ticket['subject'].'** ('.$ticket['count'].' '
                .($ticket['count'] === 1 ? 'update' : 'updates').')');
            $displayedCount++;
        }

        $remaining = count($this->tickets) - $displayedCount;
        if ($remaining > 0) {
            $message->line('...and '.$remaining.' more '
                .($remaining === 1 ? 'ticket' : 'tickets'));
        }

        return $message
            ->action('View All Tickets', url('/tickets'))
            ->line('Thank you for your service!');
    }
}
