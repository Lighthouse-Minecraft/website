<?php

use App\Actions\UnlinkDiscordAccount;
use App\Models\DiscordAccount;
use Flux\Flux;
use Livewire\Volt\Component;

new class extends Component {
    public ?int $accountToUnlink = null;

    public function confirmUnlink(int $accountId): void
    {
        $this->accountToUnlink = $accountId;
        Flux::modal('confirm-unlink-discord')->show();
    }

    public function unlinkAccount(): void
    {
        if (! $this->accountToUnlink) {
            Flux::modal('confirm-unlink-discord')->close();
            return;
        }

        $account = auth()->user()->discordAccounts()->find($this->accountToUnlink);

        if (! $account) {
            Flux::modal('confirm-unlink-discord')->close();
            $this->accountToUnlink = null;
            return;
        }

        $this->authorize('delete', $account);

        UnlinkDiscordAccount::run($account, auth()->user());

        Flux::modal('confirm-unlink-discord')->close();
        $this->accountToUnlink = null;

        Flux::toast('Discord account unlinked successfully.', variant: 'success');
    }

    public function with(): array
    {
        $maxAccounts = config('lighthouse.max_discord_accounts', 1);
        $linkedAccounts = auth()->user()->fresh()->discordAccounts;

        return [
            'linkedAccounts' => $linkedAccounts,
            'maxAccounts' => $maxAccounts,
            'remainingSlots' => $maxAccounts - $linkedAccounts->count(),
            'inviteUrl' => config('services.discord.invite_url'),
        ];
    }
}; ?>

<x-settings.layout heading="Discord Account" subheading="Link your Discord account to receive role sync and DM notifications">
<div class="space-y-6">

    {{-- Session Messages --}}
    @if(session('success'))
        <flux:callout variant="success">{{ session('success') }}</flux:callout>
    @endif
    @if(session('error'))
        <flux:callout variant="danger">{{ session('error') }}</flux:callout>
    @endif

    {{-- Linked Accounts --}}
    @if($linkedAccounts->isNotEmpty())
        <div class="flex flex-col gap-3">
            <flux:heading size="lg">Your Linked Accounts</flux:heading>

            @foreach($linkedAccounts as $account)
                <flux:card wire:key="discord-account-{{ $account->id }}" class="p-4">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <img src="{{ $account->avatarUrl() }}" alt="{{ $account->username }}" class="w-8 h-8 rounded-full" />
                            <div>
                                <div class="flex items-center gap-2">
                                    <span class="font-semibold">{{ $account->displayName() }}</span>
                                    <flux:badge color="{{ $account->status->color() }}" size="sm">
                                        {{ $account->status->label() }}
                                    </flux:badge>
                                </div>
                                <flux:text class="text-sm text-zinc-500">
                                    {{ $account->username }}
                                    @if($account->verified_at)
                                        &bull; Linked {{ $account->verified_at->diffForHumans() }}
                                    @endif
                                </flux:text>
                            </div>
                        </div>
                        @if($account->status === \App\Enums\DiscordAccountStatus::Active)
                            <flux:button
                                wire:click="confirmUnlink({{ $account->id }})"
                                variant="danger"
                                size="sm">
                                Unlink
                            </flux:button>
                        @endif
                    </div>
                </flux:card>
            @endforeach
        </div>
    @endif

    {{-- Account Limit Message --}}
    @if($linkedAccounts->isNotEmpty() && $remainingSlots > 0)
        <flux:text class="text-sm text-zinc-600 dark:text-zinc-400">
            You can link {{ $remainingSlots }} more account{{ $remainingSlots !== 1 ? 's' : '' }}.
        </flux:text>
    @elseif($linkedAccounts->isNotEmpty() && $remainingSlots <= 0)
        <flux:callout variant="warning">
            You have reached the maximum of {{ $maxAccounts }} linked Discord account{{ $maxAccounts !== 1 ? 's' : '' }}. Unlink an account to link a new one.
        </flux:callout>
    @endif

    {{-- Discord Server Invite --}}
    @if($inviteUrl)
        <flux:card class="p-4">
            <div class="flex items-center justify-between">
                <div>
                    <flux:heading size="lg">Join Our Discord Server</flux:heading>
                    <flux:text class="text-sm text-zinc-500">Join the community server before linking your account.</flux:text>
                </div>
                <a href="{{ $inviteUrl }}" target="_blank" rel="noopener noreferrer">
                    <flux:button variant="primary" size="sm">Join Server</flux:button>
                </a>
            </div>
        </flux:card>
    @endif

    {{-- Link Button --}}
    @can('link-discord')
        @if($remainingSlots > 0)
            <flux:card class="p-6">
                <flux:heading size="lg" class="mb-4">Link Discord Account</flux:heading>
                <flux:text class="mb-4">
                    Connect your Discord account to automatically receive server roles matching your membership level and staff position.
                    You'll also be able to receive notifications via Discord DM.
                </flux:text>
                <a href="{{ route('auth.discord.redirect') }}">
                    <flux:button variant="primary">
                        Link Discord Account
                    </flux:button>
                </a>
            </flux:card>
        @endif
    @else
        @if($linkedAccounts->isEmpty())
            <flux:callout variant="info">
                You'll be able to link your Discord account once you've been promoted to Traveler rank.
            </flux:callout>
        @endif
    @endcan

    {{-- Unlink Confirmation Modal --}}
    <flux:modal name="confirm-unlink-discord" class="min-w-[22rem] space-y-6">
        <div>
            <flux:heading size="lg">Unlink Discord Account</flux:heading>
            <flux:text class="mt-2">Are you sure you want to unlink this Discord account? Your server roles will be removed.</flux:text>
        </div>

        <div class="flex gap-2 justify-end">
            <flux:modal.close>
                <flux:button variant="ghost">Cancel</flux:button>
            </flux:modal.close>
            <flux:button variant="danger" wire:click="unlinkAccount">Unlink Account</flux:button>
        </div>
    </flux:modal>
</div>
</x-settings.layout>
