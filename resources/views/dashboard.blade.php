<x-layouts.app>
    <livewire:dashboard.alert-in-progress-meeting />
    <livewire:dashboard.view-announcements />


    <div class="w-full bg-zinc-900 p-4 rounded-lg mb-6">
        <div class="flex mb-4">
            <flux:heading>Community</flux:heading>
            <flux:spacer />
            <flux:button href="{{ route('rules.show') }}" size="xs">View Rules</flux:button>
        </div>

        @can('view-community-content')
            @php $authUser = auth()->user(); @endphp

            @if($authUser->shouldShowOnboardingWizard())
                <livewire:onboarding.wizard />
            @else
            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
                <livewire:dashboard.announcements-widget />

                @php
                    $showDiscordLink = $authUser->can('link-discord') && $authUser->discordAccounts()->active()->doesntExist();
                    $showMinecraftLink = $authUser->can('link-minecraft-account') && $authUser->minecraftAccounts()->active()->doesntExist();
                    $showSetupCard = $showDiscordLink || $showMinecraftLink;
                @endphp

                @if($showSetupCard)
                    <flux:card class="border border-indigo-500/40 bg-indigo-950/20">
                        <flux:heading size="md" class="text-indigo-300">Complete Your Setup</flux:heading>
                        <flux:text variant="subtle" class="text-sm mt-1">
                            Connect your accounts to get the most out of Lighthouse.
                        </flux:text>
                        <flux:separator variant="subtle" class="my-3" />

                        <div class="flex flex-col gap-3">
                            @if($showDiscordLink)
                                <div class="flex items-center justify-between">
                                    <div>
                                        <flux:text class="font-medium">Discord</flux:text>
                                        <flux:text variant="subtle" class="text-sm">Get server roles and DM notifications</flux:text>
                                    </div>
                                    <flux:button href="{{ route('settings.discord-account') }}" size="sm" variant="primary">
                                        Link Discord
                                    </flux:button>
                                </div>
                            @endif

                            @if($showMinecraftLink)
                                <div class="flex items-center justify-between">
                                    <div>
                                        <flux:text class="font-medium">Minecraft</flux:text>
                                        <flux:text variant="subtle" class="text-sm">Link your account to join the server</flux:text>
                                    </div>
                                    <flux:button href="{{ route('settings.minecraft-accounts') }}" size="sm" variant="primary">
                                        Link Minecraft Account
                                    </flux:button>
                                </div>
                            @endif
                        </div>
                    </flux:card>
                @else
                    <flux:card>
                        <flux:heading size="sm">My Account</flux:heading>
                        <flux:separator variant="subtle" class="my-3" />
                        <div class="flex flex-col gap-2">
                            <flux:button href="{{ route('settings.discord-account') }}" size="xs" variant="ghost" class="justify-start">
                                Discord Account
                            </flux:button>
                            <flux:button href="{{ route('settings.minecraft-accounts') }}" size="xs" variant="ghost" class="justify-start">
                                Minecraft Accounts
                            </flux:button>
                            <flux:button href="{{ route('settings.notifications') }}" size="xs" variant="ghost" class="justify-start">
                                Notification Preferences
                            </flux:button>
                        </div>
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

            @can('view-community-stories')
                <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4 mt-4">
                    <div class="md:col-span-2">
                        <livewire:dashboard.community-question-widget />
                    </div>
                </div>
            @endcan
            @endif
        @else
            <livewire:dashboard.in-brig-card />
        @endcan

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

    @can('view-ready-room')
        <div class="w-full bg-zinc-900 p-4 rounded-lg mb-6">
            <div class="flex mb-4">
                <flux:heading>Staff Dashboard</flux:heading>
                <flux:spacer />
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
                @can('manage-stowaway-users')
                    <livewire:dashboard.stowaway-users-widget />
                @endcan

                @can('manage-traveler-users')
                    <livewire:dashboard.traveler-users-widget />
                @endcan

                @canany(['manage-discipline-reports', 'publish-discipline-reports'])
                    <livewire:dashboard.discipline-reports-widget />
                @endcanany
            </div>
        </div>
    @endcan

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
