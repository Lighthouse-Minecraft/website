<?php

namespace App\Livewire\Meeting;

use App\Models\Meeting;
use Livewire\Component;
use Livewire\WithPagination;

class NotesDisplay extends Component
{
    use WithPagination;

    public ?string $sectionKey = null;

    public int $perPage = 10;

    public function mount(string $sectionKey)
    {
        $this->sectionKey = $sectionKey;
    }

    public function getMeetingsProperty()
    {
        return Meeting::with([
            'notes' => fn ($query) => $query
                ->where('section_key', $this->sectionKey)
                ->with('createdBy'),
        ])
            ->orderBy('scheduled_time', 'desc')
            ->paginate($this->perPage);
    }

    public function render()
    {
        return view('livewire.meeting.notes-display');
    }
}
