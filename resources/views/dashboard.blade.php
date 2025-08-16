<x-layouts.app>
    <livewire:dashboard.alert-in-progress-meeting />
    <livewire:dashboard.view-announcements />

    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
        <livewire:dashboard.view-rules />

        <livewire:dashboard.announcements-widget />
        {{-- <livewire:dashboard.view-upcoming-meetings /> --}}
    </div>
</x-layouts.app>
