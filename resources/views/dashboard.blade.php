<x-layouts.app>
    <livewire:dashboard.alert-in-progress-meeting />

    <div class="flex flex-col space-y-8">

    <!-- Top: Announcements & Blogs -->
    <div class="flex flex-row space-x-8">
        <div class="flex-1">
            <livewire:dashboard.view-announcements />
        </div>
        <div class="flex-1">
            <livewire:dashboard.view-blogs />
        </div>
    </div>

    <!-- Middle: Rules -->
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
        <livewire:dashboard.view-rules />
    </div>

    <!-- Bottom: Widgets -->
    <div class="flex flex-row space-x-8 mt-8">
        <div class="flex-1 overflow-y-auto max-h-64">
            <livewire:dashboard.announcements-widget />
        </div>
        <div class="flex-1 overflow-y-auto max-h-64">
            <livewire:dashboard.blogs-widget />
        </div>

        @can('manage-stowaway-users')
            <livewire:dashboard.stowaway-users-widget />
        @endcan
    </div>
</x-layouts.app>
