<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
        <!-- Lighthouse Layout -->
    </head>
    <body class="min-h-screen bg-gray-200 dark:bg-zinc-800">
        <flux:sidebar sticky stashable class="border-r border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:sidebar.toggle class="lg:hidden" icon="x-mark" />

            <a href="{{ route('pages.show', 'home') }}" class="mr-5 flex items-center space-x-2" wire:navigate>
                <x-app-logo class="size-8" href="#"></x-app-logo>
            </a>

            <flux:navlist variant="outline">
                <flux:navlist.group heading="Pages" class="grid my-4">
                    @foreach(\App\Models\Page::where('is_published', operator: true)->get() as $page)
                        <flux:navlist.item
                            icon="document-text"
                            :href="route('pages.show', $page->slug)"
                            :current="url()->current() === route('pages.show', $page->slug)"
                            wire:navigate
                        >
                            {{ $page->title }}
                        </flux:navlist.item>
                    @endforeach
                </flux:navlist.group>

                <flux:navlist.group heading="Platform" class="grid">
                    <flux:navlist.item icon="home" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate class="mb-2">Dashboard</flux:navlist.item>

                    @can('view-community-updates')
                        <flux:navlist.item icon="user-group" :href="route('community-updates.index')" :current="request()->routeIs('community-updates.index')" wire:navigate>Community Updates</flux:navlist.item>
                    @endcan

                    @auth
                        @php
                            // Build base query for tickets visible to this user (no status filter)
                            $ticketsQuery = \App\Models\Thread::query();
                            
                            if (! auth()->user()->can('viewAll', \App\Models\Thread::class)) {
                                $ticketsQuery->where(function ($q) {
                                    $user = auth()->user();
                                    $q->whereHas('participants', fn ($sq) => $sq->where('user_id', $user->id));
                                    
                                    if ($user->can('viewDepartment', \App\Models\Thread::class) && $user->staff_department) {
                                        $q->orWhere('department', $user->staff_department);
                                    }
                                    
                                    if ($user->can('viewFlagged', \App\Models\Thread::class)) {
                                        $q->orWhere('is_flagged', true);
                                    }
                                });
                            }
                            
                            // Get counts for different statuses
                            $openTicketsCount = (clone $ticketsQuery)->where('status', \App\Enums\ThreadStatus::Open)->count();
                            $hasPendingTickets = (clone $ticketsQuery)->where('status', \App\Enums\ThreadStatus::Pending)->exists();
                        @endphp
                        
                        <flux:navlist.item 
                            icon="inbox" 
                            :href="route('tickets.index')" 
                            :current="request()->routeIs('tickets.*')" 
                            wire:navigate
                            :badge="$openTicketsCount > 0 ? $openTicketsCount : null"
                            :badge:color="$hasPendingTickets ? 'red' : 'zinc'"
                        >
                            Tickets
                        </flux:navlist.item>
                    @endauth

                    @can('view-ready-room')
                        <flux:navlist.item 
                            icon="building-storefront" 
                            :href="route('ready-room.index')" 
                            :current="request()->routeIs('ready-room.index')" 
                            wire:navigate
                        >
                            Staff Ready Room
                        </flux:navlist.item>
                    @endcan

                    @can('viewACP')
                        <flux:navlist.item icon="home" :href="route('acp.index')" :current="request()->routeIs('acp.index')" wire:navigate>Admin Control Panel</flux:navlist.item>
                    @endcan
                </flux:navlist.group>

                <flux:navlist.group heading="Get Involved" class="grid">
                    <flux:navlist.item icon="gift" :href="route('donate')" :current="request()->routeIs('donate')" wire:navigate badge="Donate" badge:color="amber">Support Lighthouse</flux:navlist.item>
                </flux:navlist.group>
            </flux:navlist>

            <flux:spacer />

            <flux:navlist variant="outline">
                <flux:navlist.item icon="book-open" href="https://library.lighthousemc.net" target="_blank">
                    Lighthouse Library
                </flux:navlist.item>
            </flux:navlist>

            <!-- Desktop User Menu -->
            @auth
                <flux:dropdown position="bottom" align="start">
                    <flux:profile
                        :name="auth()->user()->name"
                        :initials="auth()->user()->initials()"
                        icon-trailing="chevrons-up-down"
                    />

                    <flux:menu class="w-[220px]">
                        <flux:menu.radio.group>
                            <div class="p-0 text-sm font-normal">
                                <div class="flex items-center gap-2 px-1 py-1.5 text-left text-sm">
                                    <span class="relative flex h-8 w-8 shrink-0 overflow-hidden rounded-lg">
                                        <span
                                            class="flex h-full w-full items-center justify-center rounded-lg bg-neutral-200 text-black dark:bg-neutral-700 dark:text-white"
                                        >
                                            {{ auth()->user()->initials() }}
                                        </span>
                                    </span>

                                    <div class="grid flex-1 text-left text-sm leading-tight">
                                        <span class="truncate font-semibold"><flux:link href="{{ route('profile.show', auth()->user()) }}">{{ auth()->user()->name }}</flux:link></span>
                                        <span class="truncate text-xs">{{ auth()->user()->email }}</span>
                                    </div>
                                </div>
                            </div>
                        </flux:menu.radio.group>

                        <flux:menu.separator />

                        <flux:menu.radio.group>
                            <flux:menu.item href="{{  route('profile.show', auth()->user()) }}" icon="user" wire:navigate>Profile</flux:menu.item>
                            <flux:menu.item href="/settings/profile" icon="cog" wire:navigate>Settings</flux:menu.item>
                        </flux:menu.radio.group>

                        <flux:menu.separator />

                        <form method="POST" action="{{ route('logout') }}" class="w-full">
                            @csrf
                            <flux:menu.item as="button" type="submit" icon="arrow-right-start-on-rectangle" class="w-full">
                                {{ __('Log Out') }}
                            </flux:menu.item>
                        </form>
                    </flux:menu>
                </flux:dropdown>
            @endauth


            @guest
                <flux:button size="xs" icon="user-plus" wire:navigate href="{{ route('register') }}">
                    Register
                </flux:button>
                <flux:button size="xs" variant="primary" icon="arrow-right-start-on-rectangle" wire:navigate href="{{ route('login') }}">
                    Log In
                </flux:button>
            @endguest

        </flux:sidebar>

        <!-- Mobile User Menu -->
        <flux:header class="bg-zinc-900">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

            <x-discord-banner-modal />
            <flux:spacer />

            @auth
                <flux:dropdown position="top" align="end">
                    <flux:profile
                        :initials="auth()->user()->initials()"
                        icon-trailing="chevron-down"
                    />

                    <flux:menu>
                        <flux:menu.radio.group>
                            <div class="p-0 text-sm font-normal">
                                <div class="flex items-center gap-2 px-1 py-1.5 text-left text-sm">
                                    <span class="relative flex h-8 w-8 shrink-0 overflow-hidden rounded-lg">
                                        <span
                                            class="flex h-full w-full items-center justify-center rounded-lg bg-neutral-200 text-black dark:bg-neutral-700 dark:text-white"
                                        >
                                            {{ auth()->user()->initials() }}
                                        </span>
                                    </span>

                                    <div class="grid flex-1 text-left text-sm leading-tight">
                                        <span class="truncate font-semibold">{{ auth()->user()->name }}</span>
                                        <span class="truncate text-xs">{{ auth()->user()->email }}</span>
                                    </div>
                                </div>
                            </div>
                        </flux:menu.radio.group>

                        <flux:menu.separator />

                        <flux:menu.radio.group>
                            <flux:menu.item href="{{  route('profile.show', auth()->user()) }}" icon="user" wire:navigate>Profile</flux:menu.item>
                            <flux:menu.item href="/settings/profile" icon="cog" wire:navigate>Settings</flux:menu.item>
                        </flux:menu.radio.group>

                        <flux:menu.separator />

                        <form method="POST" action="{{ route('logout') }}" class="w-full">
                            @csrf
                            <flux:menu.item as="button" type="submit" icon="arrow-right-start-on-rectangle" class="w-full">
                                {{ __('Log Out') }}
                            </flux:menu.item>
                        </form>
                    </flux:menu>
                </flux:dropdown>
            @endauth
        </flux:header>

        {{ $slot }}

        @fluxScripts
        @persist('toast')
            <flux:toast position="top end" />
        @endpersist
    </body>
</html>
