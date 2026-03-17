<?php

use App\Actions\AddApplicationNote;
use App\Actions\ApproveApplication;
use App\Actions\DenyApplication;
use App\Actions\UpdateApplicationStatus;
use App\Enums\ApplicationStatus;
use App\Enums\BackgroundCheckStatus;
use App\Models\StaffApplication;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;

new class extends Component {
    public StaffApplication $staffApplication;

    public string $transitionNotes = '';
    public string $approveNotes = '';
    public string $approveBgCheck = 'passed';
    public string $approveConditions = '';
    public string $denyNotes = '';
    public string $bgCheckNotes = '';
    public string $bgCheckStatus = 'pending';
    public string $newNote = '';

    public function mount(StaffApplication $staffApplication): void
    {
        $this->authorize('viewAny', StaffApplication::class);
        $this->staffApplication = $staffApplication->load([
            'staffPosition', 'user', 'answers.question', 'reviewer',
            'notes.user', 'staffReviewThread', 'interviewThread',
        ]);

        if ($staffApplication->background_check_status) {
            $this->approveBgCheck = $staffApplication->background_check_status->value;
        }
    }

    public function moveToUnderReview(): void
    {
        $this->authorize('update', $this->staffApplication);
        UpdateApplicationStatus::run(
            $this->staffApplication,
            ApplicationStatus::UnderReview,
            Auth::user(),
            $this->transitionNotes ?: null,
        );
        $this->transitionNotes = '';
        $this->refreshApplication();
        Flux::toast('Application moved to Under Review.', 'Updated', variant: 'success');
    }

    public function moveToInterview(): void
    {
        $this->authorize('update', $this->staffApplication);
        UpdateApplicationStatus::run(
            $this->staffApplication,
            ApplicationStatus::Interview,
            Auth::user(),
            $this->transitionNotes ?: null,
        );
        $this->transitionNotes = '';
        $this->refreshApplication();
        Flux::toast('Application moved to Interview. Discussion created.', 'Updated', variant: 'success');
    }

    public function moveToBackgroundCheck(): void
    {
        $this->authorize('update', $this->staffApplication);
        UpdateApplicationStatus::run(
            $this->staffApplication,
            ApplicationStatus::BackgroundCheck,
            Auth::user(),
            $this->bgCheckNotes ?: null,
            BackgroundCheckStatus::from($this->bgCheckStatus),
        );
        $this->bgCheckNotes = '';
        Flux::modal('bg-check-modal')->close();
        $this->refreshApplication();
        Flux::toast('Application moved to Background Check.', 'Updated', variant: 'success');
    }

    public function approve(): void
    {
        $this->authorize('update', $this->staffApplication);
        ApproveApplication::run(
            $this->staffApplication,
            Auth::user(),
            BackgroundCheckStatus::from($this->approveBgCheck),
            $this->approveConditions ?: null,
            $this->approveNotes ?: null,
        );
        $this->approveNotes = '';
        $this->approveConditions = '';
        Flux::modal('approve-modal')->close();
        $this->refreshApplication();
        Flux::toast('Application approved.', 'Approved', variant: 'success');
    }

    public function deny(): void
    {
        $this->authorize('update', $this->staffApplication);
        DenyApplication::run(
            $this->staffApplication,
            Auth::user(),
            $this->denyNotes ?: null,
        );
        $this->denyNotes = '';
        Flux::modal('deny-modal')->close();
        $this->refreshApplication();
        Flux::toast('Application denied.', 'Denied', variant: 'success');
    }

    public function addNote(): void
    {
        $this->authorize('update', $this->staffApplication);

        $this->validate(['newNote' => 'required|string|min:1']);

        AddApplicationNote::run($this->staffApplication, Auth::user(), $this->newNote);
        $this->newNote = '';
        $this->refreshApplication();
        Flux::toast('Note added.', 'Success', variant: 'success');
    }

    private function refreshApplication(): void
    {
        $this->staffApplication = $this->staffApplication->fresh([
            'staffPosition', 'user', 'answers.question', 'reviewer',
            'notes.user', 'staffReviewThread', 'interviewThread',
        ]);
    }
}; ?>

