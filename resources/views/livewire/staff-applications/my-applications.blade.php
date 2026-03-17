<?php

use App\Models\StaffApplication;
use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;

new class extends Component {
    public function mount(): void
    {
        abort_unless(Auth::check(), 403);
    }

    public function getApplicationsProperty()
    {
        return Auth::user()->staffApplications()
            ->with('staffPosition')
            ->latest()
            ->get();
    }
}; ?>

<section>
    <div class="max-w-4xl px-4 py-8 mx-auto">
        <flux:heading size="2xl" class="mb-6">My Applications</flux:heading>

        @if($this->applications->isEmpty())
            <flux:card>
                <flux:text variant="subtle" class="py-8 text-center">You haven't submitted any applications yet.</flux:text>
            </flux:card>
        @else
            <div class="space-y-3">
                @foreach($this->applications as $app)
                    <flux:card wire:key="app-{{ $app->id }}">
                        <a href="{{ route('applications.show', $app) }}" wire:navigate class="flex items-center justify-between">
                            <div>
                                <div class="font-medium">{{ $app->staffPosition->title }}</div>
                                <div class="flex gap-2 mt-1">
                                    <flux:badge size="sm" color="zinc">{{ $app->staffPosition->department->label() }}</flux:badge>
                                    <flux:badge size="sm" color="{{ $app->status->color() }}">{{ $app->status->label() }}</flux:badge>
                                </div>
                            </div>
                            <div class="text-sm text-zinc-500">
                                {{ $app->created_at->format('M j, Y') }}
                            </div>
                        </a>
                    </flux:card>
                @endforeach
            </div>
        @endif
    </div>
</section>
