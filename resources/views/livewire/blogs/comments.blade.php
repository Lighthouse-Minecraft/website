<?php

use Livewire\Volt\{Component};

new class extends Component {
    public $blog;

    public function mount($blog)
    {
        $this->blog = $blog;
    }
}; ?>

<div style="margin-top: 0.5rem;">
    <strong>Comments:</strong>
    {{ $blog->comments->count() }}
    @if($blog->comments->isNotEmpty())
        <ul style="margin:0.5rem 0 0 0;padding-left:1rem;">
            @foreach($blog->comments->take(2) as $comment)
                <li style="color:#cbd5e1;font-size:0.95em;">{{ $comment->body }} <em style="color:#94a3b8;">— {{ $comment->author->name ?? 'Unknown' }}</em></li>
            @endforeach
            @if($blog->comments->count() > 2)
                <li style="color:#94a3b8;font-size:0.9em;">...and {{ $blog->comments->count() - 2 }} more</li>
            @endif
        </ul>
    @endif
</div>
