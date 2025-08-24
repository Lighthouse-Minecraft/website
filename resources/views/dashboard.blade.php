<x-layouts.app>
    <livewire:dashboard.alert-in-progress-meeting />
    <livewire:dashboard.view-announcements />


    <div class="w-full bg-zinc-900 p-4 rounded-lg mb-6">
        <div class="flex mb-4">
            <flux:heading>Community</flux:heading>
            <flux:spacer />
            <livewire:dashboard.view-rules />
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
            <livewire:dashboard.announcements-widget />
        </div>
    </div>

    <div class="w-full bg-zinc-900 p-4 rounded-lg mb-6">
        <div class="flex mb-4">
            <flux:heading>Spiritual Discipleship</flux:heading>
            <flux:spacer />
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
            @can('viewPrayer', App\Models\PrayerCountry::class)
                <livewire:prayer.prayer-widget />
            @endcan
        </div>
    </div>

    @if (auth()->user()->isAtLeastRank(App\Enums\StaffRank::Officer) || auth()->user()->isInDepartment(App\Enums\StaffDepartment::Quartermaster) || auth()->user()->isAdmin())
        <div class="w-full bg-zinc-900 p-4 rounded-lg mb-6">
            <div class="flex mb-4">
                <flux:heading>Quartermaster</flux:heading>
                <flux:spacer />
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
                @can('manage-stowaway-users')
                    <livewire:dashboard.stowaway-users-widget />
                @endcan
            </div>
        </div>
    @endif
</x-layouts.app>
