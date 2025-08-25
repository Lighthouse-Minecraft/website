<?php

use Livewire\Volt\{Component};

new class extends Component {
    public $blog;

    public function mount($blog)
    {
        $this->blog = $blog;
    }
}; ?>

<flux:text size="sm" style="color:#94a3b8; margin-top:0; text-align: right;">
    @php $author = $blog->author; @endphp
        <span style="display: flex; align-items: right; gap: 6px;">
            <span style="display: flex; align-items: center; gap: 6px; justify-content: flex-end; width: 100%;">
                Published
                @if($author)
                    by <span style="display: inline-flex; align-items: center;">
                        @if(!empty($author->avatar))
                            <flux:avatar size="xs" src="{{ $author->avatar }}" style="vertical-align: middle; margin-right: 4px;" />
                        @endif
                        <flux:link href="{{ route('profile.show', ['user' => $author]) }}" style="vertical-align: middle;">
                            {{ $author->name }}
                        </flux:link>
                    </span>
                @endif
                on @if($blog->created_at)
                    <time datetime="{{ $blog->created_at->toIso8601String() }}">
                        {{ $blog->created_at->format('m/d/y @ h:i a') }}
                    </time>
                @else
                    N/A
                @endif
            </span>
        </span>
</flux:text>
