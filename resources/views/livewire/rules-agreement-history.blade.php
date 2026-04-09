<?php

use App\Models\UserRuleAgreement;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    public string $search = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function getAgreements()
    {
        return UserRuleAgreement::with(['user', 'ruleVersion'])
            ->when($this->search, function ($q) {
                $q->whereHas('user', fn ($uq) => $uq->where(function ($inner) {
                    $inner->where('name', 'like', '%'.$this->search.'%')
                        ->orWhere('email', 'like', '%'.$this->search.'%');
                }));
            })
            ->orderByDesc('agreed_at')
            ->paginate(25);
    }
}; ?>

<div class="space-y-4">
    <flux:heading size="xl">Rules Agreement History</flux:heading>
    <flux:text variant="subtle">Full record of which users have agreed to which rules versions.</flux:text>

    <flux:input wire:model.live.debounce="search" placeholder="Search by name or email..." icon="magnifying-glass" class="max-w-xs" />

    <flux:table>
        <flux:table.columns>
            <flux:table.column>User</flux:table.column>
            <flux:table.column>Version</flux:table.column>
            <flux:table.column>Agreed At</flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
            @forelse ($this->getAgreements() as $agreement)
                <flux:table.row wire:key="agreement-{{ $agreement->id }}">
                    <flux:table.cell>
                        <div class="font-medium">{{ $agreement->user?->name ?? '—' }}</div>
                        <div class="text-xs text-zinc-400">{{ $agreement->user?->email ?? '' }}</div>
                    </flux:table.cell>
                    <flux:table.cell>
                        <flux:badge variant="primary" size="sm">v{{ $agreement->ruleVersion?->version_number ?? '?' }}</flux:badge>
                    </flux:table.cell>
                    <flux:table.cell>
                        {{ $agreement->agreed_at?->format('M j, Y g:ia') ?? '—' }}
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="3">
                        <flux:text variant="subtle">No agreement records found.</flux:text>
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    {{ $this->getAgreements()->links() }}
</div>
