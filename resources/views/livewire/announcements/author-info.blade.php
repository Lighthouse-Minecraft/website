<?php

use Livewire\Volt\Component;

new class extends Component {
    public $announcement;

    public function mount($announcement)
    {
        $this->announcement = $announcement;
    }
}; ?>

<flux:text size="sm" style="color:#94a3b8; margin-top:0; text-align: right;">
    @php $author = $announcement->author; @endphp
        <span style="display: flex; align-items: right; gap: 6px;">
            <span style="display: flex; align-items: center; gap: 6px; justify-content: flex-end; width: 100%;">
                Published
                @if($author)
                    by <span style="display: inline-flex; align-items: center;">
                        @if($author->avatarUrl())
                            <flux:avatar size="xs" src="{{ $author->avatarUrl() }}" style="vertical-align: middle; margin-right: 4px;" />
                        @endif
                        <flux:link href="{{ route('profile.show', ['user' => $author]) }}" style="vertical-align: middle;">
                            {{ $author->name }}
                        </flux:link>
                    </span>
                @endif
                on {{ $announcement->created_at ? $announcement->created_at->format('m/d/y \@ H:i') : 'N/A' }}
            </span>
        </span>
</flux:text>
