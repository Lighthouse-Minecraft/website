<?php

use Livewire\Volt\Component;
use App\Models\Meeting;
use App\Enums\StaffRank;

new class extends Component {
    public $meetings;

    public function mount()
    {
        if (auth()->user()->isAtLeastRank(StaffRank::Officer)) {
            $this->meetings = Meeting::all();
        } else {
            $this->meetings = Meeting::where('is_public', true)->get();
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
                <flux:table.column>Meeting Date</flux:table.column>
                <flux:table.column>Summary</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($meetings as $meeting)
                @can('view', $meeting)
                    <flux:table.row>
                        <flux:table.cell><flux:link href="{{ route('meeting.show', $meeting) }}">{{ $meeting->day }}</flux:link></flux:table.cell>
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

