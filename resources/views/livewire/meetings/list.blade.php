<?php

use Livewire\Volt\Component;
use App\Models\Meeting;
use App\Enums\StaffRank;

new class extends Component {
    public $meetings;

    public function mount()
    {
        if (Gate::allows('viewAnyPrivate', Meeting::class)) {
            $this->meetings = Meeting::all();
        } elseif (Gate::allows('viewAnyPublic', Meeting::class)) {
            $this->meetings = Meeting::where('is_public', true)->get();
        } else {
            $this->meetings = [];
        }
    }
}; ?>

<div class="space-y-6">
    <flux:heading size="xl">Meeting Minutes</flux:heading>
    <flux:text>
        Here you can find the minutes from the Lighthouse Staff meetings. Click on a meeting to view its details.
    </flux:text>

    <flux:table>
        <flux:table.columns>
                <flux:table.column>Meeting</flux:table.column>
                <flux:table.column>Meeting Start</flux:table.column>
                <flux:table.column>Summary</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($meetings as $meeting)
                @can('view', $meeting)
                    <flux:table.row>
                        <flux:table.cell><flux:link href="{{ route('meeting.show', $meeting) }}">{{ $meeting->title }} - {{ $meeting->day }}</flux:link></flux:table.cell>
                        <flux:table.cell>{{ $meeting->scheduled_time->setTimezone('America/New_York')->format('Y-m-d  \@  g:i A') }}</flux:table.cell>
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

