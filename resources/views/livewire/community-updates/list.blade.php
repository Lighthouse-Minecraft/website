<?php

use Livewire\Volt\Component;
use App\Models\Meeting;
use App\Enums\MeetingStatus;

new class extends Component {
    public $meetings;

    public function mount(): void
    {
        $this->meetings = Meeting::query()
            ->where('status', MeetingStatus::Completed->value)
            ->orderBy('day', 'desc')
            ->get();
    }
}; ?>

<div class="text-center space-y-6">

    @foreach($meetings as $meeting)
        <flux:separator text="{{  $meeting->title }} - {{ $meeting->day }}" />
        <flux:card class="text-left w-full lg:w-1/2 mx-auto mb-24">
            {!!  nl2br($meeting->community_minutes) !!}
        </flux:card>
    @endforeach
</div>
