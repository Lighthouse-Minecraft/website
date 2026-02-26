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

    // Notification preferences — Tickets
    public bool $notify_tickets_email = true;

    public bool $notify_tickets_pushover = false;

    public bool $notify_tickets_discord = false;

    // Notification preferences — Account Updates
    public bool $notify_account_email = true;

    public bool $notify_account_pushover = false;

    public bool $notify_account_discord = false;

    // Notification preferences — Staff Alerts
    public bool $notify_staff_alerts_email = true;

    public bool $notify_staff_alerts_pushover = false;

    public bool $notify_staff_alerts_discord = false;

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
        $this->notify_tickets_discord = $preferences['tickets']['discord'] ?? false;

        $this->notify_account_email = $preferences['account']['email'] ?? true;
        $this->notify_account_pushover = $preferences['account']['pushover'] ?? false;
        $this->notify_account_discord = $preferences['account']['discord'] ?? false;

        $this->notify_staff_alerts_email = $preferences['staff_alerts']['email'] ?? true;
        $this->notify_staff_alerts_pushover = $preferences['staff_alerts']['pushover'] ?? false;
        $this->notify_staff_alerts_discord = $preferences['staff_alerts']['discord'] ?? false;
    }

    public function updateNotificationSettings(): void
    {
        $user = Auth::user();

        $validated = $this->validate([
            'pushover_key' => ['nullable', 'string', 'max:255'],
            'email_digest_frequency' => ['required', 'in:immediate,daily,weekly'],
            'notify_tickets_email' => ['boolean'],
            'notify_tickets_pushover' => ['boolean'],
            'notify_tickets_discord' => ['boolean'],
            'notify_account_email' => ['boolean'],
            'notify_account_pushover' => ['boolean'],
            'notify_account_discord' => ['boolean'],
            'notify_staff_alerts_email' => ['boolean'],
            'notify_staff_alerts_pushover' => ['boolean'],
            'notify_staff_alerts_discord' => ['boolean'],
        ]);

        $user->pushover_key = $validated['pushover_key'];
        $user->email_digest_frequency = EmailDigestFrequency::from($validated['email_digest_frequency']);

        // Save all notification preference categories
        $preferences = $user->notification_preferences ?? [];
        $preferences['tickets'] = [
            'email' => $validated['notify_tickets_email'],
            'pushover' => $validated['notify_tickets_pushover'],
            'discord' => $validated['notify_tickets_discord'],
        ];
        $preferences['account'] = [
            'email' => $validated['notify_account_email'],
            'pushover' => $validated['notify_account_pushover'],
            'discord' => $validated['notify_account_discord'],
        ];
        $preferences['staff_alerts'] = [
            'email' => $validated['notify_staff_alerts_email'],
            'pushover' => $validated['notify_staff_alerts_pushover'],
            'discord' => $validated['notify_staff_alerts_discord'],
        ];
        $user->notification_preferences = $preferences;

        $user->save();

        Flux::toast('Notification settings updated successfully!', variant: 'success');
    }
}; ?>

