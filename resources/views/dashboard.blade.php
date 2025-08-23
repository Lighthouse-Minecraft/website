<x-layouts.app>
    <div class="w-full flex justify-center mt-4">
        @include('components.discord-banner-modal')
    </div>

    <livewire:dashboard.alert-in-progress-meeting />
    <livewire:dashboard.view-announcements />

    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
        <livewire:dashboard.view-rules />

        <livewire:dashboard.announcements-widget />

        @can('manage-stowaway-users')
            <livewire:dashboard.stowaway-users-widget />
        @endcan
    </div>
</x-layouts.app>
