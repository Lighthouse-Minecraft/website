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
    @if($announcement->categories && $announcement->categories->isNotEmpty())
        <div style="margin-top: 0.5rem;">
            <strong>Categories:</strong>
            @foreach($announcement->categories as $category)
                <span style="background:#334155;color:#fff;padding:2px 8px;border-radius:8px;margin-right:4px;font-size:0.85em;">{{ $category->name }}</span>
            @endforeach
        </div>
    @endif
</div>
