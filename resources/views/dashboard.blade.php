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
                    <livewire:dashboard.community-question-widget />
                    <livewire:dashboard.announcements-widget />

                    <flux:card>
                        <flux:heading size="md">Account Linking</flux:heading>
                        <flux:separator variant="subtle" class="my-2" />

                        @canany(['link-minecraft-account', 'link-discord'])
                            <div class="flex flex-col gap-3 mt-2">
                                @can('link-minecraft-account')
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <flux:text class="font-medium">Minecraft</flux:text>
                                            <flux:text variant="subtle" class="text-sm">
                                                @php $mcCount = auth()->user()->minecraftAccounts()->countingTowardLimit()->count(); @endphp
                                                @if($mcCount > 0)
                                                    {{ $mcCount }} account(s) linked
                                                @else
                                                    Link your account to join the server
                                                @endif
                                            </flux:text>
                                        </div>
                                        <flux:button href="{{ route('settings.minecraft-accounts') }}" size="xs" variant="primary">
                                            Manage
                                        </flux:button>
                                    </div>
                                @endcan

                                @can('link-discord')
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <flux:text class="font-medium">Discord</flux:text>
                                            <flux:text variant="subtle" class="text-sm">
                                                @php $discordCount = auth()->user()->discordAccounts()->count(); @endphp
                                                @if($discordCount > 0)
                                                    {{ $discordCount }} account(s) linked
                                                @else
                                                    Link your account for role sync and DM notifications
                                                @endif
                                            </flux:text>
                                        </div>
                                        <flux:button href="{{ route('settings.discord-account') }}" size="xs" variant="primary">
                                            Manage
                                        </flux:button>
                                    </div>
                                @endcan
                            </div>
                        @else
                            <flux:text variant="subtle" class="mt-2">
                                Account linking is not currently available for your account.
                            </flux:text>
                        @endcanany
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

    @canany(['manage-stowaway-users', 'manage-traveler-users'])
        <div class="w-full bg-zinc-900 p-4 rounded-lg mb-6">
            <div class="flex mb-4">
                <flux:heading>User Management</flux:heading>
                <flux:spacer />
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
                @can('manage-stowaway-users')
                    <livewire:dashboard.stowaway-users-widget />
                @endcan

                @can('manage-traveler-users')
                    <livewire:dashboard.traveler-users-widget />
                @endcan

                @can('manage-discipline-reports')
                    <livewire:dashboard.discipline-reports-widget />
                @endcan
            </div>
        </div>
    @endcanany

    @can('view-command-dashboard')
        <div class="w-full bg-zinc-900 p-4 rounded-lg mb-6">
            <div class="flex mb-4">
                <flux:heading>Command Staff</flux:heading>
                <flux:spacer />
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <livewire:dashboard.command-community-engagement />
                <livewire:dashboard.command-department-engagement />
            </div>

            <div class="mt-4">
                <livewire:dashboard.command-staff-engagement />
            </div>
        </div>
    @endcan
</x-layouts.app>
