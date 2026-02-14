<x-layouts.app>
    <div class="mb-6 flex items-center justify-between">
        <flux:heading size="xl">Staff Ready Room</flux:heading>
        <flux:button href="{{ route('tickets.index') }}" wire:navigate icon="inbox">
            View Tickets
        </flux:button>
    </div>

    <div class="w-full mx-auto">
        <livewire:dashboard.ready-room />
    </div>
</x-layouts.app>
