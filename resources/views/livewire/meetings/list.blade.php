<?php

use Livewire\Volt\Component;
use App\Models\Meeting;
use App\Enums\MeetingStatus;

new class extends Component {
    public $meetings;

    public function mount()
    {
        if (Gate::allows('viewAnyPrivate', Meeting::class)) {
            $this->meetings = Meeting::orderBy('created_at', 'desc')->get();
        } elseif (Gate::allows('viewAnyPublic', Meeting::class)) {
            $this->meetings = Meeting::where('is_public', true)->orderBy('created_at', 'desc')->get();
        } else {
            $this->meetings = [];
        }
    }
}; ?>

<div class="space-y-6">
    <flux:heading size="xl">Manage Meetings</flux:heading>

    <flux:table>
        <flux:table.columns>
                <flux:table.column>Meeting</flux:table.column>
                <flux:table.column>Meeting Status</flux:table.column>
                <flux:table.column>Summary</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($meetings as $meeting)
                @can('view', $meeting)
                    <flux:table.row>
                        <flux:table.cell><flux:link href="{{ route('meeting.edit', $meeting) }}">{{ $meeting->title }} - {{ $meeting->day }}</flux:link></flux:table.cell>
                        <flux:table.cell>
                            @if ($meeting->status == MeetingStatus::Pending)
                                {{ $meeting->scheduled_time->setTimezone('America/New_York')->format('F j, Y') }} &nbsp;
                                {{ $meeting->scheduled_time->setTimezone('America/New_York')->format('g:i A') }}
                            @elseif ($meeting->status == MeetingStatus::InProgress)
                                <flux:badge color="emerald">{{  MeetingStatus::InProgress->label() }}</flux:badge>
                            @elseif ($meeting->status == MeetingStatus::Completed)
                                <flux:badge color="blue">{{  MeetingStatus::Completed->label() }}</flux:badge>
                            @elseif ($meeting->status == MeetingStatus::Cancelled)
                                <flux:badge color="red">{{  MeetingStatus::Cancelled->label() }}</flux:badge>
                            @else
                                <flux:badge color="fuchsia" variant="solid">{{ $meeting->status->label() }}</flux:badge>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>{{ $meeting->summary }}</flux:table.cell>
                    </flux:table.row>
                @endcan
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="2">No meetings found.</flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    <div class="w-full text-right">
        @can('create', Meeting::class)
            <livewire:meeting.create-modal />
        @endcan
    </div>
</div>

