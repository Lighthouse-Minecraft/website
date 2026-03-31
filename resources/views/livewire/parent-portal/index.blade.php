<?php

use App\Actions\CreateChildAccount;
use App\Actions\GenerateVerificationCode;
use App\Actions\ReleaseChildToAdult;
use App\Actions\ParentRegenerateVerificationCode;
use App\Actions\RemoveChildMinecraftAccount;
use App\Actions\UpdateChildPermission;
use App\Enums\MinecraftAccountType;
use App\Enums\ThreadStatus;
use App\Enums\ThreadType;
use App\Models\MinecraftVerification;
use App\Models\Thread;
use App\Models\User;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Volt\Component;

new class extends Component {
    #[Locked]
    public int $targetUserId = 0;

    #[Locked]
    public bool $isStaffViewing = false;

    public string $newChildName = '';
    public string $newChildEmail = '';
    public string $newChildDob = '';

    public ?int $accountToRemoveId = null;
    public string $accountToRemoveName = '';
    public string $accountToRemoveChildName = '';

    public array $childMcUsernames = [];
    public array $childMcAccountTypes = [];
    public array $childMcVerificationCodes = [];
    public array $childMcExpiresAt = [];
    public array $childMcErrors = [];

    public ?int $editingChildId = null;
    public array $editChildData = [
        'name' => '',
        'email' => '',
        'date_of_birth' => '',
    ];

    #[Locked]
    public ?int $viewingReportId = null;

    public function mount($user = null): void
    {
        if ($user) {
            if (! Auth::user()->isAtLeastRank(\App\Enums\StaffRank::Officer)) {
                abort(403);
            }
            $targetUser = $user instanceof User ? $user : User::findOrFail($user);
            $this->targetUserId = $targetUser->id;
            $this->isStaffViewing = true;
        } else {
            $this->authorize('view-parent-portal');
            $this->targetUserId = Auth::id();
        }
        $this->loadChildVerifications();
    }

    private function getTargetUser(): User
    {
        return User::findOrFail($this->targetUserId);
    }

    private function loadChildVerifications(): void
    {
        $childIds = $this->getTargetUser()->children()->pluck('child_user_id');

        if ($childIds->isEmpty()) {
            return;
        }

        $activeVerifications = MinecraftVerification::whereIn('user_id', $childIds)
            ->where('status', 'pending')
            ->where('expires_at', '>', now())
            ->get();

        foreach ($activeVerifications as $v) {
            $this->childMcVerificationCodes[$v->user_id] = $v->code;
            $this->childMcExpiresAt[$v->user_id] = $v->expires_at->toIso8601String();
        }
    }

    #[Computed]
    public function children()
    {
        $parent = $this->getTargetUser();
        $children = $parent->children()
            ->with(['minecraftAccounts', 'discordAccounts'])
            ->get();

        // Batch-load recent tickets for all children to avoid N+1
        if ($children->isNotEmpty()) {
            $childIds = $children->pluck('id');
            $tickets = Thread::whereIn('created_by_user_id', $childIds)
                ->where('type', ThreadType::Ticket)
                ->whereIn('status', [ThreadStatus::Open, ThreadStatus::Closed, ThreadStatus::Resolved])
                ->latest()
                ->get()
                ->groupBy('created_by_user_id');

            foreach ($children as $child) {
                $child->setRelation('recentTickets',
                    ($tickets->get($child->id) ?? collect())->take(10)
                );
            }

            // Batch-load published discipline reports for all children
            $reports = \App\Models\DisciplineReport::whereIn('subject_user_id', $childIds)
                ->with('category')
                ->published()
                ->latest('published_at')
                ->get()
                ->groupBy('subject_user_id');

            foreach ($children as $child) {
                $child->setRelation('publishedDisciplineReports',
                    ($reports->get($child->id) ?? collect())->take(10)
                );
            }
        }

        return $children;
    }

    public function togglePermission(int $childId, string $permission): void
    {
        if ($this->isStaffViewing) {
            return;
        }

        if (! in_array($permission, ['use_site', 'login', 'minecraft', 'discord'])) {
            return;
        }

        $child = User::findOrFail($childId);
        $parent = $this->getTargetUser();

        if (! $parent->children()->where('child_user_id', $child->id)->exists()) {
            Flux::toast('You do not have permission to manage this account.', 'Unauthorized', variant: 'danger');
            return;
        }

        $currentValue = match ($permission) {
            'use_site' => $child->parent_allows_site,
            'login' => $child->parent_allows_login,
            'minecraft' => $child->parent_allows_minecraft,
            'discord' => $child->parent_allows_discord,
        };

        UpdateChildPermission::run($child, $parent, $permission, ! $currentValue);

        $action = ! $currentValue ? 'enabled' : 'disabled';
        $label = match ($permission) {
            'use_site' => 'Account',
            'login' => 'Website login',
            'minecraft' => 'Minecraft access',
            'discord' => 'Discord access',
        };

        Flux::toast("{$label} {$action} for {$child->name}.", 'Permission Updated', variant: 'success');
        unset($this->children);
    }

    public function createChildAccount(): void
    {
        if ($this->isStaffViewing) {
            return;
        }

        $this->authorize('view-parent-portal');

        $parent = $this->getTargetUser();
        if (! $parent->isAtLeastLevel(\App\Enums\MembershipLevel::Traveler)) {
            Flux::toast('You must be a Traveler to add child accounts.', 'Not Eligible', variant: 'danger');

            return;
        }

        $this->validate([
            'newChildName' => ['required', 'string', 'max:32'],
            'newChildEmail' => ['required', 'email', 'max:255', 'unique:users,email'],
            'newChildDob' => ['required', 'date', 'before:today'],
        ]);

        $childAge = \Carbon\Carbon::parse($this->newChildDob)->age;
        if ($childAge >= 17) {
            $this->addError('newChildDob', 'Child accounts are intended for ages 16 and under. Children 17 and older should create their own account.');

            return;
        }

        try {
            CreateChildAccount::run(
                $this->getTargetUser(),
                $this->newChildName,
                $this->newChildEmail,
                $this->newChildDob,
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Failed to create child account', ['error' => $e->getMessage()]);
            Flux::toast('Could not create child account. Please try again.', 'Error', variant: 'danger');
            return;
        }

        $this->reset(['newChildName', 'newChildEmail', 'newChildDob']);
        Flux::modal('create-child-modal')->close();
        Flux::toast('Child account created! A welcome email with account setup instructions has been sent.', 'Account Created', variant: 'success');
        unset($this->children);
    }

    public function releaseToAdult(int $childId): void
    {
        if ($this->isStaffViewing) {
            return;
        }

        $child = User::findOrFail($childId);
        $parent = $this->getTargetUser();

        if (! $parent->children()->where('child_user_id', $child->id)->exists()) {
            Flux::toast('You do not have permission to manage this account.', 'Unauthorized', variant: 'danger');
            return;
        }

        if ($child->age() === null || $child->age() < 17) {
            Flux::toast('Child must be at least 17 to be released to an adult account.', 'Not Eligible', variant: 'danger');
            return;
        }

        ReleaseChildToAdult::run($child, $parent);

        Flux::toast("{$child->name} has been released to a full adult account.", 'Released', variant: 'success');
        unset($this->children);
    }

    public function generateChildMcCode(int $childId): void
    {
        if ($this->isStaffViewing) {
            return;
        }

        $child = User::findOrFail($childId);
        $parent = $this->getTargetUser();

        if (! $parent->children()->where('child_user_id', $child->id)->exists()) {
            Flux::toast('You do not have permission to manage this account.', 'Unauthorized', variant: 'danger');

            return;
        }

        if (! $child->parent_allows_minecraft) {
            Flux::toast('Minecraft access is currently disabled for this child.', 'Not Allowed', variant: 'danger');

            return;
        }

        $username = $this->childMcUsernames[$childId] ?? '';
        $accountTypeStr = $this->childMcAccountTypes[$childId] ?? 'java';

        if (strlen($username) < 3 || strlen($username) > 16) {
            $this->childMcErrors[$childId] = 'Username must be between 3 and 16 characters.';

            return;
        }

        $accountType = $accountTypeStr === 'bedrock'
            ? MinecraftAccountType::Bedrock
            : MinecraftAccountType::Java;

        $result = GenerateVerificationCode::run($child, $accountType, $username);

        if ($result['success']) {
            $this->childMcVerificationCodes[$childId] = $result['code'];
            $this->childMcExpiresAt[$childId] = $result['expires_at']->toIso8601String();
            unset($this->childMcErrors[$childId]);
            Flux::toast("Verification code generated! Have {$child->name} run /verify {$result['code']} in-game.", 'Code Generated', variant: 'success');
        } else {
            $this->childMcErrors[$childId] = $result['error'];
            Flux::toast($result['error'], 'Error', variant: 'danger');
        }
    }

    public function checkChildVerification(int $childId): void
    {
        $code = $this->childMcVerificationCodes[$childId] ?? null;
        if (! $code) {
            return;
        }

        // Scope lookup to the child to prevent code-guessing across users
        $verification = MinecraftVerification::where('code', $code)
            ->where('user_id', $childId)
            ->first();

        if (! $verification) {
            unset($this->childMcVerificationCodes[$childId], $this->childMcExpiresAt[$childId]);

            return;
        }

        if ($verification->status === 'completed') {
            unset($this->childMcVerificationCodes[$childId], $this->childMcExpiresAt[$childId]);
            unset($this->children);
            Flux::toast('Minecraft account verified successfully!', 'Verified', variant: 'success');
        } elseif ($verification->status === 'expired' || $verification->expires_at < now()) {
            unset($this->childMcVerificationCodes[$childId], $this->childMcExpiresAt[$childId]);
            Flux::toast('Verification code expired. Please generate a new one.', 'Expired', variant: 'danger');
        }
    }

    public function openEditChildModal(int $childId): void
    {
        if ($this->isStaffViewing) {
            return;
        }

        $parent = $this->getTargetUser();
        $child = User::findOrFail($childId);

        if (! $parent->children()->where('child_user_id', $child->id)->exists()) {
            Flux::toast('You do not have permission to edit this account.', 'Unauthorized', variant: 'danger');
            return;
        }

        $this->editingChildId = $child->id;
        $this->editChildData = [
            'name' => $child->name,
            'email' => $child->email,
            'date_of_birth' => $child->date_of_birth?->format('Y-m-d') ?? '',
        ];
        Flux::modal('edit-child-modal')->show();
    }

    public function saveChild(): void
    {
        if ($this->isStaffViewing || ! $this->editingChildId) {
            return;
        }

        $this->validate([
            'editChildData.name' => ['required', 'string', 'max:32'],
            'editChildData.email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->editingChildId)],
            'editChildData.date_of_birth' => ['required', 'date', 'before:today'],
        ]);

        $childAge = \Carbon\Carbon::parse($this->editChildData['date_of_birth'])->age;
        if ($childAge > 16) {
            $this->addError('editChildData.date_of_birth', 'Child accounts are intended for ages 16 and under.');
            return;
        }

        $parent = $this->getTargetUser();
        $child = User::findOrFail($this->editingChildId);

        if (! $parent->children()->where('child_user_id', $child->id)->exists()) {
            Flux::toast('You do not have permission to edit this account.', 'Unauthorized', variant: 'danger');
            return;
        }

        $child->update([
            'name' => $this->editChildData['name'],
            'email' => $this->editChildData['email'],
            'date_of_birth' => $this->editChildData['date_of_birth'],
        ]);

        \App\Actions\RecordActivity::run($child, 'update_child_account', "Child account updated by parent {$parent->name}.");

        $this->editingChildId = null;
        Flux::modal('edit-child-modal')->close();
        Flux::toast("Account updated for {$child->name}.", 'Updated', variant: 'success');
        unset($this->children);
    }

    public function getViewingDisciplineReportProperty()
    {
        if (! $this->viewingReportId) {
            return null;
        }

        return \App\Models\DisciplineReport::with('category')
            ->find($this->viewingReportId);
    }

    public function viewDisciplineReport(int $reportId): void
    {
        $report = \App\Models\DisciplineReport::findOrFail($reportId);

        $childIds = $this->getTargetUser()->children()->pluck('child_user_id');
        abort_unless($childIds->contains($report->subject_user_id) && $report->isPublished(), 403);

        $this->viewingReportId = $reportId;
        Flux::modal('view-discipline-report-modal')->show();
    }

    public function confirmRemoveChildMcAccount(int $accountId): void
    {
        if ($this->isStaffViewing) {
            Flux::toast('This view is read-only.', 'Unauthorized', variant: 'danger');
            return;
        }

        $account = \App\Models\MinecraftAccount::findOrFail($accountId);
        $parent = $this->getTargetUser();

        // Verify the parent owns this child before exposing account info
        if (! $parent->children()->where('child_user_id', $account->user_id)->exists()) {
            Flux::toast('You do not have permission to manage this account.', 'Unauthorized', variant: 'danger');
            return;
        }

        $this->accountToRemoveId = $accountId;
        $this->accountToRemoveName = $account->username;
        $this->accountToRemoveChildName = $account->user->name;
        Flux::modal('confirm-remove-mc-account')->show();
    }

    public function removeChildMcAccount(): void
    {
        if ($this->isStaffViewing || ! $this->accountToRemoveId) {
            return;
        }

        $parent = $this->getTargetUser();

        $result = RemoveChildMinecraftAccount::run($parent, $this->accountToRemoveId);

        Flux::modal('confirm-remove-mc-account')->close();
        $this->accountToRemoveId = null;
        $this->accountToRemoveName = '';
        $this->accountToRemoveChildName = '';

        if ($result['success']) {
            unset($this->children);
            Flux::toast($result['message'], 'Account Removed', variant: 'success');
        } else {
            Flux::toast($result['message'], 'Error', variant: 'danger');
        }
    }

    public function removeChildCancelledMcAccount(int $accountId): void
    {
        if ($this->isStaffViewing) {
            Flux::toast('This view is read-only.', 'Unauthorized', variant: 'danger');
            return;
        }

        $account = \App\Models\MinecraftAccount::findOrFail($accountId);
        $parent = $this->getTargetUser();

        if (! $parent->children()->where('child_user_id', $account->user_id)->exists()) {
            Flux::toast('You do not have permission to manage this account.', 'Unauthorized', variant: 'danger');
            return;
        }

        if (! in_array($account->status, [\App\Enums\MinecraftAccountStatus::Cancelled, \App\Enums\MinecraftAccountStatus::Cancelling])) {
            Flux::toast('This account cannot be removed in this way.', 'Error', variant: 'danger');
            return;
        }

        $result = RemoveChildMinecraftAccount::run($parent, $accountId);

        if ($result['success']) {
            unset($this->children);
            Flux::toast($result['message'], 'Account Removed', variant: 'success');
        } else {
            Flux::toast($result['message'], 'Error', variant: 'danger');
        }
    }

    public function restartChildMinecraftVerification(int $accountId): void
    {
        if ($this->isStaffViewing) {
            Flux::toast('This view is read-only.', 'Unauthorized', variant: 'danger');
            return;
        }

        $account = \App\Models\MinecraftAccount::findOrFail($accountId);
        $parent = $this->getTargetUser();

        $result = ParentRegenerateVerificationCode::run($account, $parent);

        if ($result['success']) {
            $childId = $account->user_id;
            $this->childMcVerificationCodes[$childId] = $result['code'];
            $this->childMcExpiresAt[$childId] = $result['expires_at']->toIso8601String();
            unset($this->children);
            Flux::toast('Verification restarted! Have the child run /verify ' . $result['code'] . ' in-game.', 'Verification Restarted', variant: 'success');
        } else {
            Flux::toast($result['error'], 'Error', variant: 'danger');
        }
    }
}; ?>

<div>
    <div class="w-full max-w-4xl mx-auto">
        @if($isStaffViewing)
            <flux:callout variant="info" class="mb-4">
                Viewing parent portal for {{ $this->getTargetUser()->name }} (read-only)
            </flux:callout>
        @endif

        <div class="flex items-center justify-between mb-6">
            <flux:heading size="xl">Parent Portal</flux:heading>
            @if(! $isStaffViewing)
                <flux:modal.trigger name="create-child-modal">
                    <flux:button variant="primary" icon="plus" size="sm">Add Child Account</flux:button>
                </flux:modal.trigger>
            @endif
        </div>

        @if($this->children->isEmpty())
            <flux:card class="text-center py-12">
                <flux:icon name="user-group" class="w-12 h-12 text-zinc-400 mx-auto mb-4" />
                <flux:heading size="lg">No Child Accounts</flux:heading>
                <flux:text variant="subtle" class="mt-2">You don't have any child accounts linked yet. Add a child account or have your child register with your email as their parent email.</flux:text>
            </flux:card>
        @else
            <div class="space-y-6">
                @foreach($this->children as $child)
                    <flux:card wire:key="child-{{ $child->id }}" class="p-6">
                        <div class="flex items-start justify-between mb-4">
                            <div>
                                <flux:heading size="lg">
                                    <flux:link href="{{ route('profile.show', $child) }}">{{ $child->name }}</flux:link>
                                </flux:heading>
                                <flux:text variant="subtle" class="text-sm">
                                    @if($child->age() !== null)
                                        Age {{ $child->age() }} &middot;
                                    @endif
                                    {{ $child->email }}
                                </flux:text>
                            </div>
                            <div class="flex items-center gap-2">
                                @if($child->isInBrig())
                                    <flux:badge color="{{ $child->brig_type?->isDisciplinary() ? 'red' : 'amber' }}" size="sm">
                                        {{ $child->brig_type?->label() ?? 'In the Brig' }}
                                    </flux:badge>
                                @endif
                                @if(! $isStaffViewing)
                                    <flux:button wire:click="openEditChildModal({{ $child->id }})" variant="ghost" size="sm" icon="pencil-square" aria-label="Edit {{ $child->name }}" />
                                @endif
                            </div>
                        </div>

                        @if($child->isInBrig() && $child->brig_reason)
                            <flux:callout variant="{{ $child->brig_type?->isDisciplinary() ? 'danger' : 'warning' }}" class="mb-4">
                                {{ $child->brig_reason }}
                            </flux:callout>
                        @endif

                        {{-- Permissions --}}
                        <div class="mb-4">
                            <flux:text class="font-medium text-sm text-zinc-600 dark:text-zinc-400 uppercase tracking-wide mb-2">Permissions</flux:text>
                            <div class="space-y-3">
                                <div wire:key="perm-{{ $child->id }}-site" class="flex items-center justify-between">
                                    <flux:text>Enable Account</flux:text>
                                    @if($isStaffViewing)
                                        <flux:badge size="sm" color="{{ $child->parent_allows_site ? 'green' : 'red' }}">{{ $child->parent_allows_site ? 'Allowed' : 'Denied' }}</flux:badge>
                                    @else
                                        <flux:switch
                                            wire:key="switch-{{ $child->id }}-site"
                                            wire:click="togglePermission({{ $child->id }}, 'use_site')"
                                            :checked="$child->parent_allows_site"
                                        />
                                    @endif
                                </div>
                                <div wire:key="perm-{{ $child->id }}-login" class="flex items-center justify-between">
                                    <flux:text>Login to Website</flux:text>
                                    @if($isStaffViewing)
                                        <flux:badge size="sm" color="{{ $child->parent_allows_login ? 'green' : 'red' }}">{{ $child->parent_allows_login ? 'Allowed' : 'Denied' }}</flux:badge>
                                    @else
                                        <flux:switch
                                            wire:key="switch-{{ $child->id }}-login"
                                            wire:click="togglePermission({{ $child->id }}, 'login')"
                                            :checked="$child->parent_allows_login"
                                        />
                                    @endif
                                </div>
                                <div wire:key="perm-{{ $child->id }}-minecraft" class="flex items-center justify-between">
                                    <flux:text>Join Minecraft Server</flux:text>
                                    @if($isStaffViewing)
                                        <flux:badge size="sm" color="{{ $child->parent_allows_minecraft ? 'green' : 'red' }}">{{ $child->parent_allows_minecraft ? 'Allowed' : 'Denied' }}</flux:badge>
                                    @else
                                        <flux:switch
                                            wire:key="switch-{{ $child->id }}-minecraft"
                                            wire:click="togglePermission({{ $child->id }}, 'minecraft')"
                                            :checked="$child->parent_allows_minecraft"
                                        />
                                    @endif
                                </div>
                                <div wire:key="perm-{{ $child->id }}-discord" class="flex items-center justify-between">
                                    <flux:text>Join Discord Server</flux:text>
                                    @if($isStaffViewing)
                                        <flux:badge size="sm" color="{{ $child->parent_allows_discord ? 'green' : 'red' }}">{{ $child->parent_allows_discord ? 'Allowed' : 'Denied' }}</flux:badge>
                                    @else
                                        <flux:switch
                                            wire:key="switch-{{ $child->id }}-discord"
                                            wire:click="togglePermission({{ $child->id }}, 'discord')"
                                            :checked="$child->parent_allows_discord"
                                        />
                                    @endif
                                </div>
                            </div>
                        </div>

                        <flux:separator class="my-4" />

                        {{-- Linked Accounts --}}
                        <div class="mb-4">
                            <flux:text class="font-medium text-sm text-zinc-600 dark:text-zinc-400 uppercase tracking-wide mb-2">Linked Accounts</flux:text>

                            @if($child->minecraftAccounts->isNotEmpty())
                                @foreach($child->minecraftAccounts as $mc)
                                    <div wire:key="mc-{{ $mc->id }}" class="flex items-center gap-2 mb-1">
                                        <flux:text class="text-sm">Minecraft: {{ $mc->username }}</flux:text>
                                        <flux:badge size="sm" color="{{ $mc->status->color() }}">{{ $mc->status->label() }}</flux:badge>
                                        @if(! $isStaffViewing && $mc->status === \App\Enums\MinecraftAccountStatus::Active)
                                            <flux:button
                                                wire:click="confirmRemoveChildMcAccount({{ $mc->id }})"
                                                variant="ghost"
                                                size="sm"
                                                icon="x-mark"
                                                class="text-red-500"
                                            />
                                        @elseif(! $isStaffViewing && ($mc->status === \App\Enums\MinecraftAccountStatus::Cancelled || $mc->status === \App\Enums\MinecraftAccountStatus::Cancelling))
                                            <flux:button
                                                wire:click="restartChildMinecraftVerification({{ $mc->id }})"
                                                variant="ghost"
                                                size="sm"
                                                icon="arrow-path"
                                            >Restart</flux:button>
                                            <flux:button
                                                wire:click="removeChildCancelledMcAccount({{ $mc->id }})"
                                                wire:confirm="Remove {{ $mc->username }} from {{ $child->name }}'s account? This cannot be undone."
                                                variant="ghost"
                                                size="sm"
                                                icon="x-mark"
                                                class="text-red-500"
                                            />
                                        @endif
                                    </div>
                                @endforeach
                            @else
                                <flux:text variant="subtle" class="text-sm">No Minecraft accounts linked</flux:text>
                            @endif

                            @if($child->discordAccounts->isNotEmpty())
                                @foreach($child->discordAccounts as $discord)
                                    <div wire:key="discord-{{ $discord->id }}" class="flex items-center gap-2 mb-1">
                                        <flux:text class="text-sm">Discord: {{ $discord->discord_username }}</flux:text>
                                        <flux:badge size="sm" color="{{ $discord->status->color() }}">{{ $discord->status->label() }}</flux:badge>
                                    </div>
                                @endforeach
                            @else
                                <flux:text variant="subtle" class="text-sm">No Discord accounts linked</flux:text>
                            @endif

                            {{-- Link Minecraft Account --}}
                            @if(! $isStaffViewing && $child->parent_allows_minecraft && ! $child->isInBrig())
                                <div class="mt-3 pt-3 border-t border-zinc-200 dark:border-zinc-700">
                                    <flux:text class="font-medium text-sm text-zinc-600 dark:text-zinc-400 mb-2">Link Minecraft Account</flux:text>

                                    @if(isset($this->childMcVerificationCodes[$child->id]))
                                        <div class="p-3 bg-blue-50 dark:bg-blue-950 rounded-lg border border-blue-200 dark:border-blue-800">
                                            <flux:text class="text-sm mb-1">Verification code for {{ $child->name }}:</flux:text>
                                            <div class="font-mono text-2xl font-bold text-blue-600 dark:text-blue-400 tracking-wider my-2">
                                                {{ $this->childMcVerificationCodes[$child->id] }}
                                            </div>
                                            <flux:text class="text-sm">
                                                Have {{ $child->name }} join the server and run:
                                                <code class="px-2 py-0.5 bg-zinc-200 dark:bg-zinc-700 rounded text-xs">/verify {{ $this->childMcVerificationCodes[$child->id] }}</code>
                                            </flux:text>
                                            <div class="mt-2">
                                                <flux:button wire:click="checkChildVerification({{ $child->id }})" variant="ghost" size="sm">
                                                    Check Status
                                                </flux:button>
                                            </div>
                                        </div>
                                    @else
                                        <div class="flex items-end gap-2 flex-wrap">
                                            <div class="flex-1 min-w-[150px]">
                                                <flux:input
                                                    wire:model="childMcUsernames.{{ $child->id }}"
                                                    placeholder="Minecraft username"
                                                    size="sm"
                                                />
                                            </div>
                                            <flux:select wire:model="childMcAccountTypes.{{ $child->id }}" size="sm" class="w-28">
                                                <option value="java">Java</option>
                                                <option value="bedrock">Bedrock</option>
                                            </flux:select>
                                            <flux:button wire:click="generateChildMcCode({{ $child->id }})" variant="primary" size="sm">
                                                Generate Code
                                            </flux:button>
                                        </div>
                                        @if(isset($this->childMcErrors[$child->id]))
                                            <flux:text class="text-sm text-red-500 mt-1">{{ $this->childMcErrors[$child->id] }}</flux:text>
                                        @endif
                                    @endif
                                </div>
                            @endif
                        </div>

                        <flux:separator class="my-4" />

                        {{-- Tickets --}}
                        <div class="mb-4">
                            <flux:text class="font-medium text-sm text-zinc-600 dark:text-zinc-400 uppercase tracking-wide mb-2">Recent Tickets</flux:text>
                            @if($child->relationLoaded('recentTickets') && $child->recentTickets->isNotEmpty())
                                <div class="space-y-1">
                                    @foreach($child->recentTickets as $ticket)
                                        <div wire:key="ticket-{{ $ticket->id }}" class="flex items-center justify-between text-sm">
                                            <flux:link href="{{ route('tickets.show', $ticket) }}" class="text-sm">{{ $ticket->subject }}</flux:link>
                                            <flux:badge size="sm" color="{{ $ticket->status === \App\Enums\ThreadStatus::Open ? 'green' : 'zinc' }}">{{ $ticket->status->label() }}</flux:badge>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <flux:text variant="subtle" class="text-sm">No tickets</flux:text>
                            @endif
                        </div>

                        {{-- Discipline Reports --}}
                        @if($child->relationLoaded('publishedDisciplineReports') && $child->publishedDisciplineReports->isNotEmpty())
                            <flux:separator class="my-4" />
                            <div class="mb-4">
                                <flux:text class="font-medium text-sm text-zinc-600 dark:text-zinc-400 uppercase tracking-wide mb-2">Discipline Reports</flux:text>
                                <div class="space-y-2">
                                    @foreach($child->publishedDisciplineReports as $report)
                                        <div wire:key="report-{{ $report->id }}" class="flex items-center justify-between cursor-pointer hover:bg-zinc-100 dark:hover:bg-zinc-800 rounded p-2"
                                             wire:click="viewDisciplineReport({{ $report->id }})">
                                            <div class="flex-1 min-w-0 mr-2">
                                                <div class="flex items-center gap-2">
                                                    @if($report->category)
                                                        <flux:badge color="{{ $report->category->color }}" size="sm">{{ $report->category->name }}</flux:badge>
                                                    @endif
                                                    <flux:text class="text-sm truncate">{{ Str::limit($report->description, 60) }}</flux:text>
                                                </div>
                                                <flux:text variant="subtle" class="text-xs">
                                                    {{ $report->published_at->format('M j, Y') }}
                                                </flux:text>
                                            </div>
                                            <flux:badge color="{{ $report->severity->color() }}" size="sm">
                                                {{ $report->severity->label() }}
                                            </flux:badge>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        @if(! $isStaffViewing && $child->age() !== null && $child->age() >= 17)
                            <flux:separator class="my-4" />
                            <flux:button
                                wire:click="releaseToAdult({{ $child->id }})"
                                wire:confirm="Are you sure you want to release {{ $child->name }} to a full adult account? This will dissolve all parent-child links and cannot be undone."
                                variant="primary"
                                size="sm"
                            >
                                Release to Adult Account
                            </flux:button>
                        @endif
                    </flux:card>
                @endforeach
            </div>
        @endif
    </div>

    {{-- Create Child Modal --}}
    <flux:modal name="create-child-modal" class="w-full md:w-96">
        <div class="space-y-6">
            <flux:heading size="lg">Add Child Account</flux:heading>
            <flux:text variant="subtle">Create an account for your child. They'll receive a password reset email to set their own password.</flux:text>

            <form wire:submit="createChildAccount" class="space-y-4">
                <flux:field>
                    <flux:label>Username</flux:label>
                    <flux:description>This will be their public display name on the site — use an online nickname, not their real name.</flux:description>
                    <flux:input wire:model="newChildName" required placeholder="Online nickname" maxlength="32" />
                    <flux:error name="newChildName" />
                </flux:field>

                <flux:field>
                    <flux:label>Email</flux:label>
                    <flux:input wire:model="newChildEmail" type="email" required placeholder="child@example.com" />
                    <flux:error name="newChildEmail" />
                </flux:field>

                <flux:field>
                    <flux:label>Date of Birth</flux:label>
                    <flux:input wire:model="newChildDob" type="date" required />
                    <flux:error name="newChildDob" />
                </flux:field>

                <div class="flex gap-2 justify-end">
                    <flux:button variant="ghost" x-on:click="$flux.modal('create-child-modal').close()">Cancel</flux:button>
                    <flux:button type="submit" variant="primary">Create Account</flux:button>
                </div>
            </form>
        </div>
    </flux:modal>

    {{-- Edit Child Modal --}}
    <flux:modal name="edit-child-modal" class="w-full md:w-96">
        <div class="space-y-6">
            <flux:heading size="lg">Edit Child Account</flux:heading>

            <form wire:submit="saveChild" class="space-y-4">
                <flux:field>
                    <flux:label>Username</flux:label>
                    <flux:description>Public display name — use an online nickname, not their real name.</flux:description>
                    <flux:input wire:model="editChildData.name" required maxlength="32" />
                    <flux:error name="editChildData.name" />
                </flux:field>

                <flux:field>
                    <flux:label>Email</flux:label>
                    <flux:input wire:model="editChildData.email" type="email" required />
                    <flux:error name="editChildData.email" />
                </flux:field>

                <flux:field>
                    <flux:label>Date of Birth</flux:label>
                    <flux:input wire:model="editChildData.date_of_birth" type="date" required />
                    <flux:error name="editChildData.date_of_birth" />
                </flux:field>

                <div class="flex gap-2 justify-end">
                    <flux:button variant="ghost" x-on:click="$flux.modal('edit-child-modal').close()">Cancel</flux:button>
                    <flux:button type="submit" variant="primary">Save</flux:button>
                </div>
            </form>
        </div>
    </flux:modal>

    {{-- View Discipline Report Modal --}}
    <flux:modal name="view-discipline-report-modal" class="w-full md:w-1/2">
        @if($this->viewingDisciplineReport)
            @php $viewReport = $this->viewingDisciplineReport; @endphp
            <div class="space-y-4">
                    <flux:heading size="lg">Discipline Report</flux:heading>

                    <div class="grid grid-cols-2 gap-4">
                        @if($viewReport->category)
                            <div>
                                <flux:text class="font-medium text-sm">Category</flux:text>
                                <flux:badge color="{{ $viewReport->category->color }}">{{ $viewReport->category->name }}</flux:badge>
                            </div>
                        @endif
                        <div>
                            <flux:text class="font-medium text-sm">Location</flux:text>
                            <flux:badge color="{{ $viewReport->location->color() }}">{{ $viewReport->location->label() }}</flux:badge>
                        </div>
                        <div>
                            <flux:text class="font-medium text-sm">Severity</flux:text>
                            <flux:badge color="{{ $viewReport->severity->color() }}">{{ $viewReport->severity->label() }}</flux:badge>
                        </div>
                    </div>

                    <div>
                        <flux:text class="font-medium text-sm">What Happened</flux:text>
                        <flux:text>{{ $viewReport->description }}</flux:text>
                    </div>

                    @if($viewReport->witnesses)
                        <div>
                            <flux:text class="font-medium text-sm">Witnesses</flux:text>
                            <flux:text>{{ $viewReport->witnesses }}</flux:text>
                        </div>
                    @endif

                    <div>
                        <flux:text class="font-medium text-sm">Actions Taken</flux:text>
                        <flux:text>{{ $viewReport->actions_taken }}</flux:text>
                    </div>

                    <div>
                        <flux:text class="font-medium text-sm">Date</flux:text>
                        <flux:text>{{ $viewReport->published_at->format('M j, Y g:i A') }}</flux:text>
                    </div>
            </div>
        @endif
    </flux:modal>

    {{-- Remove MC Account Confirmation Modal --}}
    <flux:modal name="confirm-remove-mc-account" class="min-w-[22rem] space-y-6">
        <flux:heading size="lg">Remove Minecraft Account</flux:heading>
        <flux:text>
            Are you sure you want to remove <strong>{{ $accountToRemoveName }}</strong> from {{ $accountToRemoveChildName }}'s account?
            This will remove them from the server whitelist.
        </flux:text>
        <div class="flex gap-2 justify-end">
            <flux:button variant="ghost" x-on:click="$flux.modal('confirm-remove-mc-account').close()">Cancel</flux:button>
            <flux:button wire:click="removeChildMcAccount" variant="danger">Remove Account</flux:button>
        </div>
    </flux:modal>
</div>
