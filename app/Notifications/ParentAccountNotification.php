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
        $message = (new MailMessage)
            ->subject('Your Child Has Created a Lighthouse Account');

        if ($this->requiresApproval) {
            $message
                ->line("{$this->child->name} has requested an account on Lighthouse Minecraft, a Christian Minecraft community for youth.")
                ->line('Your approval is required before they can access the community. Because they are under 13, their account is currently on hold.')
                ->line('Create your own account to review and manage their permissions through the Parent Portal.')
                ->action('Create Your Account', route('register'));
        } else {
            $message
                ->line("{$this->child->name} has created an account on Lighthouse Minecraft, a Christian Minecraft community for youth.")
                ->line('As their parent or guardian, you can create your own account to manage their permissions, view their linked accounts, and monitor their activity through the Parent Portal.')
                ->action('Create Your Account', route('register'));
        }

        return $message;
    }
}
