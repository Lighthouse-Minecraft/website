<?php

use App\Enums\StaffRank;
use App\Models\Meeting;
use App\Models\MeetingReport;
use App\Models\User;
use Flux\Flux;
use Livewire\Volt\Component;

new class extends Component {
    public Meeting $meeting;
    public string $department;
    public ?int $viewingUserId = null;

    public function mount(Meeting $meeting, string $department): void
    {
        $this->meeting = $meeting;
        $this->department = $department;
    }

    #[\Livewire\Attributes\Computed]
    public function staffMembers()
    {
        return User::where('staff_department', $this->department)
            ->where('staff_rank', '>=', StaffRank::JrCrew->value)
            ->orderBy('name')
            ->get();
    }

    #[\Livewire\Attributes\Computed]
    public function reports()
    {
        return MeetingReport::where('meeting_id', $this->meeting->id)
            ->whereNotNull('submitted_at')
            ->with(['user', 'answers.question'])
            ->get()
            ->keyBy('user_id');
    }

    #[\Livewire\Attributes\Computed]
    public function attendeeIds()
    {
        return $this->meeting->attendees()
            ->wherePivot('attended', true)
            ->pluck('users.id')
            ->toArray();
    }

    public function viewReport(int $userId): void
    {
        $this->viewingUserId = $userId;
        Flux::modal("view-staff-report-{$this->department}-{$this->meeting->id}")->show();
    }
}; ?>

<div>
    @if($this->staffMembers->isNotEmpty())
        <div class="flex flex-wrap gap-2">
            @foreach($this->staffMembers as $member)
                @php
                    $report = $this->reports->get($member->id);
                    $hasSubmitted = $report !== null;
                @endphp
                <button
                    wire:key="staff-{{ $member->id }}"
                    wire:click="viewReport({{ $member->id }})"
                    class="w-72 flex items-center gap-2 p-2 rounded-lg border border-zinc-200 dark:border-zinc-700 hover:bg-zinc-100 dark:hover:bg-zinc-700 hover:shadow-sm transition-all cursor-pointer text-left"
                >
                    <flux:avatar size="xs" :src="$member->avatarUrl()" />
                    <div class="min-w-0 flex-1">
                        <p class="text-xs font-medium truncate">{{ $member->name }}</p>
                    </div>
                    @if($hasSubmitted)
                        <flux:icon name="check-circle" variant="solid" class="w-4 h-4 text-green-500 shrink-0" />
                    @else
                        <flux:icon name="x-circle" variant="solid" class="w-4 h-4 text-red-400 shrink-0" />
                    @endif
                </button>
            @endforeach
        </div>
    @endif

    <flux:modal name="view-staff-report-{{ $department }}-{{ $meeting->id }}" class="min-w-[32rem] !text-left">
        @if($viewingUserId)
            @php
                $viewUser = $this->staffMembers->firstWhere('id', $viewingUserId);
                $viewReport = $this->reports->get($viewingUserId);
            @endphp

            @if($viewUser)
                @php $viewPresent = in_array($viewUser->id, $this->attendeeIds); @endphp
                <div class="space-y-4">
                    <div class="flex items-center gap-3">
                        <flux:avatar size="sm" :src="$viewUser->avatarUrl()" />
                        <div>
                            <flux:heading size="lg">{{ $viewUser->name }}</flux:heading>
                            @if($viewUser->staff_title)
                                <flux:text variant="subtle" class="text-sm">{{ $viewUser->staff_title }}</flux:text>
                            @endif
                        </div>
                        @if($viewPresent)
                            <flux:badge size="sm" color="green">Present</flux:badge>
                        @else
                            <flux:badge size="sm" color="red">Absent</flux:badge>
                        @endif
                    </div>

                    @if($viewReport)
                        @foreach($viewReport->answers->sortBy(fn ($a) => $a->question->sort_order) as $answer)
                            <div>
                                <flux:text class="font-semibold text-sm">{{ $answer->question->question_text }}</flux:text>
                                <flux:text class="mt-1">{{ $answer->answer ?: 'No response' }}</flux:text>
                            </div>
                        @endforeach
                    @else
                        <flux:callout color="zinc">
                            <flux:callout.text>This staff member has not submitted a check-in for this meeting.</flux:callout.text>
                        </flux:callout>
                    @endif

                    <div class="text-right">
                        <flux:modal.close>
                            <flux:button variant="ghost">Close</flux:button>
                        </flux:modal.close>
                    </div>
                </div>
            @endif
        @endif
    </flux:modal>
</div>
