<?php

use App\Models\User;
use Livewire\Volt\Component;

new class extends Component {
    public User $user;

    public function with(): array
    {
        $approvedResponses = $this->user->communityResponses()
            ->approved()
            ->with('question')
            ->orderByDesc('approved_at')
            ->get();

        return [
            'approvedResponses' => $approvedResponses,
        ];
    }
}; ?>

<div>
    @if($approvedResponses->count() > 0)
        <flux:card>
            <flux:heading size="md">Community Stories</flux:heading>
            <flux:separator variant="subtle" class="my-2" />

            <div class="space-y-4 mt-3">
                @foreach($approvedResponses as $response)
                    <div wire:key="profile-story-{{ $response->id }}" class="p-3 bg-zinc-50 dark:bg-zinc-800/50 rounded-lg">
                        <flux:text variant="subtle" class="text-xs italic mb-1">"{{ $response->question->question_text }}"</flux:text>
                        <div class="text-sm mt-1">
                            {!! nl2br(e($response->body)) !!}
                        </div>
                        @if($response->imageUrl())
                            <img src="{{ $response->imageUrl() }}" alt="Story image" class="rounded-lg max-h-48 mt-2" loading="lazy" />
                        @endif
                        <flux:text variant="subtle" class="text-xs mt-2">{{ $response->approved_at->format('M j, Y') }}</flux:text>
                    </div>
                @endforeach
            </div>
        </flux:card>
    @endif
</div>
