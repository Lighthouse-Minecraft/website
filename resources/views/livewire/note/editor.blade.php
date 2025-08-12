<?php

use App\Models\Meeting;
use App\Models\User;
use App\Models\MeetingNote;
use Livewire\Volt\Component;

new class extends Component {
    public Meeting $meeting;
    public string $section_key;
    public MeetingNote $note;
    public $locked;
    public $updatedContent;

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
        $this->syncLockState();

        $this->syncLockState();
    }

    public function CreateNote() {
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
    }

    public function EditNote() {
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
        $this->note->update([
            'content' => $this->updatedContent,
            'locked_by' => null,
            'locked_at' => null,
            'lock_updated_at' => null,
        ]);

        $this->note->refresh();
        $this->syncLockState();
    }

    // UpdateNote will do a periodic save but not release the lock
    public function UpdateNote() {
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
        $this->note->refresh();
        $this->syncLockState();
    }

    private function syncLockState(): void {
        $lockerId = $this->note->locked_by;

        $this->isLocked = (bool) $lockerId;
        $this->lockedById = $lockerId;
        $this->lockedByName = $lockerId ? optional(User::find($lockerId))->name : null;
        $this->isLockedByMe = $lockerId && $lockerId === auth()->id();
    }

}; ?>

<div class="space-y-6">
    @if (! $this->note->exists)
        <flux:button variant="primary" wire:click="CreateNote">Create {{  ucfirst($section_key) }}</flux:button>
    @else
        @if ($isLockedByMe)
            <flux:textarea wire:model.live.debounce.10s="updatedContent" wire:input.debounce.10s="UpdateNote" label="{{ ucfirst($section_key) }} Notes" rows="15" />

            <div class="flex my-4">
                <div class="text-left w-1/2">
                    @if($isLocked)
                        <flux:text size="xs">You have locked this section.</flux:text>
                    @endif
                </div>

                <div class="w-full text-right">
                        <flux:button size="xs" wire:click="SaveNote" variant="primary">Save {{  ucfirst($section_key) }}</flux:button>
                </div>
            </div>
        @else
            <div wire:poll.10s="RefreshNote">
                <flux:card>
                    <flux:heading class="mb-4">Meeting Notes - {{  ucfirst($section_key) }}</flux:heading>

                    <flux:text>
                        {!!  nl2br($this->note->content) !!}
                    </flux:text>
                </flux:card>

                <div class="flex my-4">
                    <div class="text-left w-1/2">
                        @if($isLocked)
                            <flux:text size="xs">Locked by <flux:link href="{{ route('profile.show', $lockedById) }}">{{ $lockedByName }}</flux:link></flux:text>
                        @endif
                    </div>

                    <div class="w-full text-right">
                        @if($isLocked)
                            <flux:button size="xs" disabled variant="filled">Edit {{ ucfirst($section_key) }}</flux:button>
                        @else
                            <flux:button size="xs" wire:click="EditNote" variant="primary" color="indigo">Edit {{ ucfirst($section_key) }}</flux:button>
                        @endif
                    </div>
                </div>
            </div>
        @endif
    @endif
</div>
