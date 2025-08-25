<x-layouts.app>
    <!-- Alert for any meeting currently in progress -->
    <livewire:dashboard.alert-in-progress-meeting />

    <!-- Notification-style popups (top-right, stacked, newest first) -->
    <livewire:dashboard.notifications />

    <!-- Quick access buttons for widgets (open in modals) -->
    <div class="mt-4 flex flex-wrap items-center gap-2">
        <livewire:dashboard.view-rules />

        <flux:modal.trigger name="dashboard-announcements-widget">
            <flux:button size="sm" icon="megaphone">Open Announcements</flux:button>
        </flux:modal.trigger>

        <flux:modal.trigger name="dashboard-blogs-widget">
            <flux:button size="sm" icon="book-open">Open Blogs</flux:button>
        </flux:modal.trigger>
    </div>

    <!-- Modals for quick widgets -->
    <flux:modal name="dashboard-announcements-widget" class="w-full md:w-3/4 xl:w-1/2">
        <flux:heading size="lg" class="mb-4">Recent Announcements</flux:heading>
        <livewire:dashboard.announcements-widget />
    </flux:modal>

    <flux:modal name="dashboard-blogs-widget" class="w-full md:w-3/4 xl:w-1/2">
        <flux:heading size="lg" class="mb-4">Community Blogs</flux:heading>
        <livewire:dashboard.blogs-widget />
    </flux:modal>

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

    {{-- <!-- Stowaway widget (contained and horizontally scrollable with controls) -->
    @can('manage-stowaway-users')
        <div
            x-data="{
                leftOffset: 0,
                update() {
                    const s = document.querySelector('[data-flux-sidebar]');
                    // Only offset on large screens where the sidebar is visible inline
                    this.leftOffset = (window.innerWidth >= 1024 && s) ? s.getBoundingClientRect().width : 0;
                },
            }"
            x-init="
                update();
                window.addEventListener('resize', () => update());
                const s = document.querySelector('[data-flux-sidebar]');
                if (window.ResizeObserver && s) {
                    const ro = new ResizeObserver(() => update());
                    ro.observe(s);
                }
            "
            x-bind:style="'left:' + leftOffset + 'px; width: calc(100vw - ' + leftOffset + 'px)';"
            class="fixed bottom-0 right-0 z-30 px-2 sm:px-4 md:px-10 pb-4"
        >
            <!-- Hide scrollbars for this scroller only -->
            <style>
                .stowaway-scroll{scrollbar-width:none;-ms-overflow-style:none}
                .stowaway-scroll::-webkit-scrollbar{display:none}
            </style>

            <div class="relative w-full">
                <div class="relative w-full rounded-xl bg-white/10 shadow-lg ring-1 ring-white/15 backdrop-blur dark:bg-zinc-900/50 dark:ring-white/10 mx-auto">
                    <!-- edge fades to hint scrollability -->
                    <div class="pointer-events-none absolute inset-y-0 left-0 w-16 lg:w-24 rounded-l-xl bg-gradient-to-r from-black/10 to-transparent dark:from-black/30"></div>
                    <div class="pointer-events-none absolute inset-y-0 right-0 w-16 lg:w-24 rounded-r-xl bg-gradient-to-l from-black/10 to-transparent dark:from-black/30"></div>

                    <!-- overlay controls -->
                    <div class="pointer-events-none absolute inset-0 flex items-center justify-between px-4 lg:px-6">
                        <button
                            type="button"
                            class="pointer-events-auto -ml-1 inline-flex h-9 w-9 items-center justify-center rounded-full bg-white/70 text-zinc-800 ring-1 ring-white/40 backdrop-blur hover:bg-white/90 shadow dark:bg-zinc-800/70 dark:text-zinc-100 dark:ring-white/20 dark:hover:bg-zinc-800/80"
                            aria-label="Scroll left"
                            title="Scroll left"
                            @click="$refs.stowawayScroller.scrollBy({ left: -240, behavior: 'smooth' })"
                        >
                            <flux:icon name="chevron-left" class="size-4" />
                        </button>

                        <button
                            type="button"
                            class="pointer-events-auto -mr-1 inline-flex h-9 w-9 items-center justify-center rounded-full bg-white/70 text-zinc-800 ring-1 ring-white/40 backdrop-blur hover:bg-white/90 shadow dark:bg-zinc-800/70 dark:text-zinc-100 dark:ring-white/20 dark:hover:bg-zinc-800/80"
                            aria-label="Scroll right"
                            title="Scroll right"
                            @click="$refs.stowawayScroller.scrollBy({ left: 240, behavior: 'smooth' })"
                        >
                            <flux:icon name="chevron-right" class="size-4" />
                        </button>
                    </div>

                    <div class="stowaway-scroll w-full overflow-x-auto pl-24 pr-24 py-3 scroll-smooth" x-ref="stowawayScroller">
                        <div class="flex justify-center gap-4 snap-x snap-mandatory">
                            <div class="min-w-[900px] max-w-full snap-center">
                                <livewire:dashboard.stowaway-users-widget />
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endcan --}}

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
