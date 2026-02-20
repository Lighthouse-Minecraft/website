<x-layouts.app>
    <livewire:dashboard.alert-in-progress-meeting />
    <livewire:dashboard.view-announcements />


    <div class="w-full bg-zinc-900 p-4 rounded-lg mb-6">
        <div class="flex mb-4">
            <flux:heading>Community</flux:heading>
            <flux:spacer />
            @if (auth()->user()->rules_accepted_at)
                <livewire:dashboard.view-rules />
            @endif
        </div>

        @if (!auth()->user()->rules_accepted_at)
            <div class="flex flex-col items-center justify-center py-12">
                <flux:text class="text-center mb-6 text-lg">
                    Welcome to Lighthouse! Please read and accept our community rules to get started.
                </flux:text>
                <livewire:dashboard.view-rules />
            </div>
        @else
            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
                <livewire:dashboard.announcements-widget />

                @if(auth()->user()->minecraftAccounts()->doesntExist())
                    <flux:card class="flex flex-col items-center justify-center gap-3 py-8 text-center">
                        <flux:heading size="md">Minecraft Account</flux:heading>
                        <flux:text variant="subtle">Link your Minecraft account to join the server.</flux:text>
                        <flux:button href="{{ route('settings.minecraft-accounts') }}" variant="primary" icon="plus">
                            Add Your Minecraft Account
                        </flux:button>
                    </flux:card>
                @endif

                <flux:card>
                    <flux:heading>Donations</flux:heading>
                    <flux:separator variant="subtle" class="my-2" />
                    <div class="flex mt-4">
                        <flux:button size="xs" href="{{ route('donate') }}" variant="primary" color="sky">Support Lighthouse</flux:button>
                        <flux:spacer />
                        <flux:button href="{{  config('lighthouse.stripe.customer_portal_url') }}" size="xs">Manage Subscription</flux:button>
                    </div>

                </flux:card>
            </div>
        @endif

    </div>

    <div class="w-full bg-zinc-900 p-4 rounded-lg mb-6">
        <div class="flex mb-4">
            <flux:heading>Spiritual Discipleship</flux:heading>
            <flux:spacer />
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
            @can('viewPrayer', App\Models\PrayerCountry::class)
                <livewire:prayer.prayer-widget />
                <livewire:prayer.prayer-graph />
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

                @can('manage-traveler-users')
                    <livewire:dashboard.traveler-users-widget />
                @endcan
            </div>
        </div>
    @endif
</x-layouts.app>
