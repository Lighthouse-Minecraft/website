<?php

use Livewire\Volt\Component;

new class extends Component {
    public $announcement;

    public function mount($announcement)
    {
        $this->announcement = $announcement;
    }
}; ?>

<div>
    @if($announcement->tags && $announcement->tags->isNotEmpty())
        <div style="margin-top: 0.5rem;">
            <strong>Tags:</strong>
            @foreach($announcement->tags as $tag)
                <span style="background:#64748b;color:#fff;padding:2px 8px;border-radius:8px;margin-right:4px;font-size:0.85em;">{{ $tag->name }}</span>
            @endforeach
        </div>
    @endif
</div>
