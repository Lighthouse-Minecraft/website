<?php

use App\Enums\ApplicationStatus;
use App\Models\StaffApplication;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    public string $statusFilter = '';

    public function mount(): void
    {
        $this->authorize('viewAny', StaffApplication::class);
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function getApplicationsProperty()
    {
        $query = StaffApplication::with(['user', 'staffPosition'])
            ->latest();

        if ($this->statusFilter) {
            $query->where('status', $this->statusFilter);
        }

        return $query->paginate(20);
    }
}; ?>

<section>
    <div class="max-w-5xl px-4 py-8 mx-auto">
        <flux:heading size="2xl" class="mb-6">Review Applications</flux:heading>

        <div class="flex items-center gap-4 mb-4">
            <flux:select wire:model.live="statusFilter" class="w-48">
                <flux:select.option value="">All Statuses</flux:select.option>
                @foreach(ApplicationStatus::cases() as $status)
                    <flux:select.option value="{{ $status->value }}">{{ $status->label() }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>

        <flux:table>
            <flux:table.columns>
                <flux:table.column>Applicant</flux:table.column>
                <flux:table.column>Position</flux:table.column>
                <flux:table.column>Department</flux:table.column>
                <flux:table.column>Status</flux:table.column>
                <flux:table.column>Submitted</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse($this->applications as $app)
                    <flux:table.row wire:key="review-{{ $app->id }}">
                        <flux:table.cell>
                            <flux:link href="{{ route('profile.show', $app->user) }}" wire:navigate>{{ $app->user->name }}</flux:link>
                        </flux:table.cell>
                        <flux:table.cell>{{ $app->staffPosition->title }}</flux:table.cell>
                        <flux:table.cell>{{ $app->staffPosition->department->label() }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" color="{{ $app->status->color() }}">{{ $app->status->label() }}</flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>{{ $app->created_at->format('M j, Y') }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:button href="{{ route('admin.applications.show', $app) }}" variant="ghost" size="sm" icon="eye" wire:navigate>Review</flux:button>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="6" class="text-center">
                            <flux:text variant="subtle">No applications found.</flux:text>
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>

        <div class="mt-4">{{ $this->applications->links() }}</div>
    </div>
</section>
