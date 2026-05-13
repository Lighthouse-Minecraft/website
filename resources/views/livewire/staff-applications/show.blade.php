<?php

use App\Actions\WithdrawApplication;
use App\Models\StaffApplication;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;

new class extends Component {
    public StaffApplication $staffApplication;

    public function mount(StaffApplication $staffApplication): void
    {
        $this->authorize('view', $staffApplication);
        $this->staffApplication = $staffApplication->load(['staffPosition', 'answers.question', 'reviewer']);
    }

    public function withdraw(): void
    {
        try {
            WithdrawApplication::run($this->staffApplication, Auth::user());
            Flux::toast('Application withdrawn.', 'Withdrawn', variant: 'success');
            $this->redirect(route('applications.index'), navigate: true);
        } catch (\RuntimeException $e) {
            Flux::toast($e->getMessage(), 'Error', variant: 'danger');
        }
    }
}; ?>

<section>
    <div class="max-w-3xl px-4 py-8 mx-auto">
        <div class="flex items-center justify-between mb-6">
            <flux:heading size="2xl">Application Details</flux:heading>
            <flux:button href="{{ route('applications.index') }}" variant="ghost" icon="arrow-left" wire:navigate>Back</flux:button>
        </div>

        <flux:card class="mb-6">
            <div class="space-y-2">
                <div class="flex items-center justify-between">
                    <flux:heading size="lg">{{ $staffApplication->staffPosition->title }}</flux:heading>
                    <flux:badge size="sm" color="{{ $staffApplication->status->color() }}">{{ $staffApplication->status->label() }}</flux:badge>
                </div>
                <div class="flex gap-2">
                    <flux:badge size="sm" color="{{ $staffApplication->staffPosition->rank->color() }}">{{ $staffApplication->staffPosition->rank->label() }}</flux:badge>
                    <flux:badge size="sm" color="zinc">{{ $staffApplication->staffPosition->department->label() }}</flux:badge>
                </div>
                <flux:text variant="subtle">Submitted {{ $staffApplication->created_at->format('M j, Y \a\t g:i A') }}</flux:text>
            </div>
        </flux:card>

        {{-- Answers --}}
        <div class="space-y-4 mb-6">
            @foreach($staffApplication->answers as $answer)
                <flux:card wire:key="answer-{{ $answer->id }}">
                    <div class="space-y-1">
                        <flux:text class="font-medium">{{ $answer->question?->question_text ?? '(Question removed)' }}</flux:text>
                        <flux:text>{{ $answer->answer ?? '—' }}</flux:text>
                    </div>
                </flux:card>
            @endforeach
        </div>

        {{-- Approval details --}}
        @if($staffApplication->status === \App\Enums\ApplicationStatus::Approved && $staffApplication->conditions)
            <flux:card class="mb-6">
                <flux:heading size="md" class="mb-2">Approval Details</flux:heading>
                <div>
                    <flux:text class="font-medium">Conditions:</flux:text>
                    <flux:text>{{ $staffApplication->conditions }}</flux:text>
                </div>
            </flux:card>
        @endif

        {{-- Interview discussion link --}}
        @if($staffApplication->interview_thread_id)
            <flux:card class="mb-6">
                <flux:button href="{{ route('discussions.show', $staffApplication->interview_thread_id) }}" variant="ghost" icon="chat-bubble-left-right" wire:navigate>
                    View Interview Discussion
                </flux:button>
            </flux:card>
        @endif

        {{-- Withdraw --}}
        @if(! $staffApplication->isTerminal())
            <div class="flex justify-end">
                <flux:button variant="danger" icon="x-mark" wire:click="withdraw" wire:confirm="Are you sure you want to withdraw this application? This cannot be undone.">
                    Withdraw Application
                </flux:button>
            </div>
        @endif
    </div>
</section>
