<?php

use App\Enums\MembershipLevel;
use App\Models\RuleVersion;
use App\Models\User;
use Livewire\Volt\Component;

new class extends Component {
    public function mount(): void
    {
        $this->authorize('rules.manage');
    }

    public function getComplianceData(): array
    {
        $version = RuleVersion::currentPublished();

        if (! $version) {
            return ['version' => null, 'users' => collect()];
        }

        $agreedUserIds = $version->agreements()->pluck('user_id');

        $users = User::whereNotIn('id', $agreedUserIds)
            ->where('membership_level', '>=', MembershipLevel::Stowaway->value)
            ->orderBy('name')
            ->get();

        return ['version' => $version, 'users' => $users];
    }
}; ?>

<div class="space-y-4">
    <flux:heading size="xl">Rules Compliance</flux:heading>
    <flux:text variant="subtle">Users who have not yet agreed to the current published version.</flux:text>

    @php $data = $this->getComplianceData(); @endphp

    @if (!$data['version'])
        <flux:text variant="subtle">No published rules version found.</flux:text>
    @elseif ($data['users']->isEmpty())
        <flux:text class="text-green-400">All members have agreed to the current rules version.</flux:text>
    @else
        @php $daysSincePublish = (int) $data['version']->published_at->diffInDays(now()); @endphp
        <flux:text variant="subtle" class="text-sm">
            Version {{ $data['version']->version_number }} published
            {{ $data['version']->published_at->format('M j, Y') }}
            ({{ $daysSincePublish }} {{ Str::plural('day', $daysSincePublish) }} ago)
            — {{ $data['users']->count() }} {{ Str::plural('user', $data['users']->count()) }} pending
        </flux:text>

        <flux:table>
            <flux:table.columns>
                <flux:table.column>User</flux:table.column>
                <flux:table.column>Level</flux:table.column>
                <flux:table.column>Days Overdue</flux:table.column>
                <flux:table.column>Reminder Sent</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @foreach ($data['users'] as $user)
                    <flux:table.row wire:key="compliance-{{ $user->id }}">
                        <flux:table.cell>
                            <div class="font-medium">{{ $user->name }}</div>
                            <div class="text-xs text-zinc-400">{{ $user->email }}</div>
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" variant="outline">{{ $user->membership_level->label() }}</flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>
                            @php $days = $daysSincePublish; @endphp
                            @if ($days >= 28)
                                <flux:badge color="red" size="sm">{{ $days }}d — Brig Eligible</flux:badge>
                            @elseif ($days >= 14)
                                <flux:badge color="amber" size="sm">{{ $days }}d — Reminder Due</flux:badge>
                            @else
                                <span class="text-zinc-400 text-sm">{{ $days }}d</span>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>
                            @if ($user->rules_reminder_sent_at)
                                <span class="text-zinc-300 text-sm">{{ $user->rules_reminder_sent_at->format('M j, Y') }}</span>
                            @else
                                <span class="text-zinc-500 text-sm">Not sent</span>
                            @endif
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    @endif
</div>
