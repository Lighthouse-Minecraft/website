<?php

use App\Actions\RecordActivity;
use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;

new class extends Component
{
    public string $step = '';

    public bool $showWelcomeModal = false;

    public function mount(): void
    {
        $this->step = Auth::user()->currentOnboardingStep();

        if ($this->step === 'complete') {
            $this->complete();
        }
    }

    public function skipDiscord(): void
    {
        $user = Auth::user();
        RecordActivity::run($user, 'onboarding_discord_skipped', 'Skipped Discord step.');
        $this->step = $user->fresh()->currentOnboardingStep();
    }

    public function continueDisabledDiscord(): void
    {
        $user = Auth::user();
        RecordActivity::run($user, 'onboarding_discord_disabled', 'Continued past disabled Discord step.');
        $this->step = $user->fresh()->currentOnboardingStep();
    }

    public function skipMinecraft(): void
    {
        $user = Auth::user();
        RecordActivity::run($user, 'onboarding_minecraft_skipped', 'Skipped Minecraft step.');
        $this->complete();
    }

    public function continueDisabledMinecraft(): void
    {
        $user = Auth::user();
        RecordActivity::run($user, 'onboarding_minecraft_disabled', 'Continued past disabled Minecraft step.');
        $this->complete();
    }

    public function complete(): void
    {
        $user = Auth::user();
        $user->update([
            'onboarding_wizard_completed_at' => now(),
            'onboarding_wizard_dismissed_at' => now(),
        ]);
        RecordActivity::run($user, 'onboarding_wizard_completed', 'Completed the onboarding wizard.');
        $this->step = 'complete';
        $this->showWelcomeModal = true;
    }

    public function dismiss(): void
    {
        $user = Auth::user();
        $user->update(['onboarding_wizard_dismissed_at' => now()]);
        RecordActivity::run($user, 'onboarding_wizard_dismissed', 'Dismissed the onboarding wizard.');
        $this->redirect(route('dashboard'));
    }

    public function closeWelcomeModal(): void
    {
        $this->redirect(route('dashboard'));
    }
}; ?>

