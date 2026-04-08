<x-layouts.app>
    <div class="mb-6 flex items-center justify-between">
        <flux:heading size="xl">Staff Ready Room</flux:heading>
        <div class="flex gap-2">
            @can('review-staff-applications')
                <flux:button href="{{ route('admin.applications.index') }}" wire:navigate icon="document-text">
                    View Applications
                </flux:button>
            @endcan
            <flux:button href="{{ route('tickets.index') }}" wire:navigate icon="inbox">
                View Tickets
            </flux:button>
            @can('finance-view')
                <flux:button href="{{ route('finance.journal.index') }}" wire:navigate icon="banknotes">
                    Finance
                </flux:button>
            @endcan
            @can('manage-blog')
                <flux:button href="{{ route('blog.manage') }}" wire:navigate icon="pencil-square">
                    Blog Management
                </flux:button>
            @endcan
        </div>
    </div>

    <div class="w-full mx-auto">
        <livewire:dashboard.ready-room />
    </div>
</x-layouts.app>
