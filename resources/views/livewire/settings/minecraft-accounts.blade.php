<?php

use App\Actions\GenerateVerificationCode;
use App\Actions\SendMinecraftCommand;
use App\Actions\UnlinkMinecraftAccount;
use App\Enums\MinecraftAccountType;
use App\Models\MinecraftAccount;
use App\Models\MinecraftVerification;
use Flux\Flux;
use Livewire\Volt\Component;

new class extends Component {
    public string $accountType = 'java';

    public string $username = '';

    public ?string $verificationCode = null;

    public ?\Carbon\Carbon $expiresAt = null;

    public ?string $errorMessage = null;

    public function mount(): void
    {
        // Check for active verification
        $activeVerification = MinecraftVerification::where('user_id', auth()->id())
            ->where('status', 'pending')
            ->where('expires_at', '>', now())
            ->latest()
            ->first();

        if ($activeVerification) {
            $this->verificationCode = $activeVerification->code;
            $this->expiresAt = $activeVerification->expires_at;
            $this->accountType = $activeVerification->account_type->value;
        }
    }

    public function generateCode(): void
    {
        $this->errorMessage = null;
        $this->validate([
            'username' => 'required|string|min:3|max:16',
            'accountType' => 'required|in:java,bedrock',
        ]);

        $accountType = $this->accountType === 'java' ? MinecraftAccountType::Java : MinecraftAccountType::Bedrock;

        $result = GenerateVerificationCode::run(auth()->user(), $accountType, $this->username);

        if ($result['success']) {
            $this->verificationCode = $result['code'];
            $this->expiresAt = $result['expires_at'];
            Flux::toast('Verification code generated! Join the server and run /verify '.$result['code'], variant: 'success');
        } else {
            $this->errorMessage = $result['error'];
            Flux::toast($result['error'], variant: 'danger');
        }
    }

    public function cancelVerification(): void
    {
        if (! $this->verificationCode) {
            return;
        }

        $verification = MinecraftVerification::where('code', $this->verificationCode)
            ->where('user_id', auth()->id())
            ->pending()
            ->first();

        if ($verification) {
            // Async whitelist remove — same pattern as CleanupExpiredVerifications
            SendMinecraftCommand::dispatch(
                "whitelist remove {$verification->minecraft_username}",
                'whitelist',
                $verification->minecraft_username,
                auth()->user(),
                ['action' => 'cancel_verification', 'verification_id' => $verification->id]
            );

            $verification->update(['status' => 'expired']);
        }

        $this->verificationCode = null;
        $this->expiresAt = null;
        Flux::toast('Verification cancelled.', variant: 'warning');
    }

    public function checkVerification(): void
    {
        if (!$this->verificationCode) {
            return;
        }

        // Check if verification was completed - force fresh query
        $verification = MinecraftVerification::where('code', $this->verificationCode)
            ->where('user_id', auth()->id())
            ->first();

        if (!$verification) {
            // Verification not found, clear the code
            $this->verificationCode = null;
            $this->expiresAt = null;
            return;
        }

        if ($verification->status === 'completed') {
            $this->verificationCode = null;
            $this->expiresAt = null;
            $this->username = '';
            Flux::toast('Minecraft account linked successfully!', variant: 'success');
        } elseif ($verification->status === 'expired' || $verification->expires_at < now()) {
            $this->verificationCode = null;
            $this->expiresAt = null;
            Flux::toast('Verification code expired. Please generate a new one.', variant: 'danger');
        }
    }

    public function remove(int $accountId): void
    {
        $account = MinecraftAccount::findOrFail($accountId);

        $this->authorize('delete', $account);

        $result = UnlinkMinecraftAccount::run($account, auth()->user());

        if ($result['success']) {
            Flux::toast($result['message'], variant: 'success');
        } else {
            Flux::toast($result['message'], variant: 'danger');
        }
    }

    public function with(): array
    {
        $maxAccounts = config('lighthouse.max_minecraft_accounts');
        $linkedAccounts = auth()->user()->fresh()->minecraftAccounts;

        return [
            'linkedAccounts' => $linkedAccounts,
            'maxAccounts' => $maxAccounts,
            'remainingSlots' => $maxAccounts - $linkedAccounts->count(),
        ];
    }
}; ?>

