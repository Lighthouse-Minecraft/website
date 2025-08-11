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
        @if ($isLocked)
            <flux:textarea wire:model.defer="updatedContent" label="{{ ucfirst($section_key) }} Notes" />
            <flux:text size="xs">Locked by {{ $lockedByName }}</flux:text>
            <div class="w-full text-right">
                <flux:button variant="primary" wire:click="SaveNote">Save {{  ucfirst($section_key) }}</flux:button>
            </div>
        @else
            <flux:card>
                <flux:heading class="mb-4">Meeting Notes - {{  ucfirst($section_key) }}</flux:heading>

                <flux:text>
                    {{  $this->note->content }}
                </flux:text>
                <div class="w-full text-right">
                    <flux:button size="sm" wire:click="EditNote">Edit {{ ucfirst($section_key) }}</flux:button>
                </div>
            </flux:card>
        @endif
    @endif
</div>
