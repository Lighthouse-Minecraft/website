<x-layouts.app>
    <div class="my-6">
        <livewire:users.display-basic-details :user="$user" />
    </div>

    <div class="my-6">
        <livewire:users.registration-answer-card :user="$user" />
    </div>

    @can('view-community-stories')
        <div class="my-6">
            <livewire:users.community-stories-card :user="$user" />
        </div>
    @endcan

    @can('view-ready-room')
        <div class="my-8">
            <flux:separator />
            <flux:heading size="lg" class="mt-4 mb-6">Staff</flux:heading>

            @if($user->staffPosition || $user->hasEverBeenOnStaff())
                @can('view-staff-activity', $user)
                    <div class="my-6">
                        <livewire:users.staff-activity-card :user="$user" />
                    </div>
                @endcan
            @endif

            @can('view-vault')
                <div class="my-6">
                    <livewire:users.vault-keys-card :user="$user" />
                </div>
            @endcan

            @can('viewActivityLog', $user)
                <div class="w-full my-6 flex justify-end">
                    <flux:modal.trigger name="activity-log-modal">
                        <flux:button icon="clock" size="sm" variant="ghost">View Activity Log</flux:button>
                    </flux:modal.trigger>
                </div>

                <flux:modal name="activity-log-modal" class="w-full max-w-7xl">
                    <livewire:users.display-activity-log :user="$user" lazy />
                </flux:modal>
            @endcan
        </div>
    @endcan
</x-layouts.app>