<x-settings.layout heading="Minecraft Accounts" subheading="Link your Minecraft account to access the server and sync permissions">
<div class="space-y-6" wire:poll.15s="checkVerification">

    {{-- Linked Accounts --}}
    @if($linkedAccounts->isNotEmpty())
        <div class="flex flex-col gap-3">
            <flux:heading size="lg">Your Linked Accounts</flux:heading>

            @foreach($linkedAccounts as $account)
                <flux:card wire:key="{{ $account->id }}" class="p-4">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            @if($account->avatar_url)
                                <img src="{{ $account->avatar_url }}" alt="{{ $account->username }}" class="w-8 h-8 rounded" />
                            @endif
                            <div>
                                <flux:text class="font-semibold">{{ $account->username }}</flux:text>
                                <flux:text class="text-sm text-zinc-500">
                                    {{ $account->account_type->label() }} • Verified {{ $account->verified_at->diffForHumans() }}
                                </flux:text>
                            </div>
                        </div>
                        <flux:button
                            wire:click="remove({{ $account->id }})"
                            variant="danger"
                            size="sm"
                            wire:confirm="Are you sure you want to unlink this Minecraft account? You will be removed from the server whitelist.">
                            Remove
                        </flux:button>
                    </div>
                </flux:card>
            @endforeach
        </div>
    @endif

    {{-- Account Limit Message --}}
    @if($remainingSlots > 0)
        <flux:text class="text-sm text-zinc-600 dark:text-zinc-400">
            You can link {{ $remainingSlots }} more account{{ $remainingSlots !== 1 ? 's' : '' }}.
        </flux:text>
    @else
        <flux:callout variant="warning">
            You have reached the maximum of {{ $maxAccounts }} linked Minecraft accounts. Remove an account to link a new one.
        </flux:callout>
    @endif

    {{-- Active Verification Code --}}
    @if($verificationCode && $expiresAt)
        <flux:card class="p-6 bg-blue-50 dark:bg-blue-950 border-blue-200 dark:border-blue-800">
            <flux:heading size="lg" class="mb-4">Active Verification Code</flux:heading>

            <div class="space-y-4">
                <div>
                    <flux:text class="text-sm text-zinc-600 dark:text-zinc-400 mb-2">Your verification code:</flux:text>
                    <div class="font-mono text-3xl font-bold text-blue-600 dark:text-blue-400 tracking-wider">
                        {{ $verificationCode }}
                    </div>
                </div>

                <flux:text class="text-sm">
                    Expires {{ $expiresAt->diffForHumans() }} ({{ $expiresAt->format('g:i A') }})
                </flux:text>

                <flux:separator />

                <div class="space-y-2">
                    <flux:text class="font-semibold">Instructions:</flux:text>
                    <ol class="flex flex-col gap-1 list-decimal list-inside text-sm text-zinc-700 dark:text-zinc-300">
                        <li>Join the Minecraft server</li>
                        <li>Type in chat: <code class="px-2 py-1 bg-zinc-200 dark:bg-zinc-700 rounded">/verify {{ $verificationCode }}</code></li>
                        <li>Wait for confirmation (this page will update automatically)</li>
                    </ol>
                </div>

                <div class="flex justify-end pt-2">
                    <flux:button
                        wire:click="cancelVerification"
                        variant="ghost"
                        size="sm"
                        wire:confirm="Cancel this verification? You will need to generate a new code to link your account.">
                        Cancel Verification
                    </flux:button>
                </div>
            </div>
        </flux:card>
    @endif

    {{-- Add New Account Form --}}
    @if($remainingSlots > 0 && !$verificationCode)
        <flux:card class="p-6">
            <flux:heading size="lg" class="mb-4">Link New Account</flux:heading>

            <form wire:submit="generateCode" class="space-y-4">
                @if($errorMessage)
                    <flux:callout variant="danger">
                        {{ $errorMessage }}
                    </flux:callout>
                @endif

                <flux:field>
                    <flux:label>Account Type</flux:label>
                    <flux:radio.group wire:model.live="accountType">
                        <flux:radio value="java" label="Java Edition" />
                        <flux:radio value="bedrock" label="Bedrock Edition" />
                    </flux:radio.group>
                </flux:field>

                <div class="flex items-end gap-4">
                    <div class="flex-1">
                        <flux:input
                            wire:model.live.debounce.600ms="username"
                            label="Minecraft Username"
                            placeholder="{{ $accountType === 'java' ? 'JavaPlayer123' : 'BedrockGamer456' }}"
                            required />
                    </div>
                    @if($accountType === 'java' && strlen($username) >= 3)
                        <div class="flex-shrink-0 mb-0.5">
                            <img
                                src="https://mc-heads.net/avatar/{{ urlencode($username) }}/48"
                                alt="{{ $username }}"
                                class="w-12 h-12 rounded pixelated"
                                title="{{ $username }}" />
                        </div>
                    @endif
                </div>

                <flux:button type="submit" variant="primary">
                    Generate Verification Code
                </flux:button>
            </form>
        </flux:card>
    @endif
</div>
</x-settings.layout>
