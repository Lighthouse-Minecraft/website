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

    public string $childName;

    /**
     * @param  User|string  $child  A User model or the child's display name (for under-13 COPPA flow where no account is created)
     */
    public function __construct(
        User|string $child,
        public bool $requiresApproval,
    ) {
        $this->childName = $child instanceof User ? $child->name : $child;
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your Child Has Created a Lighthouse Account')
            ->markdown('mail.parent-account', [
                'childName' => $this->childName,
                'requiresApproval' => $this->requiresApproval,
                'registerUrl' => route('register'),
            ]);
    }
}