<div class="flex flex-col items-center justify-center py-8">
    <div class="w-full max-w-lg">
        @if ($step === 'discord')
            <flux:card class="border border-indigo-500/40 bg-indigo-950/20">
                <div class="flex items-start justify-between">
                    <flux:heading size="lg" class="text-indigo-300">Connect Your Discord Account</flux:heading>
                    <flux:button wire:click="dismiss" variant="ghost" size="sm" class="text-zinc-400 hover:text-zinc-200 -mt-1 -mr-1">
                        Dismiss
                    </flux:button>
                </div>

                <flux:separator variant="subtle" class="my-4" />

                @if (! auth()->user()->parent_allows_discord)
                    <flux:text class="mb-4">
                        Discord is currently disabled for your account by a parent or guardian.
                        You can continue setting up your Lighthouse account without it.
                    </flux:text>

                    <div class="flex justify-end">
                        <flux:button wire:click="continueDisabledDiscord" variant="primary">
                            Continue
                        </flux:button>
                    </div>
                @else
                    <flux:text class="mb-2">
                        Connecting Discord lets Lighthouse assign you the right server roles automatically
                        and send you direct message notifications about meetings, tickets, and community updates.
                    </flux:text>
                    <flux:text variant="subtle" class="text-sm mb-4">
                        You'll be redirected to your account settings to complete the link.
                    </flux:text>

                    <div class="flex items-center justify-between">
                        <flux:button wire:click="skipDiscord" variant="ghost" size="sm">
                            Skip for now
                        </flux:button>
                        <flux:button href="{{ route('settings.discord-account') }}" variant="primary">
                            Connect Discord
                        </flux:button>
                    </div>
                @endif
            </flux:card>

        @elseif ($step === 'waiting')
            <flux:card class="border border-zinc-700 bg-zinc-900/50">
                <div class="flex items-start justify-between">
                    <flux:heading size="lg">You're on the Waitlist!</flux:heading>
                    <flux:button wire:click="dismiss" variant="ghost" size="sm" class="text-zinc-400 hover:text-zinc-200 -mt-1 -mr-1">
                        Dismiss
                    </flux:button>
                </div>

                <flux:separator variant="subtle" class="my-4" />

                <flux:text class="mb-3">
                    A staff member will review your account and promote you to Traveler.
                    This usually happens within a day or two — hang tight!
                </flux:text>
                <flux:text variant="subtle" class="text-sm">
                    Once you're a Traveler you'll be able to link your Minecraft account and join
                    the server, plus unlock full community features. Check back soon.
                </flux:text>
            </flux:card>

        @elseif ($step === 'minecraft')
            <flux:card class="border border-indigo-500/40 bg-indigo-950/20">
                <div class="flex items-start justify-between">
                    <flux:heading size="lg" class="text-indigo-300">Link Your Minecraft Account</flux:heading>
                    <flux:button wire:click="dismiss" variant="ghost" size="sm" class="text-zinc-400 hover:text-zinc-200 -mt-1 -mr-1">
                        Dismiss
                    </flux:button>
                </div>

                <flux:separator variant="subtle" class="my-4" />

                @if (! auth()->user()->parent_allows_minecraft)
                    <flux:text class="mb-4">
                        Minecraft is currently disabled for your account by a parent or guardian.
                        Your setup is otherwise complete!
                    </flux:text>

                    <div class="flex justify-end">
                        <flux:button wire:click="continueDisabledMinecraft" variant="primary">
                            Continue
                        </flux:button>
                    </div>
                @else
                    <flux:text class="mb-2">
                        Link your Minecraft account to join the Lighthouse server and get whitelisted automatically.
                        You'll also receive in-game notifications and have your roles synced.
                    </flux:text>
                    <flux:text variant="subtle" class="text-sm mb-4">
                        You'll be redirected to your account settings to complete the link.
                    </flux:text>

                    <div class="flex items-center justify-between">
                        <flux:button wire:click="skipMinecraft" variant="ghost" size="sm">
                            Skip for now
                        </flux:button>
                        <flux:button href="{{ route('settings.minecraft-accounts') }}" variant="primary">
                            Connect Minecraft
                        </flux:button>
                    </div>
                @endif
            </flux:card>

        @endif
    </div>

    @if ($showWelcomeModal)
        <flux:modal wire:model="showWelcomeModal" name="welcome-modal" class="max-w-lg">
            <div class="space-y-4">
                <flux:heading size="xl">Welcome to Lighthouse!</flux:heading>
                <flux:text>
                    You're all set up. Here's what you can do now:
                </flux:text>

                <div class="space-y-3">
                    <div class="flex items-start gap-3">
                        <flux:icon.chat-bubble-left-right class="mt-0.5 size-5 shrink-0 text-indigo-400" />
                        <div>
                            <flux:text class="font-medium">Join Discussions</flux:text>
                            <flux:text variant="subtle" class="text-sm">Share ideas, ask questions, and connect with the community.</flux:text>
                        </div>
                    </div>
                    <div class="flex items-start gap-3">
                        <flux:icon.inbox class="mt-0.5 size-5 shrink-0 text-indigo-400" />
                        <div>
                            <flux:text class="font-medium">Open a Ticket</flux:text>
                            <flux:text variant="subtle" class="text-sm">Need help or have a question? Our staff team is here for you.</flux:text>
                        </div>
                    </div>
                    <div class="flex items-start gap-3">
                        <flux:icon.newspaper class="mt-0.5 size-5 shrink-0 text-indigo-400" />
                        <div>
                            <flux:text class="font-medium">Explore Community Content</flux:text>
                            <flux:text variant="subtle" class="text-sm">Check out community updates, announcements, and blog posts.</flux:text>
                        </div>
                    </div>
                </div>

                <flux:separator variant="subtle" />

                <div class="flex items-center justify-between">
                    <flux:link href="{{ route('settings.notifications') }}" wire:navigate class="text-sm">
                        Tune your notification preferences
                    </flux:link>
                    <flux:button wire:click="closeWelcomeModal" variant="primary">
                        Get Started
                    </flux:button>
                </div>
            </div>
        </flux:modal>
    @endif
</div>
