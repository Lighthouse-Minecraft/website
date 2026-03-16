<?php

use App\Actions\SubmitCommunityResponse;
use App\Enums\CommunityResponseStatus;
use App\Enums\MembershipLevel;
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
    public ?int $respondingToQuestionId = null;

    public function removeResponseImage(): void
    {
        $this->responseImage = null;
    }

    public function submitResponse(): void
    {
        $this->authorize('submit-community-response');

        $this->validate([
            'responseBody' => 'required|string|min:20|max:5000',
            'responseImage' => 'nullable|image|max:2048',
        ]);

        $question = CommunityQuestion::find($this->respondingToQuestionId);
        if (! $question) {
            Flux::toast('Question not found.', 'Error', variant: 'danger');
            return;
        }

        try {
            SubmitCommunityResponse::run($question, Auth::user(), $this->responseBody, $this->responseImage);
            $this->reset('responseBody', 'responseImage', 'respondingToQuestionId');
            Flux::toast('Your story has been submitted for review!', 'Submitted', variant: 'success');
        } catch (\RuntimeException $e) {
            Flux::toast($e->getMessage(), 'Error', variant: 'danger');
        }
    }

    public function with(): array
    {
        $user = Auth::user();
        $activeQuestion = CommunityQuestion::active()->first();

        $hasRespondedToActive = $activeQuestion
            ? CommunityResponse::where('community_question_id', $activeQuestion->id)->where('user_id', $user->id)->exists()
            : false;

        $randomResponse = null;
        $bonusQuestion = null;
        $hasUsedBonusThisCycle = false;
        $canSuggest = false;

        if ($hasRespondedToActive && $activeQuestion) {
            $randomResponse = $activeQuestion->approvedResponses()
                ->where('user_id', '!=', $user->id)
                ->with('user')
                ->inRandomOrder()
                ->first();

            // Check if user is Resident+ and eligible for a bonus archived question
            if ($user->isAtLeastLevel(MembershipLevel::Resident)) {
                $archivedQuery = CommunityResponse::where('user_id', $user->id)
                    ->where('community_question_id', '!=', $activeQuestion->id)
                    ->whereHas('question', fn ($questionQuery) => $questionQuery->archived());
                if ($activeQuestion->start_date) {
                    $archivedQuery->where('created_at', '>=', $activeQuestion->start_date);
                }
                $archivedResponseCount = $archivedQuery->count();

                $hasUsedBonusThisCycle = $archivedResponseCount >= 1;

                if (! $hasUsedBonusThisCycle) {
                    // Re-use previously selected bonus question if still valid
                    if ($this->respondingToQuestionId && $this->respondingToQuestionId !== $activeQuestion->id) {
                        $bonusQuestion = CommunityQuestion::archived()->find($this->respondingToQuestionId);
                    }

                    if (! $bonusQuestion) {
                        $answeredQuestionIds = CommunityResponse::where('user_id', $user->id)->pluck('community_question_id');

                        $bonusQuestion = CommunityQuestion::archived()
                            ->whereNotIn('id', $answeredQuestionIds)
                            ->inRandomOrder()
                            ->first();
                    }
                }

                // Citizens can suggest questions after using their bonus (or if no bonus available)
                if ($user->isAtLeastLevel(MembershipLevel::Citizen) && ($hasUsedBonusThisCycle || ! $bonusQuestion)) {
                    $canSuggest = true;
                }
            }
        }

        // Default to active question for the form
        if (! $this->respondingToQuestionId) {
            if (! $hasRespondedToActive && $activeQuestion) {
                $this->respondingToQuestionId = $activeQuestion->id;
            } elseif ($bonusQuestion) {
                $this->respondingToQuestionId = $bonusQuestion->id;
            }
        }

        return [
            'activeQuestion' => $activeQuestion,
            'hasRespondedToActive' => $hasRespondedToActive,
            'randomResponse' => $randomResponse,
            'bonusQuestion' => $bonusQuestion,
            'hasUsedBonusThisCycle' => $hasUsedBonusThisCycle,
            'canSuggest' => $canSuggest,
        ];
    }
}; ?>

<div>
    @can('view-community-stories')
        @if($activeQuestion)
            <flux:card>
                <flux:heading size="md">Community Question</flux:heading>
                <flux:separator variant="subtle" class="my-2" />

                @if(!$hasRespondedToActive)
                    {{-- Active question response form --}}
                    <flux:text class="font-medium mt-3">{{ $activeQuestion->question_text }}</flux:text>

                    <form wire:submit="submitResponse" class="mt-4 space-y-3">
                        <flux:field>
                            <flux:label class="sr-only">Your response</flux:label>
                            <flux:textarea wire:model="responseBody" rows="3" placeholder="Share your story..." />
                            <flux:error name="responseBody" />
                        </flux:field>

                        <flux:file-upload wire:model="responseImage">
                            <flux:file-upload.dropzone
                                heading="Drop an image or click to browse"
                                text="JPG, PNG, GIF up to 2MB"
                            />
                        </flux:file-upload>
                        @if($responseImage)
                            <flux:file-item
                                :heading="$responseImage->getClientOriginalName()"
                                :image="$responseImage->temporaryUrl()"
                                :size="$responseImage->getSize()"
                            >
                                <x-slot name="actions">
                                    <flux:file-item.remove wire:click="removeResponseImage" />
                                </x-slot>
                            </flux:file-item>
                        @endif

                        <flux:button type="submit" variant="primary" size="sm">Submit</flux:button>
                    </form>
                @elseif($bonusQuestion)
                    {{-- Bonus archived question --}}
                    <flux:text variant="subtle" class="text-sm mt-3">You've shared your story! Here's a past question you can answer too:</flux:text>

                    <flux:text class="font-medium mt-2">{{ $bonusQuestion->question_text }}</flux:text>

                    <form wire:submit="submitResponse" class="mt-4 space-y-3">
                        <flux:field>
                            <flux:label class="sr-only">Your response</flux:label>
                            <flux:textarea wire:model="responseBody" rows="3" placeholder="Share your story..." />
                            <flux:error name="responseBody" />
                        </flux:field>

                        <flux:file-upload wire:model="responseImage">
                            <flux:file-upload.dropzone
                                heading="Drop an image or click to browse"
                                text="JPG, PNG, GIF up to 2MB"
                            />
                        </flux:file-upload>
                        @if($responseImage)
                            <flux:file-item
                                :heading="$responseImage->getClientOriginalName()"
                                :image="$responseImage->temporaryUrl()"
                                :size="$responseImage->getSize()"
                            >
                                <x-slot name="actions">
                                    <flux:file-item.remove wire:click="removeResponseImage" />
                                </x-slot>
                            </flux:file-item>
                        @endif

                        <flux:button type="submit" variant="primary" size="sm">Submit</flux:button>
                    </form>
                @else
                    {{-- Already answered everything --}}
                    <flux:text class="font-medium mt-3">{{ $activeQuestion->question_text }}</flux:text>

                    <div class="mt-3">
                        <flux:text variant="subtle" class="text-sm">You've shared your story!</flux:text>

                        @if($randomResponse)
                            <div class="mt-3 p-3 bg-zinc-50 dark:bg-zinc-800/50 rounded-lg">
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

                        @if($canSuggest)
                            <div class="mt-3 p-3 bg-zinc-50 dark:bg-zinc-800/50 rounded-lg">
                                <flux:text variant="subtle" class="text-sm">Have a question idea for the community?</flux:text>
                                <flux:button href="{{ route('community-stories.index') }}" size="xs" variant="ghost" class="mt-1">Suggest a question &rarr;</flux:button>
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
