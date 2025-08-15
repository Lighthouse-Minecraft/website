<?php

use App\Enums\MeetingStatus;
use App\Models\Meeting;
use App\Models\MeetingNote;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Volt\Component;


new class extends Component {
    public Meeting $meeting;
    public string $section_key;
    public MeetingNote $note;
    public $locked;
    public $updatedContent;
    public $pollTime = 60;
    public $noteExists = false;

    // primitive UI state (no relations in Blade)
    public bool $isLocked = false;
    public ?int $lockedById = null;
    public ?string $lockedByName = null;
    public bool $isLockedByMe = false;

    public function mount(Meeting $meeting, string $section_key) {
        $this->meeting = $meeting;
        $this->section_key = $section_key;

        $note = MeetingNote::where('meeting_id', $meeting->id)
            ->where('section_key', $section_key)
            ->with('lockedBy')
            ->first();

        $this->note = $note ?: new MeetingNote();
        $this->updatedContent = $this->note->content;
        $this->noteExists = ($this->note->id) ? true : false;

        $this->pollTime = ($meeting->status == MeetingStatus::InProgress) ? 10 : 60;

        $this->syncLockState();
    }

    public function LookupNote() {
        $note = MeetingNote::where('meeting_id', $this->meeting->id)
            ->where('section_key', $this->section_key)
            ->with('lockedBy')
            ->first();

        $this->note = $note ?: new MeetingNote();
    }

    public function CreateNote() {
        $this->authorize('create', App\Models\MeetingNote::class);

        // Make sure the note wasn't already created
        $this->LookupNote();
        if ($this->note->id) {
            $this->RefreshNote();
            return;
        }

        $user = auth()->user();
        $time = now();

        $this->note = MeetingNote::create([
            'created_by' => $user->id,
            'meeting_id' => $this->meeting->id,
            'section_key' => $this->section_key,
            'locked_by' => $user->id,
            'locked_at' => $time,
            'lock_updated_at' => $time
        ]);
        $this->RefreshNote();
    }

    public function EditNote() {
        $this->authorize('update', $this->note);

        // Make sure someone else hasn't locked the record already
        $this->note->refresh();
        if ($this->note->locked_by) {
            $this->syncLockState();
            return;
        }

        $user = auth()->user();
        $time = now();

        $this->note->update([
            'locked_by' => $user->id,
            'locked_at' => $time,
            'lock_updated_at' => $time
        ]);

        $this->note->refresh();
        $this->syncLockState();
    }

    public function SaveNote() {
        $this->authorize('updateSave', $this->note);

        $this->note->update([
            'content' => $this->updatedContent,
        ]);

        $this->UnlockNote();
    }

    public function UnlockNote() {
        $this->authorize('update', $this->note);
        $this->note->update([
            'locked_by' => null,
            'locked_at' => null,
            'lock_updated_at' => null,
        ]);

        $this->note->refresh();
        $this->syncLockState();
    }

    // Check if the lock needs to be released
    public function HeartbeatCheck() {
        if ($this->note->content == $this->updatedContent) {
            $expirey_age = config('lighthouse.meeting_note_unlock_mins');
            $lock_last_updated_at = Carbon::parse($this->note->lock_updated_at);

            // See if it's time to release the lock
            $expiry_age = config('lighthouse.meeting_note_unlock_mins');
            $lock_last_updated_at = Carbon::parse($this->note->lock_updated_at);

            // See if it's time to release the lock
            if ($lock_last_updated_at->lt(now()->subMinutes($expiry_age))) {
                $this->UnlockNote();
            }

            return;
        }
    }

    // UpdateNote will do a periodic save but not release the lock
    public function UpdateNote() {
        $this->authorize('updateSave', $this->note);

        if ($this->note->content == $this->updatedContent) {
            return;
        }

        $timestamp = now();

        $this->note->update([
            'content' => $this->updatedContent,
            'lock_updated_at' => $timestamp,
        ]);
    }

    public function RefreshNote() {
        if (! $this->note->id) {
            $this->LookupNote();
            $this->noteExists = ($this->note->id) ? true : false;
        }

        $this->note->refresh();
        $this->HeartbeatCheck();
        $this->syncLockState();
    }

    private function syncLockState(): void {
        $lockerId = $this->note->locked_by;

        $this->noteExists = ($this->note->id) ? true : false;
        $this->isLocked = (bool) $lockerId;
        $this->lockedById = $lockerId;
        $this->lockedByName = $lockerId ? optional(User::find($lockerId))->name : null;
        $this->isLockedByMe = $lockerId && $lockerId === auth()->id();
    }

}; ?>

<div class="space-y-6">
        @if ($isLockedByMe)
            <div wire:poll.3s="HeartbeatCheck"></div>
            @php $rows = ($section_key == 'agenda') ? 15 : 6; @endphp
            <flux:textarea wire:model.live.debounce.5s="updatedContent" wire:input.debounce.5s="UpdateNote" label="{{ ucfirst($section_key) }} Notes" rows="{{ $rows }}" />

            <div class="flex my-4">
                <div class="text-left w-full">
                    @if($isLocked)
                        <flux:text size="xs">You have locked this section.</flux:text>
                    @endif
                </div>

                <div class="w-full text-right">
                    <flux:button size="xs" wire:click="SaveNote" variant="primary">Save {{  ucfirst($section_key) }} Notes</flux:button>
                </div>
            </div>
        @else
            <div wire:poll.{{  $pollTime }}="RefreshNote">

                @if(! $this->noteExists)
                    <div class="w-full text-right">
                        @can('create', App\Models\MeetingNote::class)
                            @php $buttonLabel = ($section_key == 'agenda') ? 'Create Agenda' : 'Create ' . ucfirst($section_key) . ' Note'; @endphp
                            <flux:button variant="primary" wire:click="CreateNote">{{ $buttonLabel }}</flux:button>
                        @endcan
                    </div>
                @else

                    <flux:card>
                        <flux:heading class="mb-4">Meeting Notes - {{  ucfirst($section_key) }}</flux:heading>

                        <flux:text>
                            {!!  nl2br($this->note->content) !!}
                        </flux:text>
                    </flux:card>

                    <div class="flex my-4">
                        <div class="text-left w-full">
                            @if($isLocked)
                                <flux:text size="xs">Locked by <flux:link href="{{ route('profile.show', $lockedById) }}">{{ $lockedByName }}</flux:link></flux:text>
                            @endif
                        </div>

                        <div class="w-full text-right">
                            @can('update', $this->note)
                                @if($isLocked)
                                    <flux:button size="xs" disabled variant="filled">Edit {{ ucfirst($section_key) }} Notes</flux:button>
                                @else
                                    <flux:button size="xs" wire:click="EditNote" variant="primary" color="indigo">Edit {{ ucfirst($section_key) }} Notes</flux:button>
                                @endif
                            @endcan
                        </div>
                    </div>
                @endif
            </div>
        @endif
</div>
