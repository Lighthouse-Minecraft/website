<?php

use App\Actions\CompleteVerification;
use App\Actions\ExpireVerification;
use App\Actions\GenerateVerificationCode;
use App\Actions\ReactivateMinecraftAccount;
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

    public ?int $accountToReactivate = null;

    public ?int $accountToRemoveVerifying = null;

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
        $this->authorize('link-minecraft-account');
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
            $this->modal('confirm-cancel-verification')->close();
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
                $this->cancelAndRemoveFromWhitelist($account, [
                    'action' => 'cancel_verification',
                    'verification_id' => $verification->id,
                ]);
            }

            $verification->update(['status' => 'expired']);
        }

        $this->verificationCode = null;
        $this->expiresAt = null;
        $this->modal('confirm-cancel-verification')->close();
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

    public function confirmReactivate(int $accountId): void
    {
        $this->accountToReactivate = $accountId;
        $this->modal('confirm-reactivate')->show();
    }

    public function reactivateAccount(): void
    {
        if (! $this->accountToReactivate) {
            $this->modal('confirm-reactivate')->close();
            return;
        }

        $account = auth()->user()->minecraftAccounts()->find($this->accountToReactivate);

        if (! $account) {
            $this->modal('confirm-reactivate')->close();
            $this->accountToReactivate = null;
            return;
        }

        $this->authorize('reactivate', $account);

        $result = ReactivateMinecraftAccount::run($account, auth()->user());

        $this->modal('confirm-reactivate')->close();
        $this->accountToReactivate = null;

        if ($result['success']) {
            Flux::toast($result['message'], variant: 'success');
        } else {
            Flux::toast($result['message'], variant: 'danger');
        }
    }

    public function setPrimary(int $accountId): void
    {
        $account = auth()->user()->minecraftAccounts()->findOrFail($accountId);

        $this->authorize('setPrimary', $account);

        $result = \App\Actions\SetPrimaryMinecraftAccount::run($account);

        if ($result) {
            Flux::toast("{$account->username} is now your primary account.", variant: 'success');
        } else {
            Flux::toast('Only active accounts can be set as primary.', variant: 'danger');
        }
    }

    public function confirmRemoveVerifying(int $accountId): void
    {
        $this->accountToRemoveVerifying = $accountId;
        $this->modal('confirm-remove-verifying')->show();
    }

    public function removeVerifyingAccount(): void
    {
        if (! $this->accountToRemoveVerifying) {
            $this->modal('confirm-remove-verifying')->close();

            return;
        }

        $account = auth()->user()->minecraftAccounts()
            ->where('id', $this->accountToRemoveVerifying)
            ->where('status', MinecraftAccountStatus::Verifying)
            ->first();

        if (! $account) {
            $this->modal('confirm-remove-verifying')->close();
            $this->accountToRemoveVerifying = null;
            Flux::toast('Account not found or no longer in verification.', variant: 'danger');

            return;
        }

        $this->authorize('delete', $account);

        // Expire the associated verification record (try exact match, then normalized)
        $normalizedUuid = str_replace('-', '', $account->uuid);
        $verification = MinecraftVerification::where('user_id', auth()->id())
            ->where(function ($q) use ($account, $normalizedUuid) {
                $q->where('minecraft_uuid', $account->uuid)
                    ->orWhereRaw("REPLACE(minecraft_uuid, '-', '') = ?", [$normalizedUuid]);
            })
            ->pending()
            ->first();

        if ($verification) {
            $verification->update(['status' => 'expired']);
        }

        $this->cancelAndRemoveFromWhitelist($account, [
            'action' => 'cancel_verification_by_account',
            'account_id' => $account->id,
        ]);

        // Clear the active verification UI so the form resets
        $this->verificationCode = null;
        $this->expiresAt = null;

        $this->modal('confirm-remove-verifying')->close();
        $this->accountToRemoveVerifying = null;
        Flux::toast('Verification cancelled and account removed.', variant: 'warning');
    }

    private function cancelAndRemoveFromWhitelist(MinecraftAccount $account, array $context): void
    {
        $account->update(['status' => MinecraftAccountStatus::Cancelled]);

        $rconService = app(MinecraftRconService::class);
        $result = $rconService->executeCommand(
            $account->whitelistRemoveCommand(),
            'whitelist',
            $account->username,
            auth()->user(),
            $context
        );

        if ($result['success']) {
            $account->delete();
        }
    }

    public function with(): array
    {
        $maxAccounts = config('lighthouse.max_minecraft_accounts');
        $linkedAccounts = auth()->user()->fresh()->minecraftAccounts;
        $countingStatuses = [
            \App\Enums\MinecraftAccountStatus::Active,
            \App\Enums\MinecraftAccountStatus::Verifying,
            \App\Enums\MinecraftAccountStatus::Banned,
        ];
        $countingAccounts = $linkedAccounts->filter(fn ($a) => in_array($a->status, $countingStatuses))->count();

        $activeAccounts = $linkedAccounts->filter(fn ($a) => $a->status !== \App\Enums\MinecraftAccountStatus::Removed);
        $archivedAccounts = $linkedAccounts->filter(fn ($a) => $a->status === \App\Enums\MinecraftAccountStatus::Removed);

        return [
            'linkedAccounts' => $activeAccounts,
            'archivedAccounts' => $archivedAccounts,
            'maxAccounts' => $maxAccounts,
            'remainingSlots' => $maxAccounts - $countingAccounts,
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
                                    @if($account->is_primary)
                                        <flux:badge color="blue" size="sm">Primary</flux:badge>
                                    @endif
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
                            <div class="flex gap-2">
                                @if(!$account->is_primary)
                                    <flux:button
                                        wire:click="setPrimary({{ $account->id }})"
                                        variant="ghost"
                                        size="sm">
                                        Set Primary
                                    </flux:button>
                                @endif
                                <flux:button
                                    wire:click="confirmRemove({{ $account->id }})"
                                    variant="danger"
                                    size="sm">
                                    Remove
                                </flux:button>
                            </div>
                        @elseif($account->status === \App\Enums\MinecraftAccountStatus::Verifying)
                            <flux:button
                                wire:click="confirmRemoveVerifying({{ $account->id }})"
                                variant="danger"
                                size="sm">
                                Remove
                            </flux:button>
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
    @elseif(! auth()->user()->parent_allows_minecraft)
        <flux:callout variant="warning">
            Minecraft access has been disabled by your parent or guardian.
        </flux:callout>
    @elseif($remainingSlots > 0 && !$verificationCode && Gate::allows('link-minecraft-account'))
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
    {{-- Archived Accounts --}}
    @if($archivedAccounts->isNotEmpty())
        <div class="flex flex-col gap-3">
            <flux:heading size="lg">Archived Accounts</flux:heading>
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">These accounts have been removed from the server but their history is preserved.</flux:text>

            @foreach($archivedAccounts as $account)
                <flux:card wire:key="archived-account-{{ $account->id }}" class="p-4 opacity-75">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            @if($account->avatar_url)
                                <img src="{{ $account->avatar_url }}" alt="{{ $account->username }}" class="w-8 h-8 rounded grayscale" />
                            @endif
                            <div>
                                <div class="flex items-center gap-2">
                                    <button wire:click="showAccount({{ $account->id }})" class="font-semibold text-blue-600 dark:text-blue-400 hover:underline cursor-pointer">{{ $account->username }}</button>
                                    <flux:badge color="zinc" size="sm">Removed</flux:badge>
                                </div>
                                <flux:text class="text-sm text-zinc-500">
                                    {{ $account->account_type->label() }}
                                    @if($account->verified_at)
                                        • Originally verified {{ $account->verified_at->diffForHumans() }}
                                    @endif
                                </flux:text>
                            </div>
                        </div>
                        @if($remainingSlots > 0 && Gate::allows('link-minecraft-account'))
                            <flux:button
                                wire:click="confirmReactivate({{ $account->id }})"
                                variant="primary"
                                size="sm">
                                Reactivate
                            </flux:button>
                        @endif
                    </div>
                </flux:card>
            @endforeach
        </div>
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

    {{-- Reactivate account confirmation modal --}}
    <flux:modal name="confirm-reactivate" class="min-w-[22rem] space-y-6">
        <div>
            <flux:heading size="lg">Reactivate Minecraft Account</flux:heading>
            <flux:text class="mt-2">Are you sure you want to reactivate this account? It will be re-added to the server whitelist and your rank will be synced.</flux:text>
        </div>

        <div class="flex gap-2 justify-end">
            <flux:modal.close>
                <flux:button variant="ghost">Cancel</flux:button>
            </flux:modal.close>
            <flux:button variant="primary" wire:click="reactivateAccount">Reactivate Account</flux:button>
        </div>
    </flux:modal>

    {{-- Remove verifying account confirmation modal --}}
    <flux:modal name="confirm-remove-verifying" class="min-w-[22rem] space-y-6">
        <div>
            <flux:heading size="lg">Cancel Verification</flux:heading>
            <flux:text class="mt-2">Cancel this verification and remove the account? You will need to start over to link this account.</flux:text>
        </div>

        <div class="flex gap-2 justify-end">
            <flux:modal.close>
                <flux:button variant="ghost">Keep Waiting</flux:button>
            </flux:modal.close>
            <flux:button variant="danger" wire:click="removeVerifyingAccount">Remove</flux:button>
        </div>
    </flux:modal>

    {{-- Cancel verification confirmation modal --}}
    <flux:modal name="confirm-cancel-verification" class="min-w-[22rem] space-y-6">
        <div>
            <flux:heading size="lg">Cancel Verification</flux:heading>
            <flux:text class="mt-2">Cancel this verification? You will need to generate a new code to link your account.</flux:text>
        </div>

        <div class="flex gap-2 justify-end">
            <flux:modal.close>
                <flux:button variant="ghost">Keep Waiting</flux:button>
            </flux:modal.close>
            <flux:button variant="danger" wire:click="cancelVerification">Remove</flux:button>
        </div>
    </flux:modal>
</div>
</x-settings.layout>