<x-settings.layout>
<section class="w-full">
    @include('partials.settings-heading')

    <form wire:submit="updateNotificationSettings" class="mt-6 space-y-6">
        <flux:fieldset>
            <flux:legend>Ticket Email Frequency</flux:legend>
            <flux:description>
                Choose how often you'd like to receive email notifications for ticket activity. Other notifications are always sent immediately.
            </flux:description>

            <div class="mt-4 space-y-3">
                <flux:radio.group wire:model="email_digest_frequency" variant="cards">
                    <flux:radio value="immediate" label="Immediate" description="Get notified right away when there's ticket activity" />
                    <flux:radio value="daily" label="Daily Digest" description="Receive a daily summary of ticket activity" />
                    <flux:radio value="weekly" label="Weekly Digest" description="Receive a weekly summary of ticket activity" />
                </flux:radio.group>
            </div>
        </flux:fieldset>

        <flux:fieldset>
            <flux:legend>Pushover Notifications (Optional)</flux:legend>
            <flux:description>
                Enter your Pushover user key to receive mobile push notifications.
                <a href="https://pushover.net" target="_blank" class="text-blue-600 hover:text-blue-800 dark:text-blue-400">Get your Pushover key</a>
            </flux:description>

            <flux:field class="mt-4">
                <flux:label>Pushover User Key</flux:label>
                <flux:input wire:model.live="pushover_key" type="text" placeholder="Enter your Pushover user key" />
            </flux:field>
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
                        @if($pushover_key)
                            <flux:switch wire:model="notify_tickets_pushover" label="Pushover" />
                        @else
                            <flux:tooltip content="Add your Pushover key above to enable">
                                <flux:switch wire:model="notify_tickets_pushover" label="Pushover" disabled />
                            </flux:tooltip>
                        @endif
                        @if(auth()->user()->hasDiscordLinked())
                            <flux:switch wire:model="notify_tickets_discord" label="Discord DM" />
                        @else
                            <flux:tooltip content="Link a Discord account in Settings to enable">
                                <flux:switch wire:model="notify_tickets_discord" label="Discord DM" disabled />
                            </flux:tooltip>
                        @endif
                    </div>
                </div>

                <div class="border border-zinc-200 dark:border-zinc-700 rounded-lg p-4">
                    <div class="flex items-center justify-between mb-3">
                        <div>
                            <div class="font-medium text-sm text-zinc-900 dark:text-white">Account Updates</div>
                            <div class="text-xs text-zinc-600 dark:text-zinc-400">Promotions, demotions, and brig notifications</div>
                        </div>
                    </div>
                    <div class="flex gap-6">
                        <flux:switch wire:model="notify_account_email" label="Email" />
                        @if($pushover_key)
                            <flux:switch wire:model="notify_account_pushover" label="Pushover" />
                        @else
                            <flux:tooltip content="Add your Pushover key above to enable">
                                <flux:switch wire:model="notify_account_pushover" label="Pushover" disabled />
                            </flux:tooltip>
                        @endif
                        @if(auth()->user()->hasDiscordLinked())
                            <flux:switch wire:model="notify_account_discord" label="Discord DM" />
                        @else
                            <flux:tooltip content="Link a Discord account in Settings to enable">
                                <flux:switch wire:model="notify_account_discord" label="Discord DM" disabled />
                            </flux:tooltip>
                        @endif
                    </div>
                </div>

                @can('manage-stowaway-users')
                    <div class="border border-zinc-200 dark:border-zinc-700 rounded-lg p-4">
                        <div class="flex items-center justify-between mb-3">
                            <div>
                                <div class="font-medium text-sm text-zinc-900 dark:text-white">Staff Alerts</div>
                                <div class="text-xs text-zinc-600 dark:text-zinc-400">New members to review and other staff notifications</div>
                            </div>
                        </div>
                        <div class="flex gap-6">
                            <flux:switch wire:model="notify_staff_alerts_email" label="Email" />
                            @if($pushover_key)
                                <flux:switch wire:model="notify_staff_alerts_pushover" label="Pushover" />
                            @else
                                <flux:tooltip content="Add your Pushover key above to enable">
                                    <flux:switch wire:model="notify_staff_alerts_pushover" label="Pushover" disabled />
                                </flux:tooltip>
                            @endif
                            @if(auth()->user()->hasDiscordLinked())
                                <flux:switch wire:model="notify_staff_alerts_discord" label="Discord DM" />
                            @else
                                <flux:tooltip content="Link a Discord account in Settings to enable">
                                    <flux:switch wire:model="notify_staff_alerts_discord" label="Discord DM" disabled />
                                </flux:tooltip>
                            @endif
                        </div>
                    </div>
                @endcan
            </div>
        </flux:fieldset>

        <div class="flex items-center gap-4">
            <flux:button type="submit" variant="primary">Save Notification Settings</flux:button>
        </div>
    </form>
</section>
</x-settings.layout>
