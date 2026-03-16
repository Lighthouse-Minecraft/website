<?php

use App\Actions\SubmitApplication;
use App\Enums\ApplicationQuestionCategory;
use App\Enums\ApplicationQuestionType;
use App\Models\ApplicationQuestion;
use App\Models\StaffApplication;
use App\Models\StaffPosition;
use App\Enums\StaffRank;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;

new class extends Component {
    public StaffPosition $staffPosition;
    public array $answers = [];
    public array $questions = [];

    public function mount(StaffPosition $staffPosition): void
    {
        $this->authorize('create', StaffApplication::class);

        if (! $staffPosition->accepting_applications) {
            abort(403, 'This position is not currently accepting applications.');
        }

        $this->staffPosition = $staffPosition;
        $this->loadQuestions();
        $this->prepopulateAnswers();
    }

    private function loadQuestions(): void
    {
        $categories = [ApplicationQuestionCategory::Core];

        if ($this->staffPosition->rank === StaffRank::Officer) {
            $categories[] = ApplicationQuestionCategory::Officer;
        } else {
            $categories[] = ApplicationQuestionCategory::CrewMember;
        }

        $questions = ApplicationQuestion::active()
            ->where(function ($q) use ($categories) {
                $q->whereIn('category', $categories)
                  ->orWhere(function ($sub) {
                      $sub->where('category', ApplicationQuestionCategory::PositionSpecific)
                          ->where('staff_position_id', $this->staffPosition->id);
                  });
            })
            ->ordered()
            ->get();

        $this->questions = $questions->toArray();

        foreach ($questions as $question) {
            if (! isset($this->answers[$question->id])) {
                $this->answers[$question->id] = '';
            }
        }
    }

    private function prepopulateAnswers(): void
    {
        $user = Auth::user();
        $lastApp = $user->staffApplications()
            ->with('answers')
            ->latest()
            ->first();

        if (! $lastApp) {
            return;
        }

        foreach ($lastApp->answers as $answer) {
            if (isset($this->answers[$answer->application_question_id]) && $answer->answer) {
                $this->answers[$answer->application_question_id] = $answer->answer;
            }
        }
    }

    public function submit(): void
    {
        $this->authorize('create', StaffApplication::class);

        // Validate all questions have answers
        $rules = [];
        foreach ($this->questions as $question) {
            $rules["answers.{$question['id']}"] = 'required|string|min:1';
        }
        $this->validate($rules, [
            'answers.*.required' => 'This field is required.',
            'answers.*.min' => 'This field is required.',
        ]);

        try {
            SubmitApplication::run(Auth::user(), $this->staffPosition, $this->answers);
            Flux::toast('Application submitted successfully!', 'Submitted', variant: 'success');
            $this->redirect(route('applications.index'), navigate: true);
        } catch (\RuntimeException $e) {
            Flux::toast($e->getMessage(), 'Error', variant: 'danger');
        }
    }
}; ?>

<section>
    <div class="max-w-3xl px-4 py-8 mx-auto">
        <flux:heading size="2xl" class="mb-2">Apply for Position</flux:heading>

        <flux:card class="mb-6">
            <div class="space-y-2">
                <flux:heading size="lg">{{ $staffPosition->title }}</flux:heading>
                <div class="flex gap-2">
                    <flux:badge size="sm" color="{{ $staffPosition->rank->color() }}">{{ $staffPosition->rank->label() }}</flux:badge>
                    <flux:badge size="sm" color="zinc">{{ $staffPosition->department->label() }}</flux:badge>
                </div>
                @if($staffPosition->description)
                    <flux:text variant="subtle">{{ $staffPosition->description }}</flux:text>
                @endif
                @if($staffPosition->responsibilities)
                    <div>
                        <flux:text class="font-medium">Responsibilities:</flux:text>
                        <flux:text>{{ $staffPosition->responsibilities }}</flux:text>
                    </div>
                @endif
                @if($staffPosition->requirements)
                    <div>
                        <flux:text class="font-medium">Requirements:</flux:text>
                        <flux:text>{{ $staffPosition->requirements }}</flux:text>
                    </div>
                @endif
            </div>
        </flux:card>

        <form wire:submit="submit" class="space-y-6">
            @foreach($questions as $question)
                <flux:field wire:key="question-{{ $question['id'] }}">
                    <flux:label>{{ $question['question_text'] }} <span class="text-red-500">*</span></flux:label>

                    @if($question['type'] === 'short_text')
                        <flux:input wire:model="answers.{{ $question['id'] }}" />
                    @elseif($question['type'] === 'long_text')
                        <flux:textarea wire:model="answers.{{ $question['id'] }}" rows="4" />
                    @elseif($question['type'] === 'yes_no')
                        <div class="flex gap-4">
                            <label class="flex items-center gap-2">
                                <input type="radio" wire:model="answers.{{ $question['id'] }}" value="Yes" class="text-blue-600" />
                                <span>Yes</span>
                            </label>
                            <label class="flex items-center gap-2">
                                <input type="radio" wire:model="answers.{{ $question['id'] }}" value="No" class="text-blue-600" />
                                <span>No</span>
                            </label>
                        </div>
                    @elseif($question['type'] === 'select')
                        <flux:select wire:model="answers.{{ $question['id'] }}">
                            <flux:select.option value="">Select an option...</flux:select.option>
                            @foreach($question['select_options'] ?? [] as $option)
                                <flux:select.option value="{{ $option }}">{{ $option }}</flux:select.option>
                            @endforeach
                        </flux:select>
                    @endif

                    <flux:error name="answers.{{ $question['id'] }}" />
                </flux:field>
            @endforeach

            @if(count($questions) > 0)
                <div class="flex justify-end gap-3">
                    <flux:button href="{{ route('staff.index') }}" variant="ghost" wire:navigate>Cancel</flux:button>
                    <flux:button type="submit" variant="primary" icon="paper-airplane">Submit Application</flux:button>
                </div>
            @else
                <flux:text variant="subtle" class="py-8 text-center">No application questions have been configured for this position type yet. Please check back later.</flux:text>
            @endif
        </form>
    </div>
</section>
