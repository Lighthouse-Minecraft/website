<?php

use Livewire\Volt\{Component};

new class extends Component {
    public $blog;

    public function mount($blog)
    {
        $this->blog = $blog;
    }
}; ?>

<div>
    @if($blog->tags && $blog->tags->isNotEmpty())
        <div style="margin-top: 0.5rem;">
            <strong>Tags:</strong>
            @foreach($blog->tags as $tag)
                <span style="background:#64748b;color:#fff;padding:2px 8px;border-radius:8px;margin-right:4px;font-size:0.85em;">{{ $tag->name }}</span>
            @endforeach
        </div>
    @endif
</div>
