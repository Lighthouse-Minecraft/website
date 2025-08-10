<x-layouts.app>
    <livewire:dashboard.view-announcements />

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        <div>
            <livewire:dashboard.view-rules />
        </div>

        <div>
            <livewire:dashboard.announcements-widget />
        </div>
    </div>
</x-layouts.app>
