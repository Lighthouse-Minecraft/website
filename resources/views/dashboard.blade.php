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
            @can('view-community-content')
                <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
                    <livewire:dashboard.announcements-widget />

                    <flux:card>
                        <flux:heading size="md">Account Linking</flux:heading>
                        <flux:separator variant="subtle" class="my-2" />

                        @if(auth()->user()->isAtLeastLevel(App\Enums\MembershipLevel::Traveler))
                            <div class="flex flex-col gap-3 mt-2">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <flux:text class="font-medium">Minecraft</flux:text>
                                        <flux:text variant="subtle" class="text-sm">
                                            @if(auth()->user()->minecraftAccounts()->exists())
                                                {{ auth()->user()->minecraftAccounts()->count() }} account(s) linked
                                            @else
                                                Link your account to join the server
                                            @endif
                                        </flux:text>
                                    </div>
                                    <flux:button href="{{ route('settings.minecraft-accounts') }}" size="xs" variant="primary">
                                        Manage
                                    </flux:button>
                                </div>

                                <div class="flex items-center justify-between">
                                    <div>
                                        <flux:text class="font-medium">Discord</flux:text>
                                        <flux:text variant="subtle" class="text-sm">
                                            @if(auth()->user()->discordAccounts()->exists())
                                                {{ auth()->user()->discordAccounts()->count() }} account(s) linked
                                            @else
                                                Link your account for role sync and DM notifications
                                            @endif
                                        </flux:text>
                                    </div>
                                    <flux:button href="{{ route('settings.discord-account') }}" size="xs" variant="primary">
                                        Manage
                                    </flux:button>
                                </div>
                            </div>
                        @else
                            <flux:text variant="subtle" class="mt-2">
                                Account linking will be unlocked once staff approves your account and you are promoted to Traveler.
                            </flux:text>
                        @endif
                    </flux:card>

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
            @else
                <livewire:dashboard.in-brig-card />
            @endcan
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
