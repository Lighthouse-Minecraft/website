<x-layouts.app>
    <div class="flex items-center justify-center min-h-[60vh]">
        <flux:card class="max-w-md text-center p-8">
            <flux:heading size="lg">Too Many Requests</flux:heading>
            <flux:text class="mt-2">You've made too many requests. Please wait a moment and try again.</flux:text>
            <div class="mt-6">
                <flux:button href="{{ route('dashboard') }}" variant="primary" wire:navigate>
                    Return to Dashboard
                </flux:button>
            </div>
        </flux:card>
    </div>
</x-layouts.app>
