<?php

use Livewire\Volt\Component;
use App\Actions\CreateDefaultMeetingQuestions;
use App\Enums\MeetingType;
use App\Models\Meeting;
use Flux\Flux;
use Carbon\CarbonImmutable;
use Carbon\CarbonTimeZone;
use Illuminate\Validation\Rule;

new class extends Component {
    public $title;
    public $day;
    public $time;
    public $type = 'staff_meeting';
    public $scheduled_time;

    public function mount() {
        abort_unless(auth()->user()?->can('create', Meeting::class), 403, 'You do not have permission to create meetings.');
        $this->time = '7:00 PM';
    }

    public function CreateMeeting()
    {
        $this->validate([
            'title' => 'required|string|max:255',
            'day' => 'required|date',
            'time' => ['required', Rule::date()->format('g:i A')],
            'type' => ['required', Rule::in(array_column(MeetingType::cases(), 'value'))],
        ]);

        $this->scheduled_time = $this->scheduledAtUtc();

        $meeting = Meeting::create([
            'title' => $this->title,
            'type' => $this->type,
            'day' => $this->day,
            'scheduled_time' => $this->scheduled_time,
        ]);

        CreateDefaultMeetingQuestions::run($meeting);

        Flux::toast('Meeting created successfully!', 'Success', variant: 'success');
        $this->reset(['title', 'day', 'time', 'type']);

        return redirect()->route('meeting.edit', ['meeting' => $meeting]);
    }

    protected function scheduledAtUtc(string $tz = 'America/New_York'): CarbonImmutable
    {
        // Validate/assume ISO date + 24h time (HH:mm)
        $utcTime = CarbonImmutable::createFromFormat(
            'Y-m-d g:i A',
            "{$this->day} {$this->time}",
            new CarbonTimeZone($tz)
        )->utc();

        return $utcTime;
    }
}; ?>

<div>
    <flux:modal.trigger name='meeting-create-modal'>
        <flux:button variant="primary">
            Create a Meeting
        </flux:button>
    </flux:modal.trigger>

    <flux:modal name='meeting-create-modal'>
        <div class="text-left space-y-6">
            <flux:heading size="xl">Create a New Meeting</flux:heading>

            <flux:text>
                Fill out the form below to create a new meeting. Ensure all required fields are completed.
            </flux:text>

            <flux:input wire:model="title" name="title" label="Meeting Title" required />
            <flux:select wire:model="type" name="type" label="Meeting Type" required>
                @foreach(\App\Enums\MeetingType::cases() as $meetingType)
                    <flux:select.option value="{{ $meetingType->value }}">{{ $meetingType->label() }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:date-picker wire:model="day" name="day" label="Meeting Date" required />
            <flux:input wire:model="time" name="time" label="Meeting Time (Eastern Time - ET)" required />
            <flux:link href="https://time.is/ET" target="_blank" color="secondary">
                Need help converting time zones?
            </flux:link>

            <div class="text-right w-full mt-6">
                <flux:button data-testid="meeting-create.store" variant="primary" wire:click="CreateMeeting">
                    Create Meeting
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>
