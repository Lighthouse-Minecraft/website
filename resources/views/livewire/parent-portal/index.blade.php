<?php

use App\Actions\CreateChildAccount;
use App\Actions\ReleaseChildToAdult;
use App\Actions\UpdateChildPermission;
use App\Enums\ThreadStatus;
use App\Enums\ThreadType;
use App\Models\Thread;
use App\Models\User;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Volt\Component;

new class extends Component {
    public string $newChildName = '';
    public string $newChildEmail = '';
    public string $newChildDob = '';

    public function mount(): void
    {
        $this->authorize('view-parent-portal');
    }

    #[Computed]
    public function children()
    {
        return Auth::user()->children()
            ->with(['minecraftAccounts', 'discordAccounts'])
            ->get();
    }

    public function togglePermission(int $childId, string $permission): void
    {
        $child = User::findOrFail($childId);
        $parent = Auth::user();

        // Authorize via policy
        if (! $parent->children()->where('child_user_id', $child->id)->exists()) {
            Flux::toast('You do not have permission to manage this account.', 'Unauthorized', variant: 'danger');
            return;
        }

        $currentValue = match ($permission) {
            'use_site' => $child->parent_allows_site,
            'minecraft' => $child->parent_allows_minecraft,
            'discord' => $child->parent_allows_discord,
        };

        UpdateChildPermission::run($child, $parent, $permission, ! $currentValue);

        $action = ! $currentValue ? 'enabled' : 'disabled';
        $label = match ($permission) {
            'use_site' => 'Site access',
            'minecraft' => 'Minecraft access',
            'discord' => 'Discord access',
        };

        Flux::toast("{$label} {$action} for {$child->name}.", 'Permission Updated', variant: 'success');
        unset($this->children);
    }

    public function createChildAccount(): void
    {
        $this->authorize('view-parent-portal');

        $this->validate([
            'newChildName' => ['required', 'string', 'max:255'],
            'newChildEmail' => ['required', 'email', 'max:255', 'unique:users,email'],
            'newChildDob' => ['required', 'date', 'before:today'],
        ]);

        CreateChildAccount::run(
            Auth::user(),
            $this->newChildName,
            $this->newChildEmail,
            $this->newChildDob,
        );

        $this->reset(['newChildName', 'newChildEmail', 'newChildDob']);
        Flux::modal('create-child-modal')->close();
        Flux::toast('Child account created! A password reset email has been sent.', 'Account Created', variant: 'success');
        unset($this->children);
    }

    public function releaseToAdult(int $childId): void
    {
        $child = User::findOrFail($childId);
        $parent = Auth::user();

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

    public function getChildTickets(User $child)
    {
        return Thread::where('created_by_user_id', $child->id)
            ->where('type', ThreadType::Ticket)
            ->whereIn('status', [ThreadStatus::Open, ThreadStatus::Closed, ThreadStatus::Resolved])
            ->latest()
            ->limit(10)
            ->get();
    }
}; ?>

<div>
    <div class="w-full max-w-4xl mx-auto">
        <div class="flex items-center justify-between mb-6">
            <flux:heading size="xl">Parent Portal</flux:heading>
            <flux:modal.trigger name="create-child-modal">
                <flux:button variant="primary" icon="plus" size="sm">Add Child Account</flux:button>
            </flux:modal.trigger>
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
                    <flux:card class="p-6">
                        <div class="flex items-start justify-between mb-4">
                            <div>
                                <flux:heading size="lg">{{ $child->name }}</flux:heading>
                                <flux:text variant="subtle" class="text-sm">
                                    @if($child->age() !== null)
                                        Age {{ $child->age() }} &middot;
                                    @endif
                                    {{ $child->email }}
                                </flux:text>
                            </div>
                            @if($child->isInBrig())
                                <flux:badge color="{{ $child->brig_type?->isDisciplinary() ? 'red' : 'amber' }}" size="sm">
                                    {{ $child->brig_type?->label() ?? 'In the Brig' }}
                                </flux:badge>
                            @endif
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
                                <div class="flex items-center justify-between">
                                    <flux:text>Use the Site</flux:text>
                                    <flux:switch
                                        wire:click="togglePermission({{ $child->id }}, 'use_site')"
                                        :checked="$child->parent_allows_site"
                                    />
                                </div>
                                <div class="flex items-center justify-between">
                                    <flux:text>Join Minecraft Server</flux:text>
                                    <flux:switch
                                        wire:click="togglePermission({{ $child->id }}, 'minecraft')"
                                        :checked="$child->parent_allows_minecraft"
                                    />
                                </div>
                                <div class="flex items-center justify-between">
                                    <flux:text>Join Discord Server</flux:text>
                                    <flux:switch
                                        wire:click="togglePermission({{ $child->id }}, 'discord')"
                                        :checked="$child->parent_allows_discord"
                                    />
                                </div>
                            </div>
                        </div>

                        <flux:separator class="my-4" />

                        {{-- Linked Accounts --}}
                        <div class="mb-4">
                            <flux:text class="font-medium text-sm text-zinc-600 dark:text-zinc-400 uppercase tracking-wide mb-2">Linked Accounts</flux:text>

                            @if($child->minecraftAccounts->isNotEmpty())
                                @foreach($child->minecraftAccounts as $mc)
                                    <div class="flex items-center gap-2 mb-1">
                                        <flux:text class="text-sm">Minecraft: {{ $mc->username }}</flux:text>
                                        <flux:badge size="sm" color="{{ $mc->status->color() }}">{{ $mc->status->label() }}</flux:badge>
                                    </div>
                                @endforeach
                            @else
                                <flux:text variant="subtle" class="text-sm">No Minecraft accounts linked</flux:text>
                            @endif

                            @if($child->discordAccounts->isNotEmpty())
                                @foreach($child->discordAccounts as $discord)
                                    <div class="flex items-center gap-2 mb-1">
                                        <flux:text class="text-sm">Discord: {{ $discord->discord_username }}</flux:text>
                                        <flux:badge size="sm" color="{{ $discord->status->color() }}">{{ $discord->status->label() }}</flux:badge>
                                    </div>
                                @endforeach
                            @else
                                <flux:text variant="subtle" class="text-sm">No Discord accounts linked</flux:text>
                            @endif
                        </div>

                        <flux:separator class="my-4" />

                        {{-- Tickets --}}
                        <div class="mb-4">
                            <flux:text class="font-medium text-sm text-zinc-600 dark:text-zinc-400 uppercase tracking-wide mb-2">Recent Tickets</flux:text>
                            @php $tickets = $this->getChildTickets($child); @endphp
                            @if($tickets->isNotEmpty())
                                <div class="space-y-1">
                                    @foreach($tickets as $ticket)
                                        <div class="flex items-center justify-between text-sm">
                                            <flux:text>{{ $ticket->subject }}</flux:text>
                                            <flux:badge size="sm" color="{{ $ticket->status === \App\Enums\ThreadStatus::Open ? 'green' : 'zinc' }}">{{ $ticket->status->label() }}</flux:badge>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <flux:text variant="subtle" class="text-sm">No tickets</flux:text>
                            @endif
                        </div>

                        @if($child->age() !== null && $child->age() >= 17)
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
                    <flux:label>Name</flux:label>
                    <flux:input wire:model="newChildName" required placeholder="Child's name or nickname" />
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
</div>
