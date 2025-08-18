<?php

namespace App\Livewire\Announcements;

use App\Models\Announcement;
use Livewire\Component;

class Show extends Component
{
    public Announcement $announcement;

    public function mount(Announcement $announcement)
    {
        $this->announcement = $announcement;
    }

    public function render()
    {
        return view('livewire.announcements.show', [
            'announcement' => $this->announcement,
        ]);
    }
}
