<?php

use App\Actions\CompleteVerification;
use App\Actions\ExpireVerification;
use App\Actions\GenerateVerificationCode;
use App\Actions\UnlinkMinecraftAccount;
use App\Enums\MinecraftAccountStatus;
use App\Enums\MinecraftAccountType;
use App\Models\MinecraftAccount;
use App\Models\MinecraftVerification;
use App\Services\MinecraftRconService;
use Flux\Flux;
use Livewire\Volt\Component;

new class extends Component {
    public string $accountType = 'java';
    public ?MinecraftAccount $selectedAccount = null;

    public string $username = '';

    public ?string $verificationCode = null;

    public ?\Carbon\Carbon $expiresAt = null;

    public ?string $errorMessage = null;

    public ?int $accountToUnlink = null;

    /**
     * Load the authenticated user's Minecraft account by ID and open its detail modal if found.
     *
     * Sets $this->selectedAccount to the located account (including its related user). If an account
     * with the given ID exists for the current user, shows the 'mc-account-detail' modal.
     *
     * @param int $accountId The ID of the Minecraft account to load.
     */
    public function showAccount(int $accountId): void
    {
        $this->selectedAccount = auth()->user()->minecraftAccounts()->with('user')->find($accountId);

        if ($this->selectedAccount) {
            $this->modal('mc-account-detail')->show();
        }
    }

    /**
     * Initialize component state from any active pending Minecraft verification for the authenticated user.
     *
     * If a pending verification that expires in the future exists, sets the component's
     * verificationCode, expiresAt, and accountType to match that verification.
     */
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
            // Find and cancel the associated verifying account
            $account = MinecraftAccount::whereNormalizedUuid($verification->minecraft_uuid)
                ->verifying()
                ->where('user_id', auth()->id())
                ->first();

            if ($account) {
                // Mark cancelled before attempting removal (enters retry pool if server is down)
                $account->update(['status' => MinecraftAccountStatus::Cancelled]);

                $rconService = app(MinecraftRconService::class);
                $result = $rconService->executeCommand(
                    $account->whitelistRemoveCommand(),
                    'whitelist',
                    $account->username,
                    auth()->user(),
                    ['action' => 'cancel_verification', 'verification_id' => $verification->id]
                );

                if ($result['success']) {
                    $account->delete();
                }
                // If server offline, account stays 'cancelled' for CleanupExpiredVerifications to retry
            }

            $verification->update(['status' => 'expired']);
        }

        $this->verificationCode = null;
        $this->expiresAt = null;
        Flux::toast('Verification cancelled.', variant: 'warning');
    }

    /**
     * Refreshes the current verification status for the active verification code and updates component state.
     *
     * If there is no active code this is a no-op. The method re-queries the verification by code and current user:
     * - If no verification is found, it clears the code and expiration.
     * - If the verification is completed, it clears the code and expiration, resets the username, and shows a success toast.
     * - If the verification is expired (by status or expiration timestamp), it clears the code and expiration and shows an expiration toast.
     */
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
            // If still pending, the scheduler hasn't cleaned up yet — do it now
            if ($verification->status === 'pending') {
                ExpireVerification::run($verification);
            }
            $this->verificationCode = null;
            $this->expiresAt = null;
            Flux::toast('Verification code expired. Please generate a new one.', variant: 'danger');
        }
    }

    /**
     * Simulates completion of the current pending Minecraft verification for local testing.
     *
     * Aborts with HTTP 403 when not running in the local environment. If there is no active
     * verification code the method returns immediately. The method looks up a pending
     * verification for the authenticated user by the stored code; if none is found a danger
     * toast is shown. On successful simulation it clears the component's verification state
     * (`verificationCode`, `expiresAt`, `username`) and shows a success toast; on failure it
     * shows a danger toast with the error message.
     */
    public function simulateVerification(): void
    {
        abort_unless(app()->isLocal(), 403);

        if (! $this->verificationCode) {
            return;
        }

        $verification = MinecraftVerification::where('code', $this->verificationCode)
            ->where('user_id', auth()->id())
            ->pending()
            ->first();

        if (! $verification) {
            Flux::toast('No pending verification found.', variant: 'danger');

            return;
        }

        $result = CompleteVerification::run(
            $verification->code,
            $verification->minecraft_username,
            $verification->minecraft_uuid,
        );

        if ($result['success']) {
            $this->verificationCode = null;
            $this->expiresAt = null;
            $this->username = '';
            Flux::toast('Verification simulated successfully!', variant: 'success');
        } else {
            Flux::toast('Simulation failed: '.$result['message'], variant: 'danger');
        }
    }

    /**
     * Store the account ID and open the remove confirmation modal.
     *
     * @param int $accountId The ID of the MinecraftAccount to remove.
     */
    public function confirmRemove(int $accountId): void
    {
        $this->accountToUnlink = $accountId;
        $this->modal('confirm-remove')->show();
    }

    /**
     * Remove the account stored in $accountToUnlink, unlinking it from the user.
     */
    public function unlinkAccount(): void
    {
        if (! $this->accountToUnlink) {
            $this->modal('confirm-remove')->close();
            return;
        }

        $account = auth()->user()->minecraftAccounts()->find($this->accountToUnlink);

        if (! $account) {
            $this->modal('confirm-remove')->close();
            $this->accountToUnlink = null;
            return;
        }

        $this->authorize('delete', $account);

        $result = UnlinkMinecraftAccount::run($account, auth()->user());

        $this->modal('confirm-remove')->close();
        $this->accountToUnlink = null;

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
                <flux:card wire:key="minecraft-account-{{ $account->id }}" class="p-4">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            @if($account->avatar_url)
                                <img src="{{ $account->avatar_url }}" alt="{{ $account->username }}" class="w-8 h-8 rounded" />
                            @endif
                            <div>
                                <div class="flex items-center gap-2">
                                    <button wire:click="showAccount({{ $account->id }})" class="font-semibold text-blue-600 dark:text-blue-400 hover:underline cursor-pointer">{{ $account->username }}</button>
                                    <flux:badge color="{{ $account->status->color() }}" size="sm">
                                        {{ $account->status->label() }}
                                    </flux:badge>
                                </div>
                                <flux:text class="text-sm text-zinc-500">
                                    {{ $account->account_type->label() }}
                                    @if($account->status === \App\Enums\MinecraftAccountStatus::Active && $account->verified_at)
                                        • Verified {{ $account->verified_at->diffForHumans() }}
                                    @endif
                                </flux:text>
                            </div>
                        </div>
                        @if($account->status === \App\Enums\MinecraftAccountStatus::Active)
                            <flux:button
                                wire:click="confirmRemove({{ $account->id }})"
                                variant="danger"
                                size="sm">
                                Remove
                            </flux:button>
                        @else
                            <flux:text class="text-sm text-zinc-400 dark:text-zinc-500 italic">Pending removal...</flux:text>
                        @endif
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
                    @php
                        $tz = auth()->user()->timezone ?? 'UTC';
                        $expiresAtInTz = $expiresAt->copy()->setTimezone($tz);
                    @endphp
                    Expires {{ $expiresAtInTz->diffForHumans() }} ({{ $expiresAtInTz->format('g:i A T') }})
                </flux:text>

                <flux:separator />

                <div class="space-y-2">
                    <flux:text class="font-semibold">Instructions:</flux:text>
                    @php
                        $serverName = config('lighthouse.minecraft.server_name');
                        $serverHost = config('lighthouse.minecraft.server_host');
                        $serverPort = $accountType === 'bedrock'
                            ? config('lighthouse.minecraft.server_port_bedrock')
                            : config('lighthouse.minecraft.server_port_java');
                    @endphp
                    <ol class="flex flex-col gap-1 list-decimal list-inside text-sm text-zinc-700 dark:text-zinc-300">
                        <li>
                            Join the Minecraft server: <strong>{{ $serverName }}</strong>
                            <br>
                            <code class="px-2 py-1 bg-zinc-200 dark:bg-zinc-700 rounded">
                                {{ $serverHost }}:{{ $serverPort }}
                            </code>
                        </li>
                        <li>Type in chat: <code class="px-2 py-1 bg-zinc-200 dark:bg-zinc-700 rounded">/verify {{ $verificationCode }}</code></li>
                        <li>Wait for confirmation (this page will update automatically)</li>
                    </ol>
                </div>

                <div class="flex justify-between items-center pt-2">
                    @if(app()->isLocal())
                        <flux:button
                            wire:click="simulateVerification"
                            variant="filled"
                            size="sm"
                            class="bg-amber-100 text-amber-800 hover:bg-amber-200 dark:bg-amber-900 dark:text-amber-200 dark:hover:bg-amber-800">
                            ⚙ Simulate Verification
                        </flux:button>
                    @else
                        <div></div>
                    @endif
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
    @if(auth()->user()->membership_level->minecraftRank() === null)
        <flux:callout variant="info">
            You'll be able to link your Minecraft account once an admin has verified your membership and promoted you to Traveler rank.
        </flux:callout>
    @elseif($remainingSlots > 0 && !$verificationCode && !auth()->user()->isInBrig())
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
    <x-minecraft.mc-account-detail-modal :account="$selectedAccount" />

    {{-- Remove account confirmation modal --}}
    <flux:modal name="confirm-remove" class="min-w-[22rem] space-y-6">
        <div>
            <flux:heading size="lg">Remove Minecraft Account</flux:heading>
            <flux:text class="mt-2">Are you sure you want to unlink this account? You will be removed from the server whitelist.</flux:text>
        </div>

        <div class="flex gap-2 justify-end">
            <flux:modal.close>
                <flux:button variant="ghost">Cancel</flux:button>
            </flux:modal.close>
            <flux:button variant="danger" wire:click="unlinkAccount">Remove Account</flux:button>
        </div>
    </flux:modal>
</div>
</x-settings.layout>
