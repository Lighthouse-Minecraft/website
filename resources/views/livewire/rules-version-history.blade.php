<?php

use App\Models\RuleVersion;
use Livewire\Volt\Component;

new class extends Component {
    public function getVersions()
    {
        return RuleVersion::with(['createdBy', 'approvedBy'])
            ->where('status', 'published')
            ->orderByDesc('version_number')
            ->get();
    }
}; ?>

<div class="space-y-4">
    <flux:heading size="xl">Rules Version History</flux:heading>
    <flux:text variant="subtle">All published versions of the community rules.</flux:text>

    <flux:table>
        <flux:table.columns>
            <flux:table.column>Version</flux:table.column>
            <flux:table.column>Published</flux:table.column>
            <flux:table.column>Created By</flux:table.column>
            <flux:table.column>Approved By</flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
            @forelse($this->getVersions() as $version)
                <flux:table.row wire:key="version-{{ $version->id }}">
                    <flux:table.cell>
                        <flux:badge variant="primary" size="sm">v{{ $version->version_number }}</flux:badge>
                    </flux:table.cell>
                    <flux:table.cell>
                        {{ $version->published_at?->format('M j, Y') ?? '—' }}
                    </flux:table.cell>
                    <flux:table.cell>
                        {{ $version->createdBy?->name ?? '—' }}
                    </flux:table.cell>
                    <flux:table.cell>
                        {{ $version->approvedBy?->name ?? '—' }}
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="4">
                        <flux:text variant="subtle">No published versions yet.</flux:text>
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>
</div>
