<?php

use App\Models\Meeting;
use App\Models\MeetingQuestion;
use Flux\Flux;
use Livewire\Volt\Component;

new class extends Component {
    public Meeting $meeting;
    public string $newQuestion = '';

    public function mount(Meeting $meeting): void
    {
        $this->meeting = $meeting;
    }

    #[\Livewire\Attributes\Computed]
    public function questions()
    {
        return $this->meeting->questions()->orderBy('sort_order')->get();
    }

    public function addQuestion(): void
    {
        $this->authorize('update', $this->meeting);

        $this->validate(['newQuestion' => 'required|string|min:5']);

        $maxOrder = $this->meeting->questions()->max('sort_order') ?? -1;

        $this->meeting->questions()->create([
            'question_text' => $this->newQuestion,
            'sort_order' => $maxOrder + 1,
        ]);

        $this->newQuestion = '';
        unset($this->questions);
        Flux::toast('Question added.', variant: 'success');
    }

    public function removeQuestion(int $questionId): void
    {
        $this->authorize('update', $this->meeting);

        MeetingQuestion::where('id', $questionId)
            ->where('meeting_id', $this->meeting->id)
            ->delete();

        unset($this->questions);
        Flux::toast('Question removed.', variant: 'success');
    }

    public function moveUp(int $questionId): void
    {
        $this->authorize('update', $this->meeting);
        $this->reorder($questionId, -1);
    }

    public function moveDown(int $questionId): void
    {
        $this->authorize('update', $this->meeting);
        $this->reorder($questionId, 1);
    }

    private function reorder(int $questionId, int $direction): void
    {
        $questions = $this->meeting->questions()->orderBy('sort_order')->get();
        $index = $questions->search(fn ($q) => $q->id === $questionId);

        if ($index === false) return;

        $swapIndex = $index + $direction;
        if ($swapIndex < 0 || $swapIndex >= $questions->count()) return;

        $currentOrder = $questions[$index]->sort_order;
        $swapOrder = $questions[$swapIndex]->sort_order;

        $questions[$index]->update(['sort_order' => $swapOrder]);
        $questions[$swapIndex]->update(['sort_order' => $currentOrder]);

        unset($this->questions);
    }
}; ?>

<div class="mt-6">
    <flux:card>
        <flux:heading class="mb-4">Pre-Meeting Report Questions</flux:heading>
        <flux:text variant="subtle" class="mb-4">
            Staff will answer these questions before the meeting. You can add, remove, or reorder questions.
        </flux:text>

        @if($this->questions->isNotEmpty())
            <div class="space-y-2 mb-4">
                @foreach($this->questions as $question)
                    <div class="flex items-center gap-2 p-2 rounded border border-zinc-200 dark:border-zinc-700">
                        <div class="flex flex-col gap-0.5">
                            <flux:button wire:click="moveUp({{ $question->id }})" variant="ghost" size="xs" icon="chevron-up" class="!p-0.5" />
                            <flux:button wire:click="moveDown({{ $question->id }})" variant="ghost" size="xs" icon="chevron-down" class="!p-0.5" />
                        </div>
                        <flux:text class="flex-1 text-sm">{{ $question->question_text }}</flux:text>
                        @can('update', $meeting)
                            <flux:button wire:click="removeQuestion({{ $question->id }})" variant="ghost" size="xs" icon="trash" class="text-red-500 hover:text-red-700" />
                        @endcan
                    </div>
                @endforeach
            </div>
        @else
            <flux:text variant="subtle" class="mb-4">No questions configured yet.</flux:text>
        @endif

        @can('update', $meeting)
            <div class="flex gap-2">
                <flux:input wire:model="newQuestion" placeholder="Add a new question..." class="flex-1" wire:keydown.enter="addQuestion" />
                <flux:button wire:click="addQuestion" variant="primary" size="sm">Add</flux:button>
            </div>
        @endcan
    </flux:card>
</div>
