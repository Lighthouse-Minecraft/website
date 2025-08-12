<?php

use Livewire\Volt\Component;

new class extends Component {
    public $announcement;

    public function mount($announcement)
    {
        $this->announcement = $announcement;
    }
}; ?>

<div style="margin-top: 0.5rem;">
    <strong>Comments:</strong>
    {{ $announcement->comments->count() }}
    @if($announcement->comments->isNotEmpty())
        <ul style="margin:0.5rem 0 0 0;padding-left:1rem;">
            @foreach($announcement->comments->take(2) as $comment)
                <li style="color:#cbd5e1;font-size:0.95em;">{{ $comment->body }} <em style="color:#94a3b8;">— {{ $comment->author->name ?? 'Unknown' }}</em></li>
            @endforeach
            @if($announcement->comments->count() > 2)
                <li style="color:#94a3b8;font-size:0.9em;">...and {{ $announcement->comments->count() - 2 }} more</li>
            @endif
        </ul>
    @endif
</div>
