<x-layouts.app>
    <div class="my-6">
        <livewire:users.display-basic-details :user="$user" />
    </div>

    <div class="w-full my-6 flex justify-end">
        <flux:modal.trigger name="activity-log-modal">
            <flux:button icon="clock" size="sm" variant="ghost">View Activity Log</flux:button>
        </flux:modal.trigger>
    </div>

    <flux:modal name="activity-log-modal" class="w-full xl:w-1/2">
        <livewire:users.display-activity-log :user="$user" />
    </flux:modal>
</x-layouts.app>
