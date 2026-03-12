<?php

use App\Actions\CreateCommunityQuestion;
use App\Actions\EditCommunityResponse;
use App\Actions\ModerateResponses;
use App\Actions\ReviewQuestionSuggestion;
use App\Actions\SubmitCommunityResponse;
use App\Actions\SubmitQuestionSuggestion;
use App\Actions\ToggleCommunityReaction;
use App\Actions\UpdateCommunityQuestion;
use App\Enums\CommunityQuestionStatus;
use App\Enums\CommunityResponseStatus;
use App\Enums\MembershipLevel;
use App\Enums\QuestionSuggestionStatus;
use App\Models\CommunityQuestion;
use App\Models\CommunityResponse;
use App\Models\QuestionSuggestion;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

new class extends Component {
    use WithFileUploads, WithPagination;

    // Tab state
    public string $activeTab = 'stories';
    public string $moderationTab = 'responses';

    // Response submission
    public string $responseBody = '';
    public $responseImage = null;

    // Editing own response
    public ?int $editingResponseId = null;
    public string $editBody = '';
    public $editImage = null;
    public bool $editRemoveImage = false;

    // Past questions
    public ?int $selectedQuestionId = null;
    public string $archivedResponseBody = '';
    public $archivedResponseImage = null;

    // Question suggestion (Citizens)
    public string $suggestionText = '';

    // Staff: moderation
    public array $selectedResponseIds = [];
    public ?int $viewingResponseId = null;
    public ?CommunityResponse $viewingResponse = null;
    public string $staffEditBody = '';

    // Staff: question management
    public ?int $editingQuestionId = null;
    public string $questionText = '';
    public string $questionDescription = '';
    public string $questionStatus = 'draft';
    public ?string $questionStartDate = null;
    public ?string $questionEndDate = null;

    public function mount(): void
    {
        $this->activeTab = 'stories';
    }

    // --- Public: Submit Response ---

    public function submitResponse(): void
    {
        $this->authorize('submit-community-response');

        $this->validate([
            'responseBody' => 'required|string|min:20|max:5000',
            'responseImage' => 'nullable|image|max:2048',
        ]);

        $activeQuestion = CommunityQuestion::active()->first();
        if (! $activeQuestion) {
            Flux::toast('No active question to respond to.', 'Error', variant: 'danger');
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

    // --- Public: Submit Archived Response ---

    public function submitArchivedResponse(): void
    {
        $this->authorize('submit-community-response');

        $this->validate([
            'archivedResponseBody' => 'required|string|min:20|max:5000',
            'archivedResponseImage' => 'nullable|image|max:2048',
            'selectedQuestionId' => 'required|exists:community_questions,id',
        ]);

        $question = CommunityQuestion::findOrFail($this->selectedQuestionId);

        try {
            SubmitCommunityResponse::run($question, Auth::user(), $this->archivedResponseBody, $this->archivedResponseImage);
            $this->reset('archivedResponseBody', 'archivedResponseImage');
            Flux::toast('Your story has been submitted for review!', 'Submitted', variant: 'success');
        } catch (\RuntimeException $e) {
            Flux::toast($e->getMessage(), 'Error', variant: 'danger');
        }
    }

    // --- Public: Edit Own Response ---

    public function startEditing(int $responseId): void
    {
        $response = CommunityResponse::findOrFail($responseId);
        $this->authorize('update', $response);

        $this->editingResponseId = $responseId;
        $this->editBody = $response->body;
        $this->editRemoveImage = false;
        Flux::modal('edit-response-modal')->show();
    }

    public function saveEdit(): void
    {
        $response = CommunityResponse::findOrFail($this->editingResponseId);
        $this->authorize('update', $response);

        $this->validate([
            'editBody' => 'required|string|min:20|max:5000',
            'editImage' => 'nullable|image|max:2048',
        ]);

        try {
            EditCommunityResponse::run($response, Auth::user(), $this->editBody, $this->editImage, $this->editRemoveImage);
            $this->reset('editingResponseId', 'editBody', 'editImage', 'editRemoveImage');
            Flux::modal('edit-response-modal')->close();
            Flux::toast('Response updated.', 'Updated', variant: 'success');
        } catch (\RuntimeException $e) {
            Flux::toast($e->getMessage(), 'Error', variant: 'danger');
        }
    }

    public function deleteResponse(int $responseId): void
    {
        $response = CommunityResponse::findOrFail($responseId);
        $this->authorize('delete', $response);

        if ($response->image_path) {
            \Illuminate\Support\Facades\Storage::disk(config('filesystems.public_disk'))->delete($response->image_path);
        }
        $response->delete();

        Flux::toast('Response deleted.', 'Deleted', variant: 'success');
    }

    // --- Public: Reactions ---

    public function toggleReaction(int $responseId, string $emoji): void
    {
        $this->authorize('view-community-stories');

        $response = CommunityResponse::findOrFail($responseId);
        ToggleCommunityReaction::run($response, Auth::user(), $emoji);
    }

    // --- Public: Question Suggestion ---

    public function suggestQuestion(): void
    {
        $this->authorize('suggest-community-question');

        $this->validate([
            'suggestionText' => 'required|string|min:10|max:500',
        ]);

        SubmitQuestionSuggestion::run(Auth::user(), $this->suggestionText);
        $this->reset('suggestionText');
        Flux::toast('Your question suggestion has been submitted!', 'Suggested', variant: 'success');
    }

    // --- Staff: Moderate Responses ---

    public function approveSelected(): void
    {
        $this->authorize('manage-community-stories');

        $responses = CommunityResponse::whereIn('id', $this->selectedResponseIds)->get();
        $count = ModerateResponses::run($responses, Auth::user(), CommunityResponseStatus::Approved);

        $this->selectedResponseIds = [];
        Flux::toast("{$count} response(s) approved.", 'Approved', variant: 'success');
    }

    public function rejectSelected(): void
    {
        $this->authorize('manage-community-stories');

        $responses = CommunityResponse::whereIn('id', $this->selectedResponseIds)->get();
        $count = ModerateResponses::run($responses, Auth::user(), CommunityResponseStatus::Rejected);

        $this->selectedResponseIds = [];
        Flux::toast("{$count} response(s) rejected.", 'Done', variant: 'success');
    }

    public function openResponseModal(int $id): void
    {
        $this->authorize('manage-community-stories');

        $this->viewingResponse = CommunityResponse::with(['user', 'question'])->findOrFail($id);
        $this->viewingResponseId = $id;
        $this->staffEditBody = $this->viewingResponse->body;
        Flux::modal('staff-response-modal')->show();
    }

    public function staffEditResponse(): void
    {
        $this->authorize('manage-community-stories');

        $response = CommunityResponse::findOrFail($this->viewingResponseId);

        $this->validate([
            'staffEditBody' => 'required|string|min:20|max:5000',
        ]);

        try {
            EditCommunityResponse::run($response, Auth::user(), $this->staffEditBody);
            Flux::modal('staff-response-modal')->close();
            $this->viewingResponse = null;
            Flux::toast('Response edited.', 'Updated', variant: 'success');
        } catch (\RuntimeException $e) {
            Flux::toast($e->getMessage(), 'Error', variant: 'danger');
        }
    }

    public function approveViewing(): void
    {
        $this->authorize('manage-community-stories');

        $response = CommunityResponse::findOrFail($this->viewingResponseId);
        ModerateResponses::run(collect([$response]), Auth::user(), CommunityResponseStatus::Approved);

        Flux::modal('staff-response-modal')->close();
        $this->viewingResponseId = null;
        $this->viewingResponse = null;
        Flux::toast('Response approved.', 'Approved', variant: 'success');
    }

    public function rejectViewing(): void
    {
        $this->authorize('manage-community-stories');

        $response = CommunityResponse::findOrFail($this->viewingResponseId);
        ModerateResponses::run(collect([$response]), Auth::user(), CommunityResponseStatus::Rejected);

        Flux::modal('staff-response-modal')->close();
        $this->viewingResponseId = null;
        $this->viewingResponse = null;
        Flux::toast('Response rejected.', 'Done', variant: 'success');
    }

    // --- Staff: Question Management ---

    public function openQuestionModal(?int $id = null): void
    {
        $this->authorize('manage-community-stories');

        if ($id) {
            $question = CommunityQuestion::findOrFail($id);
            $this->editingQuestionId = $id;
            $this->questionText = $question->question_text;
            $this->questionDescription = $question->description ?? '';
            $this->questionStatus = $question->status->value;
            $this->questionStartDate = $question->start_date?->format('Y-m-d\TH:i');
            $this->questionEndDate = $question->end_date?->format('Y-m-d\TH:i');
        } else {
            $this->reset('editingQuestionId', 'questionText', 'questionDescription', 'questionStatus', 'questionStartDate', 'questionEndDate');
        }

        Flux::modal('question-modal')->show();
    }

    public function saveQuestion(): void
    {
        $this->authorize('manage-community-stories');

        $this->validate([
            'questionText' => 'required|string|min:10|max:1000',
            'questionDescription' => 'nullable|string|max:2000',
            'questionStatus' => 'required|in:draft,scheduled,active,archived',
            'questionStartDate' => 'nullable|date',
            'questionEndDate' => 'nullable|date|after_or_equal:questionStartDate',
        ]);

        $status = CommunityQuestionStatus::from($this->questionStatus);
        $startDate = $this->questionStartDate ? \Carbon\Carbon::parse($this->questionStartDate) : null;
        $endDate = $this->questionEndDate ? \Carbon\Carbon::parse($this->questionEndDate) : null;

        if ($this->editingQuestionId) {
            $question = CommunityQuestion::findOrFail($this->editingQuestionId);
            UpdateCommunityQuestion::run($question, Auth::user(), [
                'question_text' => $this->questionText,
                'description' => $this->questionDescription ?: null,
                'status' => $status,
                'start_date' => $startDate,
                'end_date' => $endDate,
            ]);
            Flux::toast('Question updated.', 'Updated', variant: 'success');
        } else {
            CreateCommunityQuestion::run(
                Auth::user(),
                $this->questionText,
                $this->questionDescription ?: null,
                $status,
                $startDate,
                $endDate,
            );
            Flux::toast('Question created.', 'Created', variant: 'success');
        }

        Flux::modal('question-modal')->close();
        $this->reset('editingQuestionId', 'questionText', 'questionDescription', 'questionStatus', 'questionStartDate', 'questionEndDate');
    }

    public function deleteQuestion(int $id): void
    {
        $question = CommunityQuestion::findOrFail($id);
        $this->authorize('delete', $question);

        $question->delete();
        Flux::toast('Question deleted.', 'Deleted', variant: 'success');
    }

    // --- Staff: Suggestion Review ---

    public function approveSuggestion(int $id): void
    {
        $this->authorize('manage-community-stories');

        $suggestion = QuestionSuggestion::findOrFail($id);
        ReviewQuestionSuggestion::run($suggestion, Auth::user(), QuestionSuggestionStatus::Approved);
        Flux::toast('Suggestion approved and draft question created.', 'Approved', variant: 'success');
    }

    public function rejectSuggestion(int $id): void
    {
        $this->authorize('manage-community-stories');

        $suggestion = QuestionSuggestion::findOrFail($id);
        ReviewQuestionSuggestion::run($suggestion, Auth::user(), QuestionSuggestionStatus::Rejected);
        Flux::toast('Suggestion rejected.', 'Done', variant: 'success');
    }

    // --- Computed Data ---

    public function with(): array
    {
        $user = Auth::user();
        $activeQuestion = CommunityQuestion::active()->first();
        $userResponse = $activeQuestion
            ? CommunityResponse::where('community_question_id', $activeQuestion->id)->where('user_id', $user->id)->first()
            : null;
        $hasResponded = $userResponse !== null;

        // Check if user can respond to an archived question
        $canRespondToArchived = false;
        $hasRespondedToArchived = false;
        if ($activeQuestion && $hasResponded && $user->isAtLeastLevel(MembershipLevel::Resident)) {
            $hasRespondedToArchived = CommunityResponse::where('user_id', $user->id)
                ->where('community_question_id', '!=', $activeQuestion->id)
                ->whereHas('question', fn ($q) => $q->archived())
                ->where('created_at', '>=', $activeQuestion->start_date)
                ->exists();
            $canRespondToArchived = ! $hasRespondedToArchived;
        }

        $approvedResponses = $activeQuestion
            ? $activeQuestion->approvedResponses()
                ->with(['user', 'reactions'])
                ->orderByDesc('approved_at')
                ->paginate(15)
            : collect();

        $archivedQuestions = CommunityQuestion::archived()
            ->withCount(['responses as approved_responses_count' => fn ($q) => $q->approved()])
            ->orderByDesc('end_date')
            ->get();

        // Pre-load which archived questions the user has already responded to
        $userArchivedResponseQuestionIds = $archivedQuestions->isNotEmpty()
            ? CommunityResponse::where('user_id', $user->id)
                ->whereIn('community_question_id', $archivedQuestions->pluck('id'))
                ->pluck('community_question_id')
                ->toArray()
            : [];

        // If viewing a specific archived question
        $selectedQuestionResponses = null;
        $selectedQuestion = null;
        if ($this->selectedQuestionId) {
            $selectedQuestion = CommunityQuestion::find($this->selectedQuestionId);
            $selectedQuestionResponses = $selectedQuestion
                ? $selectedQuestion->approvedResponses()->with(['user', 'reactions'])->orderByDesc('approved_at')->get()
                : collect();
        }

        // Staff data
        $pendingResponses = null;
        $allQuestions = null;
        $pendingSuggestions = null;
        if ($user->can('manage-community-stories')) {
            $pendingResponses = CommunityResponse::pendingReview()
                ->with(['user', 'question'])
                ->orderBy('created_at')
                ->get();

            $allQuestions = CommunityQuestion::withCount('responses')
                ->orderByDesc('created_at')
                ->get();

            $pendingSuggestions = QuestionSuggestion::where('status', QuestionSuggestionStatus::Suggested)
                ->with('user')
                ->orderBy('created_at')
                ->get();
        }

        return [
            'activeQuestion' => $activeQuestion,
            'hasResponded' => $hasResponded,
            'userResponse' => $userResponse,
            'canRespondToArchived' => $canRespondToArchived,
            'hasRespondedToArchived' => $hasRespondedToArchived,
            'approvedResponses' => $approvedResponses,
            'archivedQuestions' => $archivedQuestions,
            'selectedQuestion' => $selectedQuestion,
            'selectedQuestionResponses' => $selectedQuestionResponses,
            'userArchivedResponseQuestionIds' => $userArchivedResponseQuestionIds,
            'pendingResponses' => $pendingResponses,
            'allQuestions' => $allQuestions,
            'pendingSuggestions' => $pendingSuggestions,
            'allowedEmojis' => \App\Actions\ToggleCommunityReaction::ALLOWED_EMOJIS,
        ];
    }
}; ?>

<div>
    <div class="max-w-4xl mx-auto">
        <div class="mb-8">
            <flux:heading size="lg">Community Stories</flux:heading>
            <flux:text variant="subtle">Stories from the Lighthouse community</flux:text>
        </div>

        {{-- Tab Navigation --}}
        <div class="flex gap-1 mb-6 border-b border-zinc-700">
            <button wire:click="$set('activeTab', 'stories')"
                class="px-4 py-2 text-sm font-medium rounded-t-lg {{ $activeTab === 'stories' ? 'bg-zinc-800 text-white border-b-2 border-accent' : 'text-zinc-400 hover:text-white' }}">
                Current Stories
            </button>
            <button wire:click="$set('activeTab', 'past-questions')"
                class="px-4 py-2 text-sm font-medium rounded-t-lg {{ $activeTab === 'past-questions' ? 'bg-zinc-800 text-white border-b-2 border-accent' : 'text-zinc-400 hover:text-white' }}">
                Past Questions
            </button>
            @can('manage-community-stories')
                <button wire:click="$set('activeTab', 'manage')"
                    class="px-4 py-2 text-sm font-medium rounded-t-lg {{ $activeTab === 'manage' ? 'bg-zinc-800 text-white border-b-2 border-accent' : 'text-zinc-400 hover:text-white' }}">
                    Manage
                </button>
            @endcan
        </div>

        {{-- ==================== STORIES TAB ==================== --}}
        @if($activeTab === 'stories')
            @if($activeQuestion)
                <flux:card class="mb-6">
                    <flux:heading size="md">{{ $activeQuestion->question_text }}</flux:heading>
                    @if($activeQuestion->description)
                        <flux:text variant="subtle" class="mt-2">{{ $activeQuestion->description }}</flux:text>
                    @endif
                </flux:card>

                {{-- Response Form --}}
                @if(!$hasResponded)
                    <flux:card class="mb-6">
                        <flux:heading size="sm">Share Your Story</flux:heading>
                        <form wire:submit="submitResponse" class="mt-4 space-y-4">
                            <flux:field>
                                <flux:textarea wire:model="responseBody" rows="5" placeholder="Share your experience, memory, or thoughts..." />
                                <flux:error name="responseBody" />
                            </flux:field>

                            <flux:field>
                                <flux:label>Image (optional)</flux:label>
                                <input type="file" wire:model="responseImage" accept="image/*" class="text-sm text-zinc-400" />
                                <flux:error name="responseImage" />
                            </flux:field>

                            <flux:button type="submit" variant="primary">Submit Response</flux:button>
                        </form>
                    </flux:card>
                @else
                    <flux:card class="mb-6">
                        @if($userResponse->status === \App\Enums\CommunityResponseStatus::Approved)
                            <flux:text variant="subtle">You've shared your story for this question! Your response has been approved and is visible below.</flux:text>
                        @elseif($userResponse->status === \App\Enums\CommunityResponseStatus::Rejected)
                            <flux:text variant="subtle">Your response to this question was not approved.</flux:text>
                        @else
                            <flux:text variant="subtle">You've shared your story for this question! Your response is being reviewed.</flux:text>
                        @endif
                    </flux:card>
                @endif

                {{-- Approved Stories Feed --}}
                @if($approvedResponses instanceof \Illuminate\Pagination\LengthAwarePaginator && $approvedResponses->count() > 0)
                    <div class="space-y-4">
                        @foreach($approvedResponses as $response)
                            <flux:card wire:key="story-{{ $response->id }}">
                                <div class="flex items-start gap-3">
                                    <flux:avatar :src="$response->user->avatarUrl()" :name="$response->user->name" size="sm" />
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center gap-2">
                                            <flux:text class="font-medium">{{ $response->user->name }}</flux:text>
                                            <flux:text variant="subtle" class="text-xs">{{ $response->approved_at->diffForHumans() }}</flux:text>
                                        </div>
                                        <div class="mt-2 prose prose-sm prose-invert max-w-none">
                                            {!! nl2br(e($response->body)) !!}
                                        </div>
                                        @if($response->imageUrl())
                                            <img src="{{ $response->imageUrl() }}" alt="Story image" class="rounded-lg max-h-64 mt-3" loading="lazy" />
                                        @endif

                                        {{-- Emoji Reactions --}}
                                        <div class="flex flex-wrap gap-2 mt-3">
                                            @foreach($allowedEmojis as $emoji)
                                                @php
                                                    $count = $response->reactions->where('emoji', $emoji)->count();
                                                    $hasReacted = $response->reactions->where('emoji', $emoji)->where('user_id', auth()->id())->count() > 0;
                                                @endphp
                                                <button wire:click="toggleReaction({{ $response->id }}, '{{ $emoji }}')"
                                                    class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-sm {{ $hasReacted ? 'bg-accent/20 border border-accent' : 'bg-zinc-800 border border-zinc-700 hover:border-zinc-500' }}">
                                                    <span>{{ $emoji }}</span>
                                                    @if($count > 0)
                                                        <span class="text-xs text-zinc-400">{{ $count }}</span>
                                                    @endif
                                                </button>
                                            @endforeach
                                        </div>
                                    </div>
                                </div>
                            </flux:card>
                        @endforeach

                        <div class="mt-4">
                            {{ $approvedResponses->links() }}
                        </div>
                    </div>
                @else
                    <flux:text variant="subtle" class="text-center py-8">No stories yet. Be the first to share!</flux:text>
                @endif
            @else
                <flux:card>
                    <flux:text variant="subtle" class="text-center py-8">No active community question right now. Check back soon!</flux:text>
                </flux:card>
            @endif

            {{-- Question Suggestion (Citizens) --}}
            @can('suggest-community-question')
                <flux:card class="mt-6">
                    <flux:heading size="sm">Suggest a Question</flux:heading>
                    <flux:text variant="subtle" class="mb-3">As a Citizen, you can suggest questions for the community.</flux:text>
                    <form wire:submit="suggestQuestion" class="flex gap-3">
                        <div class="flex-1">
                            <flux:input wire:model="suggestionText" placeholder="What question would you ask the community?" />
                            <flux:error name="suggestionText" />
                        </div>
                        <flux:button type="submit" variant="primary" size="sm">Suggest</flux:button>
                    </form>
                </flux:card>
            @endcan

        {{-- ==================== PAST QUESTIONS TAB ==================== --}}
        @elseif($activeTab === 'past-questions')
            @if($archivedQuestions->count() > 0)
                <div class="space-y-4">
                    @foreach($archivedQuestions as $question)
                        <flux:card wire:key="archived-q-{{ $question->id }}">
                            <div class="flex items-center justify-between">
                                <div class="flex-1">
                                    <flux:heading size="sm">{{ $question->question_text }}</flux:heading>
                                    <flux:text variant="subtle" class="text-xs mt-1">
                                        {{ $question->approved_responses_count }} {{ Str::plural('story', $question->approved_responses_count) }}
                                        @if($question->end_date)
                                            &middot; Ended {{ $question->end_date->diffForHumans() }}
                                        @endif
                                    </flux:text>
                                </div>
                                <flux:button wire:click="$set('selectedQuestionId', {{ $question->id }})" size="sm" variant="ghost">
                                    {{ $selectedQuestionId === $question->id ? 'Hide' : 'View Stories' }}
                                </flux:button>
                            </div>

                            {{-- Expanded: show responses for this question --}}
                            @if($selectedQuestionId === $question->id && $selectedQuestionResponses)
                                <div class="mt-4 space-y-3 border-t border-zinc-700 pt-4">
                                    @forelse($selectedQuestionResponses as $response)
                                        <div wire:key="archived-r-{{ $response->id }}" class="flex items-start gap-3 p-3 bg-zinc-800/50 rounded-lg">
                                            <flux:avatar :src="$response->user->avatarUrl()" :name="$response->user->name" size="xs" />
                                            <div class="flex-1 min-w-0">
                                                <div class="flex items-center gap-2">
                                                    <flux:text class="font-medium text-sm">{{ $response->user->name }}</flux:text>
                                                </div>
                                                <div class="mt-1 text-sm">
                                                    {!! nl2br(e($response->body)) !!}
                                                </div>
                                                @if($response->imageUrl())
                                                    <img src="{{ $response->imageUrl() }}" alt="Story image" class="rounded-lg max-h-48 mt-2" loading="lazy" />
                                                @endif

                                                {{-- Reactions --}}
                                                <div class="flex flex-wrap gap-1 mt-2">
                                                    @foreach($allowedEmojis as $emoji)
                                                        @php
                                                            $count = $response->reactions->where('emoji', $emoji)->count();
                                                            $hasReacted = $response->reactions->where('emoji', $emoji)->where('user_id', auth()->id())->count() > 0;
                                                        @endphp
                                                        @if($count > 0 || $hasReacted)
                                                            <button wire:click="toggleReaction({{ $response->id }}, '{{ $emoji }}')"
                                                                class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded-full text-xs {{ $hasReacted ? 'bg-accent/20 border border-accent' : 'bg-zinc-700 border border-zinc-600' }}">
                                                                <span>{{ $emoji }}</span>
                                                                <span class="text-zinc-400">{{ $count }}</span>
                                                            </button>
                                                        @endif
                                                    @endforeach
                                                </div>
                                            </div>
                                        </div>
                                    @empty
                                        <flux:text variant="subtle" class="text-center py-4">No approved stories for this question yet.</flux:text>
                                    @endforelse

                                    {{-- Respond to archived question --}}
                                    @if($canRespondToArchived && !in_array($question->id, $userArchivedResponseQuestionIds))
                                        <div class="border-t border-zinc-700 pt-4">
                                            <flux:heading size="sm">Respond to This Question</flux:heading>
                                            <form wire:submit="submitArchivedResponse" class="mt-3 space-y-3">
                                                <flux:field>
                                                    <flux:textarea wire:model="archivedResponseBody" rows="4" placeholder="Share your story..." />
                                                    <flux:error name="archivedResponseBody" />
                                                </flux:field>
                                                <flux:field>
                                                    <flux:label>Image (optional)</flux:label>
                                                    <input type="file" wire:model="archivedResponseImage" accept="image/*" class="text-sm text-zinc-400" />
                                                    <flux:error name="archivedResponseImage" />
                                                </flux:field>
                                                <flux:button type="submit" variant="primary" size="sm">Submit Response</flux:button>
                                            </form>
                                        </div>
                                    @endif
                                </div>
                            @endif
                        </flux:card>
                    @endforeach
                </div>
            @else
                <flux:text variant="subtle" class="text-center py-8">No past questions yet.</flux:text>
            @endif

        {{-- ==================== MANAGE TAB (Staff) ==================== --}}
        @elseif($activeTab === 'manage')
            @can('manage-community-stories')
                {{-- Sub-tab navigation --}}
                <div class="flex gap-1 mb-4">
                    <flux:button wire:click="$set('moderationTab', 'responses')" size="sm"
                        variant="{{ $moderationTab === 'responses' ? 'primary' : 'ghost' }}">
                        Pending Responses
                        @if($pendingResponses && $pendingResponses->count() > 0)
                            <flux:badge variant="danger" size="sm" class="ml-1">{{ $pendingResponses->count() }}</flux:badge>
                        @endif
                    </flux:button>
                    <flux:button wire:click="$set('moderationTab', 'questions')" size="sm"
                        variant="{{ $moderationTab === 'questions' ? 'primary' : 'ghost' }}">
                        Questions
                    </flux:button>
                    <flux:button wire:click="$set('moderationTab', 'suggestions')" size="sm"
                        variant="{{ $moderationTab === 'suggestions' ? 'primary' : 'ghost' }}">
                        Suggestions
                        @if($pendingSuggestions && $pendingSuggestions->count() > 0)
                            <flux:badge variant="sky" size="sm" class="ml-1">{{ $pendingSuggestions->count() }}</flux:badge>
                        @endif
                    </flux:button>
                </div>

                {{-- PENDING RESPONSES --}}
                @if($moderationTab === 'responses')
                    @if($pendingResponses && $pendingResponses->count() > 0)
                        <div class="flex gap-2 mb-3">
                            <flux:button wire:click="approveSelected" size="sm" variant="primary"
                                :disabled="empty($selectedResponseIds)">
                                Approve Selected
                            </flux:button>
                            <flux:button wire:click="rejectSelected" size="sm" variant="danger"
                                :disabled="empty($selectedResponseIds)">
                                Reject Selected
                            </flux:button>
                        </div>

                        <flux:table>
                            <flux:table.columns>
                                <flux:table.column class="w-8">
                                    <input type="checkbox"
                                        @change="
                                            if ($event.target.checked) {
                                                $wire.set('selectedResponseIds', {{ $pendingResponses->pluck('id')->toJson() }})
                                            } else {
                                                $wire.set('selectedResponseIds', [])
                                            }
                                        "
                                        class="rounded border-zinc-600" />
                                </flux:table.column>
                                <flux:table.column>User</flux:table.column>
                                <flux:table.column>Question</flux:table.column>
                                <flux:table.column>Preview</flux:table.column>
                                <flux:table.column>Submitted</flux:table.column>
                                <flux:table.column>Actions</flux:table.column>
                            </flux:table.columns>
                            <flux:table.rows>
                                @foreach($pendingResponses as $response)
                                    <flux:table.row wire:key="mod-{{ $response->id }}">
                                        <flux:table.cell>
                                            <input type="checkbox" value="{{ $response->id }}" wire:model.live="selectedResponseIds" class="rounded border-zinc-600" />
                                        </flux:table.cell>
                                        <flux:table.cell>{{ $response->user->name }}</flux:table.cell>
                                        <flux:table.cell class="max-w-[150px] truncate">{{ Str::limit($response->question->question_text, 30) }}</flux:table.cell>
                                        <flux:table.cell class="max-w-[200px] truncate">{{ Str::limit($response->body, 60) }}</flux:table.cell>
                                        <flux:table.cell>{{ $response->created_at->diffForHumans() }}</flux:table.cell>
                                        <flux:table.cell>
                                            <flux:button wire:click="openResponseModal({{ $response->id }})" size="xs" variant="ghost" icon="eye">View</flux:button>
                                        </flux:table.cell>
                                    </flux:table.row>
                                @endforeach
                            </flux:table.rows>
                        </flux:table>
                    @else
                        <flux:text variant="subtle" class="text-center py-8">No pending responses to review.</flux:text>
                    @endif

                {{-- QUESTIONS --}}
                @elseif($moderationTab === 'questions')
                    <div class="mb-3">
                        <flux:button wire:click="openQuestionModal" size="sm" variant="primary" icon="plus">New Question</flux:button>
                    </div>

                    @if($allQuestions && $allQuestions->count() > 0)
                        <flux:table>
                            <flux:table.columns>
                                <flux:table.column>Question</flux:table.column>
                                <flux:table.column>Status</flux:table.column>
                                <flux:table.column>Start</flux:table.column>
                                <flux:table.column>End</flux:table.column>
                                <flux:table.column>Responses</flux:table.column>
                                <flux:table.column>Actions</flux:table.column>
                            </flux:table.columns>
                            <flux:table.rows>
                                @foreach($allQuestions as $question)
                                    <flux:table.row wire:key="q-{{ $question->id }}">
                                        <flux:table.cell class="max-w-[250px] truncate">{{ Str::limit($question->question_text, 50) }}</flux:table.cell>
                                        <flux:table.cell>
                                            <flux:badge color="{{ $question->status->color() }}" size="sm">{{ $question->status->label() }}</flux:badge>
                                        </flux:table.cell>
                                        <flux:table.cell>{{ $question->start_date?->format('M j, Y') ?? '—' }}</flux:table.cell>
                                        <flux:table.cell>{{ $question->end_date?->format('M j, Y') ?? '—' }}</flux:table.cell>
                                        <flux:table.cell>{{ $question->responses_count }}</flux:table.cell>
                                        <flux:table.cell>
                                            <div class="flex gap-1">
                                                <flux:button wire:click="openQuestionModal({{ $question->id }})" size="xs" variant="ghost" icon="pencil">Edit</flux:button>
                                                @if($question->approvedResponses()->doesntExist())
                                                    <flux:button wire:click="deleteQuestion({{ $question->id }})" wire:confirm="Delete this question?" size="xs" variant="ghost" icon="trash" class="text-red-400">Delete</flux:button>
                                                @endif
                                            </div>
                                        </flux:table.cell>
                                    </flux:table.row>
                                @endforeach
                            </flux:table.rows>
                        </flux:table>
                    @else
                        <flux:text variant="subtle" class="text-center py-8">No questions yet. Create the first one!</flux:text>
                    @endif

                {{-- SUGGESTIONS --}}
                @elseif($moderationTab === 'suggestions')
                    @if($pendingSuggestions && $pendingSuggestions->count() > 0)
                        <flux:table>
                            <flux:table.columns>
                                <flux:table.column>Suggested By</flux:table.column>
                                <flux:table.column>Question</flux:table.column>
                                <flux:table.column>Date</flux:table.column>
                                <flux:table.column>Actions</flux:table.column>
                            </flux:table.columns>
                            <flux:table.rows>
                                @foreach($pendingSuggestions as $suggestion)
                                    <flux:table.row wire:key="sug-{{ $suggestion->id }}">
                                        <flux:table.cell>{{ $suggestion->user->name }}</flux:table.cell>
                                        <flux:table.cell>{{ $suggestion->question_text }}</flux:table.cell>
                                        <flux:table.cell>{{ $suggestion->created_at->diffForHumans() }}</flux:table.cell>
                                        <flux:table.cell>
                                            <div class="flex gap-1">
                                                <flux:button wire:click="approveSuggestion({{ $suggestion->id }})" size="xs" variant="primary">Approve</flux:button>
                                                <flux:button wire:click="rejectSuggestion({{ $suggestion->id }})" size="xs" variant="ghost" class="text-red-400">Reject</flux:button>
                                            </div>
                                        </flux:table.cell>
                                    </flux:table.row>
                                @endforeach
                            </flux:table.rows>
                        </flux:table>
                    @else
                        <flux:text variant="subtle" class="text-center py-8">No pending suggestions.</flux:text>
                    @endif
                @endif
            @endcan
        @endif
    </div>

    {{-- ==================== MODALS ==================== --}}

    {{-- Staff: Response Detail Modal --}}
    <flux:modal name="staff-response-modal" class="w-full lg:w-1/2">
        @if($viewingResponse)
                <flux:heading size="md">Review Response</flux:heading>
                <flux:text variant="subtle" class="mb-4">By {{ $viewingResponse->user->name }} &middot; {{ $viewingResponse->created_at->diffForHumans() }}</flux:text>
                <flux:text variant="subtle" class="mb-2">Question: {{ $viewingResponse->question->question_text }}</flux:text>

                <flux:field class="mb-4">
                    <flux:label>Response Body</flux:label>
                    <flux:textarea wire:model="staffEditBody" rows="6" />
                    <flux:error name="staffEditBody" />
                </flux:field>

                @if($viewingResponse->imageUrl())
                    <div class="mb-4">
                        <flux:label>Attached Image</flux:label>
                        <img src="{{ $viewingResponse->imageUrl() }}" alt="Response image" class="rounded-lg max-h-48 mt-1" />
                    </div>
                @endif

                <div class="flex gap-2 mt-4">
                    <flux:button wire:click="staffEditResponse" size="sm" variant="ghost">Save Edit</flux:button>
                    <flux:spacer />
                    <flux:button wire:click="rejectViewing" size="sm" variant="danger">Reject</flux:button>
                    <flux:button wire:click="approveViewing" size="sm" variant="primary">Approve</flux:button>
                </div>
        @endif
    </flux:modal>

    {{-- Staff: Question Create/Edit Modal --}}
    <flux:modal name="question-modal" class="w-full lg:w-1/2">
        <flux:heading size="md">{{ $editingQuestionId ? 'Edit Question' : 'New Question' }}</flux:heading>

        <form wire:submit="saveQuestion" class="mt-4 space-y-4">
            <flux:field>
                <flux:label>Question Text <span class="text-red-500">*</span></flux:label>
                <flux:textarea wire:model="questionText" rows="3" />
                <flux:error name="questionText" />
            </flux:field>

            <flux:field>
                <flux:label>Description</flux:label>
                <flux:description>Optional context or guidance for respondents</flux:description>
                <flux:textarea wire:model="questionDescription" rows="2" />
                <flux:error name="questionDescription" />
            </flux:field>

            <flux:field>
                <flux:label>Status</flux:label>
                <flux:select wire:model="questionStatus">
                    <flux:select.option value="draft">Draft</flux:select.option>
                    <flux:select.option value="scheduled">Scheduled</flux:select.option>
                    <flux:select.option value="active">Active</flux:select.option>
                    <flux:select.option value="archived">Archived</flux:select.option>
                </flux:select>
                <flux:error name="questionStatus" />
            </flux:field>

            <div class="grid grid-cols-2 gap-4">
                <flux:field>
                    <flux:label>Start Date</flux:label>
                    <flux:input type="datetime-local" wire:model="questionStartDate" />
                    <flux:error name="questionStartDate" />
                </flux:field>
                <flux:field>
                    <flux:label>End Date</flux:label>
                    <flux:input type="datetime-local" wire:model="questionEndDate" />
                    <flux:error name="questionEndDate" />
                </flux:field>
            </div>

            <div class="flex gap-2 justify-end">
                <flux:button variant="ghost" x-on:click="$flux.modal('question-modal').close()">Cancel</flux:button>
                <flux:button type="submit" variant="primary">{{ $editingQuestionId ? 'Update' : 'Create' }}</flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- User: Edit Own Response Modal --}}
    <flux:modal name="edit-response-modal" class="w-full lg:w-1/2">
        <flux:heading size="md">Edit Your Response</flux:heading>
        <form wire:submit="saveEdit" class="mt-4 space-y-4">
            <flux:field>
                <flux:textarea wire:model="editBody" rows="5" />
                <flux:error name="editBody" />
            </flux:field>

            <flux:field>
                <flux:label>Replace Image</flux:label>
                <input type="file" wire:model="editImage" accept="image/*" class="text-sm text-zinc-400" />
                <flux:error name="editImage" />
            </flux:field>

            <label class="flex items-center gap-2">
                <input type="checkbox" wire:model="editRemoveImage" class="rounded border-zinc-600" />
                <flux:text class="text-sm">Remove existing image</flux:text>
            </label>

            <div class="flex gap-2 justify-end">
                <flux:button variant="ghost" x-on:click="$flux.modal('edit-response-modal').close()">Cancel</flux:button>
                <flux:button type="submit" variant="primary">Save Changes</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
