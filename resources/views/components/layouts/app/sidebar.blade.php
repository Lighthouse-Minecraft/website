<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
        <!-- Lighthouse Layout -->
    </head>
    <body class="min-h-screen bg-gray-200 dark:bg-zinc-800">
        <flux:sidebar sticky stashable class="border-r border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:sidebar.toggle class="lg:hidden" icon="x-mark" />

            <a href="/pages/home" class="mr-5 flex items-center space-x-2" wire:navigate>
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

                    @can('viewAny', \App\Models\Meeting::class)
                        <flux:navlist.item icon="users" :href="route('meeting.index')" :current="request()->routeIs('meeting.index')" wire:navigate>Meeting Minutes</flux:navlist.item>
                    @endcan

                    @can('view-community-updates')
                        <flux:navlist.item icon="user-group" :href="route('community-updates.index')" :current="request()->routeIs('community-updates.index')" wire:navigate>Community Updates</flux:navlist.item>
                    @endcan

                    @can('view-ready-room')
                        <flux:navlist.item icon="building-storefront" :href="route('ready-room.index')" :current="request()->routeIs('ready-room.index')" wire:navigate>Staff Ready Room</flux:navlist.item>
                    @endcan

                    <flux:navlist.item icon="megaphone" :href="route('announcements.index')" :current="request()->routeIs('announcements.index')" wire:navigate>Announcement Index</flux:navlist.item>

                    <flux:navlist.item icon="book-open" :href="route('blogs.index')" :current="request()->routeIs('blogs.index')" wire:navigate>Blog Index</flux:navlist.item>

                    @can('viewAny', \App\Models\Comment::class)
                        <flux:navlist.item icon="chat-bubble-left-right" :href="route('comments.index')" :current="request()->routeIs('comments.index')" wire:navigate>Comment Index</flux:navlist.item>
                    @endcan

                    @can('viewAny', \App\Models\Meeting::class)
                        <flux:navlist.item icon="users" :href="route('meeting.index')" :current="request()->routeIs('meeting.index')" wire:navigate>Manage Meetings</flux:navlist.item>
                    @endcan

                    @can('viewACP')
                        <flux:navlist.item icon="home" :href="route('acp.index')" :current="request()->routeIs('acp.index')" wire:navigate>Admin Control Panel</flux:navlist.item>
                    @endcan
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
        <flux:header class="lg:hidden">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

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
            <flux:toast position="top right" />
        @endpersist
    </body>
</html>