<section>
    <div class="max-w-4xl px-4 py-8 mx-auto">
        <div class="flex items-center justify-between mb-6">
            <flux:heading size="2xl">Review Application</flux:heading>
            <flux:button href="{{ route('admin.applications.index') }}" variant="ghost" icon="arrow-left" wire:navigate>Back</flux:button>
        </div>

        {{-- Applicant + Position Info --}}
        <div class="grid gap-4 mb-6 md:grid-cols-2">
            <flux:card>
                <flux:heading size="sm" class="mb-2">Applicant</flux:heading>
                <div class="space-y-1">
                    <flux:link href="{{ route('profile.show', $staffApplication->user) }}" wire:navigate class="text-lg font-medium">{{ $staffApplication->user->name }}</flux:link>
                    <flux:text variant="subtle">Member since {{ $staffApplication->user->created_at->format('M Y') }}</flux:text>
                </div>
            </flux:card>
            <flux:card>
                <flux:heading size="sm" class="mb-2">Position</flux:heading>
                <div class="space-y-1">
                    <div class="text-lg font-medium">{{ $staffApplication->staffPosition->title }}</div>
                    <div class="flex gap-2">
                        <flux:badge size="sm" color="{{ $staffApplication->staffPosition->rank->color() }}">{{ $staffApplication->staffPosition->rank->label() }}</flux:badge>
                        <flux:badge size="sm" color="zinc">{{ $staffApplication->staffPosition->department->label() }}</flux:badge>
                    </div>
                </div>
            </flux:card>
        </div>

        {{-- Applicant Background (snapshotted at submission) --}}
        <flux:card class="mb-6">
            <flux:heading size="sm" class="mb-3">Applicant Background <flux:text variant="subtle" class="text-xs">(at time of application)</flux:text></flux:heading>
            <div class="grid grid-cols-2 gap-x-6 gap-y-2 sm:grid-cols-3">
                @if($staffApplication->applicant_age)
                    <div>
                        <flux:text variant="subtle" class="text-xs">Age</flux:text>
                        <flux:text class="font-medium">{{ $staffApplication->applicant_age }}</flux:text>
                    </div>
                @endif
                @if($staffApplication->applicant_member_since)
                    <div>
                        <flux:text variant="subtle" class="text-xs">Member Since</flux:text>
                        <flux:text class="font-medium">{{ $staffApplication->applicant_member_since->format('M j, Y') }} ({{ $staffApplication->applicant_member_since->diffForHumans(null, true) }})</flux:text>
                    </div>
                @endif
                @if($staffApplication->applicant_membership_level)
                    <div>
                        <flux:text variant="subtle" class="text-xs">Membership Level</flux:text>
                        <flux:text class="font-medium">{{ $staffApplication->applicant_membership_level }}</flux:text>
                    </div>
                @endif
                @if($staffApplication->applicant_membership_level_since)
                    <div>
                        <flux:text variant="subtle" class="text-xs">At Current Level Since</flux:text>
                        <flux:text class="font-medium">{{ $staffApplication->applicant_membership_level_since->format('M j, Y') }} ({{ $staffApplication->applicant_membership_level_since->diffForHumans(null, true) }})</flux:text>
                    </div>
                @endif
                <div>
                    <flux:text variant="subtle" class="text-xs">Reports</flux:text>
                    <flux:text class="font-medium">{{ $staffApplication->applicant_report_count }}</flux:text>
                </div>
                <div>
                    <flux:text variant="subtle" class="text-xs">Commendations</flux:text>
                    <flux:text class="font-medium">{{ $staffApplication->applicant_commendation_count }}</flux:text>
                </div>
            </div>
        </flux:card>

        {{-- Status --}}
        <flux:card class="mb-6">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <flux:text class="font-medium">Status:</flux:text>
                    <flux:badge color="{{ $staffApplication->status->color() }}">{{ $staffApplication->status->label() }}</flux:badge>
                </div>
                <flux:text variant="subtle">Submitted {{ $staffApplication->created_at->format('M j, Y \a\t g:i A') }}</flux:text>
            </div>
            @if($staffApplication->background_check_status)
                <div class="flex items-center gap-2 mt-2">
                    <flux:text class="font-medium">Background Check:</flux:text>
                    <flux:badge size="sm" color="{{ $staffApplication->background_check_status->color() }}">{{ $staffApplication->background_check_status->label() }}</flux:badge>
                </div>
            @endif
            @if($staffApplication->conditions)
                <div class="mt-2">
                    <flux:text class="font-medium">Conditions:</flux:text>
                    <flux:text>{{ $staffApplication->conditions }}</flux:text>
                </div>
            @endif
        </flux:card>

        {{-- Discussion Links --}}
        @if($staffApplication->staff_review_thread_id || $staffApplication->interview_thread_id)
            <div class="flex gap-3 mb-6">
                @if($staffApplication->staff_review_thread_id)
                    <flux:button href="{{ route('discussions.show', $staffApplication->staff_review_thread_id) }}" variant="ghost" icon="chat-bubble-left-right" wire:navigate>
                        Staff Review Discussion
                    </flux:button>
                @endif
                @if($staffApplication->interview_thread_id)
                    <flux:button href="{{ route('discussions.show', $staffApplication->interview_thread_id) }}" variant="ghost" icon="chat-bubble-left-right" wire:navigate>
                        Interview Discussion
                    </flux:button>
                @endif
            </div>
        @endif

        {{-- Q&A --}}
        <flux:heading size="lg" class="mb-3">Application Responses</flux:heading>
        <div class="space-y-3 mb-6">
            @foreach($staffApplication->answers as $answer)
                <flux:card wire:key="ra-{{ $answer->id }}">
                    <flux:text class="font-medium">{{ $answer->question?->question_text ?? '(Question removed)' }}</flux:text>
                    <flux:text class="mt-1">{{ $answer->answer ?? '—' }}</flux:text>
                </flux:card>
            @endforeach
        </div>

        {{-- Staff Notes --}}
        <flux:heading size="lg" class="mb-3">Staff Notes</flux:heading>
        <div class="space-y-3 mb-4">
            @forelse($staffApplication->notes as $note)
                <flux:card wire:key="note-{{ $note->id }}">
                    <div class="flex items-center justify-between mb-1">
                        <flux:text class="font-medium">{{ $note->user->name }}</flux:text>
                        <flux:text variant="subtle" class="text-xs">{{ $note->created_at->format('M j, Y g:i A') }}</flux:text>
                    </div>
                    <flux:text>{!! nl2br(e($note->body)) !!}</flux:text>
                </flux:card>
            @empty
                <flux:text variant="subtle">No staff notes yet.</flux:text>
            @endforelse
        </div>

        <form wire:submit="addNote" class="flex gap-2 mb-6">
            <flux:textarea wire:model="newNote" rows="2" placeholder="Add a staff note..." class="flex-1" />
            <flux:button type="submit" variant="primary" size="sm" icon="plus">Add</flux:button>
        </form>
        @error('newNote') <flux:text class="text-red-500 text-sm">{{ $message }}</flux:text> @enderror

        {{-- Reviewer Notes History --}}
        @if($staffApplication->reviewer_notes)
            <flux:heading size="lg" class="mb-3">Status Change Notes</flux:heading>
            <flux:card class="mb-6">
                <pre class="text-sm whitespace-pre-wrap font-sans">{{ $staffApplication->reviewer_notes }}</pre>
            </flux:card>
        @endif

        {{-- Action Buttons --}}
        @if(! $staffApplication->isTerminal())
            <flux:card>
                <flux:heading size="md" class="mb-3">Actions</flux:heading>
                <div class="space-y-3">
                    @if($staffApplication->status === \App\Enums\ApplicationStatus::Submitted)
                        <div class="flex gap-2 items-end">
                            <flux:textarea wire:model="transitionNotes" rows="2" placeholder="Notes (optional)..." class="flex-1" />
                            <div class="flex gap-2">
                                <flux:button wire:click="moveToUnderReview" variant="primary" size="sm">Start Review</flux:button>
                                <flux:button x-on:click="$flux.modal('deny-modal').show()" variant="danger" size="sm">Deny</flux:button>
                            </div>
                        </div>
                    @elseif($staffApplication->status === \App\Enums\ApplicationStatus::UnderReview)
                        <div class="flex gap-2 items-end">
                            <flux:textarea wire:model="transitionNotes" rows="2" placeholder="Notes (optional)..." class="flex-1" />
                            <div class="flex gap-2">
                                <flux:button wire:click="moveToInterview" variant="primary" size="sm">Schedule Interview</flux:button>
                                <flux:button x-on:click="$flux.modal('deny-modal').show()" variant="danger" size="sm">Deny</flux:button>
                            </div>
                        </div>
                    @elseif($staffApplication->status === \App\Enums\ApplicationStatus::Interview)
                        <div class="flex gap-2">
                            <flux:button x-on:click="$flux.modal('bg-check-modal').show()" variant="primary" size="sm">Move to Background Check</flux:button>
                            <flux:button x-on:click="$flux.modal('deny-modal').show()" variant="danger" size="sm">Deny</flux:button>
                        </div>
                    @elseif($staffApplication->status === \App\Enums\ApplicationStatus::BackgroundCheck)
                        <div class="flex gap-2">
                            <flux:button x-on:click="$flux.modal('approve-modal').show()" variant="primary" size="sm">Approve</flux:button>
                            <flux:button x-on:click="$flux.modal('deny-modal').show()" variant="danger" size="sm">Deny</flux:button>
                        </div>
                    @endif
                </div>
            </flux:card>
        @endif

        {{-- Background Check Modal --}}
        <flux:modal name="bg-check-modal" class="w-full lg:w-1/3">
            <flux:heading size="lg" class="mb-4">Move to Background Check</flux:heading>
            <form wire:submit="moveToBackgroundCheck" class="space-y-4">
                <flux:field>
                    <flux:label>Background Check Status</flux:label>
                    <flux:select wire:model="bgCheckStatus">
                        @foreach(BackgroundCheckStatus::cases() as $s)
                            <flux:select.option value="{{ $s->value }}">{{ $s->label() }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </flux:field>
                <flux:field>
                    <flux:label>Notes</flux:label>
                    <flux:textarea wire:model="bgCheckNotes" rows="3" />
                </flux:field>
                <div class="flex justify-end gap-2">
                    <flux:button variant="ghost" x-on:click="$flux.modal('bg-check-modal').close()">Cancel</flux:button>
                    <flux:button type="submit" variant="primary">Confirm</flux:button>
                </div>
            </form>
        </flux:modal>

        {{-- Approve Modal --}}
        <flux:modal name="approve-modal" class="w-full lg:w-1/3">
            <flux:heading size="lg" class="mb-4">Approve Application</flux:heading>
            <form wire:submit="approve" class="space-y-4">
                <flux:field>
                    <flux:label>Background Check Status</flux:label>
                    <flux:select wire:model="approveBgCheck">
                        @foreach(BackgroundCheckStatus::cases() as $s)
                            <flux:select.option value="{{ $s->value }}">{{ $s->label() }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </flux:field>
                <flux:field>
                    <flux:label>Conditions</flux:label>
                    <flux:description>e.g. "30-day trial period"</flux:description>
                    <flux:textarea wire:model="approveConditions" rows="3" />
                </flux:field>
                <flux:field>
                    <flux:label>Notes</flux:label>
                    <flux:textarea wire:model="approveNotes" rows="3" />
                </flux:field>
                <div class="flex justify-end gap-2">
                    <flux:button variant="ghost" x-on:click="$flux.modal('approve-modal').close()">Cancel</flux:button>
                    <flux:button type="submit" variant="primary">Approve</flux:button>
                </div>
            </form>
        </flux:modal>

        {{-- Deny Modal --}}
        <flux:modal name="deny-modal" class="w-full lg:w-1/3">
            <flux:heading size="lg" class="mb-4">Deny Application</flux:heading>
            <form wire:submit="deny" class="space-y-4">
                <flux:field>
                    <flux:label>Reason / Notes</flux:label>
                    <flux:textarea wire:model="denyNotes" rows="3" />
                </flux:field>
                <div class="flex justify-end gap-2">
                    <flux:button variant="ghost" x-on:click="$flux.modal('deny-modal').close()">Cancel</flux:button>
                    <flux:button type="submit" variant="danger">Deny Application</flux:button>
                </div>
            </form>
        </flux:modal>
    </div>
</section>
