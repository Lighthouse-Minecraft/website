<?php

use App\Actions\SubmitCommunityResponse;
use App\Enums\CommunityResponseStatus;
use App\Models\CommunityQuestion;
use App\Models\CommunityResponse;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new class extends Component {
    use WithFileUploads;

    public string $responseBody = '';
    public $responseImage = null;

    public function submitResponse(): void
    {
        $this->authorize('submit-community-response');

        $this->validate([
            'responseBody' => 'required|string|min:20|max:5000',
            'responseImage' => 'nullable|image|max:2048',
        ]);

        $activeQuestion = CommunityQuestion::active()->first();
        if (! $activeQuestion) {
            Flux::toast('No active question.', 'Error', variant: 'danger');
            return;
        }

        try {
            SubmitCommunityResponse::run($activeQuestion, Auth::user(), $this->responseBody, $this->responseImage);
            $this->reset('responseBody', 'responseImage');
            Flux::toast('Your story has been submitted for review!', 'Submitted', variant: 'success');
        } catch (\RuntimeException $e) {
            Flux::toast($e->getMessage(), 'Error', variant: 'danger');
        }
    }

    public function with(): array
    {
        $user = Auth::user();
        $activeQuestion = CommunityQuestion::active()->first();
        $hasResponded = $activeQuestion
            ? CommunityResponse::where('community_question_id', $activeQuestion->id)->where('user_id', $user->id)->exists()
            : false;

        $randomResponse = null;
        if ($hasResponded && $activeQuestion) {
            $randomResponse = $activeQuestion->approvedResponses()
                ->where('user_id', '!=', $user->id)
                ->with('user')
                ->inRandomOrder()
                ->first();
        }

        return [
            'activeQuestion' => $activeQuestion,
            'hasResponded' => $hasResponded,
            'randomResponse' => $randomResponse,
        ];
    }
}; ?>

<div>
    @can('view-community-stories')
        @if($activeQuestion)
            <flux:card>
                <flux:heading size="md">Community Question</flux:heading>
                <flux:separator variant="subtle" class="my-2" />

                <flux:text class="font-medium mt-3">{{ $activeQuestion->question_text }}</flux:text>

                @if(!$hasResponded)
                    <form wire:submit="submitResponse" class="mt-4 space-y-3">
                        <flux:field>
                            <flux:textarea wire:model="responseBody" rows="3" placeholder="Share your story..." />
                            <flux:error name="responseBody" />
                        </flux:field>

                        <flux:field>
                            <input type="file" wire:model="responseImage" accept="image/*" class="text-sm text-zinc-400" />
                            <flux:error name="responseImage" />
                        </flux:field>

                        <flux:button type="submit" variant="primary" size="sm">Submit</flux:button>
                    </form>
                @else
                    <div class="mt-3">
                        <flux:text variant="subtle" class="text-sm">You've shared your story!</flux:text>

                        @if($randomResponse)
                            <div class="mt-3 p-3 bg-zinc-800/50 rounded-lg">
                                <flux:text variant="subtle" class="text-xs mb-1">From the community:</flux:text>
                                <div class="flex items-start gap-2">
                                    <flux:avatar :src="$randomResponse->user->avatarUrl()" :name="$randomResponse->user->name" size="xs" />
                                    <div>
                                        <flux:text class="text-sm font-medium">{{ $randomResponse->user->name }}</flux:text>
                                        <flux:text class="text-sm mt-1">{{ Str::limit($randomResponse->body, 150) }}</flux:text>
                                    </div>
                                </div>
                            </div>
                        @endif

                        <div class="mt-3">
                            <flux:button href="{{ route('community-stories.index') }}" size="xs" variant="ghost">View all stories &rarr;</flux:button>
                        </div>
                    </div>
                @endif
            </flux:card>
        @endif
    @endcan
</div>
