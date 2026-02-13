<?php

use App\Enums\EmailDigestFrequency;
use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;

new class extends Component
{
    public string $pushover_key = '';

    public string $email_digest_frequency = '';

    public int $pushover_monthly_count = 0;

    public ?string $pushover_count_reset_at = null;

    // Notification preferences
    public bool $notify_tickets_email = true;

    public bool $notify_tickets_pushover = false;

    public function mount(): void
    {
        $user = Auth::user();
        $this->pushover_key = $user->pushover_key ?? '';
        $this->email_digest_frequency = $user->email_digest_frequency?->value ?? EmailDigestFrequency::Immediate->value;
        $this->pushover_monthly_count = $user->pushover_monthly_count ?? 0;
        $this->pushover_count_reset_at = $user->pushover_count_reset_at?->format('M j, Y');

        // Load notification preferences
        $preferences = $user->notification_preferences ?? [];
        $this->notify_tickets_email = $preferences['tickets']['email'] ?? true;
        $this->notify_tickets_pushover = $preferences['tickets']['pushover'] ?? false;
    }

    public function updateNotificationSettings(): void
    {
        $user = Auth::user();

        $validated = $this->validate([
            'pushover_key' => ['nullable', 'string', 'max:255'],
            'email_digest_frequency' => ['required', 'in:immediate,daily,weekly'],
            'notify_tickets_email' => ['boolean'],
            'notify_tickets_pushover' => ['boolean'],
        ]);

        $user->pushover_key = $validated['pushover_key'];
        $user->email_digest_frequency = EmailDigestFrequency::from($validated['email_digest_frequency']);

        // Merge notification preferences to preserve other categories
        $preferences = $user->notification_preferences ?? [];
        $preferences['tickets'] = [
            'email' => $validated['notify_tickets_email'],
            'pushover' => $validated['notify_tickets_pushover'],
        ];
        $user->notification_preferences = $preferences;

        $user->save();

        Flux::toast('Notification settings updated successfully!', variant: 'success');
    }
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <form wire:submit="updateNotificationSettings" class="mt-6 space-y-6">
        <flux:fieldset>
            <flux:legend>Email Notifications</flux:legend>
            <flux:description>
                Choose how often you'd like to receive email notifications for ticket activity.
            </flux:description>

            <div class="mt-4 space-y-3">
                <flux:radio.group wire:model="email_digest_frequency" variant="cards">
                    <flux:radio value="immediate" label="Immediate" description="Get notified right away when there's activity" />
                    <flux:radio value="daily" label="Daily Digest" description="Receive a daily summary of ticket activity" />
                    <flux:radio value="weekly" label="Weekly Digest" description="Receive a weekly summary of ticket activity" />
                </flux:radio.group>
            </div>
        </flux:fieldset>

        <flux:fieldset>
            <flux:legend>Pushover Notifications (Optional)</flux:legend>
            <flux:description>
                Enter your Pushover user key to receive mobile push notifications. Limited to 10,000 notifications per month.
                <a href="https://pushover.net" target="_blank" class="text-blue-600 hover:text-blue-800 dark:text-blue-400">Get your Pushover key</a>
            </flux:description>

            <flux:field class="mt-4">
                <flux:label>Pushover User Key</flux:label>
                <flux:input wire:model="pushover_key" type="text" placeholder="Enter your Pushover user key" />
            </flux:field>

            @if($pushover_monthly_count > 0)
                <div class="mt-3 text-sm text-zinc-600 dark:text-zinc-400">
                    <strong>Usage this month:</strong> {{ number_format($pushover_monthly_count) }} / 10,000 notifications
                    @if($pushover_count_reset_at)
                        <span class="text-xs">(Resets {{ $pushover_count_reset_at }})</span>
                    @endif
                </div>
            @endif
        </flux:fieldset>

        <flux:fieldset>
            <flux:legend>Notification Preferences</flux:legend>
            <flux:description>
                Choose which types of notifications you want to receive and how.
            </flux:description>

            <div class="mt-4 space-y-4">
                <div class="border border-zinc-200 dark:border-zinc-700 rounded-lg p-4">
                    <div class="flex items-center justify-between mb-3">
                        <div>
                            <div class="font-medium text-sm text-zinc-900 dark:text-white">Ticket Updates</div>
                            <div class="text-xs text-zinc-600 dark:text-zinc-400">New tickets, replies, and status changes</div>
                        </div>
                    </div>
                    <div class="flex gap-6">
                        <flux:switch wire:model="notify_tickets_email" label="Email" />
                        <flux:switch wire:model="notify_tickets_pushover" label="Pushover" />
                    </div>
                </div>
            </div>
        </flux:fieldset>

        <div class="flex items-center gap-4">
            <flux:button type="submit" variant="primary">Save Notification Settings</flux:button>
        </div>
    </form>
</section>
