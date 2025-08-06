<x-layouts.app>
    <div class="my-6">
        <livewire:users.display-basic-details :user="$user" />
    </div>

    <div class="w-full my-6">
        <flux:card>
            <livewire:users.display-activity-log :user="$user" />
        </flux:card>
    </div>
</x-layouts.app>
