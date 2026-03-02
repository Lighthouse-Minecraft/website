<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;

/**
 * Approved exception to TicketNotificationService guideline: This notification is sent to a
 * parent email address BEFORE a User account exists for the parent. It uses Laravel's
 * Notification::route('mail', $email) on-demand notification, so TicketNotificationService
 * (which requires a User model) cannot be used here.
 */
class ParentAccountNotification extends Notification implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $child,
        public bool $requiresApproval,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your Child Has Created a Lighthouse Account')
            ->markdown('mail.parent-account', [
                'childName' => $this->child->name,
                'requiresApproval' => $this->requiresApproval,
                'registerUrl' => route('register'),
            ]);
    }
}
