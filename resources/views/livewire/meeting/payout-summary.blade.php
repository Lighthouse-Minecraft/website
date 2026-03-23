<?php

use App\Models\Meeting;
use Livewire\Volt\Component;

new class extends Component {
    public Meeting $meeting;

    public function mount(Meeting $meeting): void
    {
        $this->authorize('view', $meeting);
        $this->meeting = $meeting;
    }

    #[\Livewire\Attributes\Computed]
    public function payouts()
    {
        return $this->meeting->payouts()->with('user')->orderBy('id')->get();
    }

    #[\Livewire\Attributes\Computed]
    public function paidCount(): int
    {
        return $this->payouts->where('status', 'paid')->count();
    }

    #[\Livewire\Attributes\Computed]
    public function totalLumens(): int
    {
        return $this->payouts->where('status', 'paid')->sum('amount');
    }

    #[\Livewire\Attributes\Computed]
    public function skippedCount(): int
    {
        return $this->payouts->where('status', 'skipped')->count();
    }

    #[\Livewire\Attributes\Computed]
    public function failedCount(): int
    {
        return $this->payouts->where('status', 'failed')->count();
    }

    #[\Livewire\Attributes\Computed]
    public function pendingCount(): int
    {
        return $this->payouts->where('status', 'pending')->count();
    }
}; ?>

<div>
    @if($this->payouts->isNotEmpty())
        <flux:card>
            <flux:heading class="mb-2">Payout Summary</flux:heading>

            <flux:text variant="subtle" class="mb-4 text-sm">
                {{ $this->paidCount }} paid ({{ $this->totalLumens }} ✦ total)&nbsp;&middot;&nbsp;
                {{ $this->skippedCount }} skipped&nbsp;&middot;&nbsp;
                {{ $this->failedCount }} failed
                @if($this->pendingCount > 0)
                    &nbsp;&middot;&nbsp;{{ $this->pendingCount }} pending (interrupted — manual action needed)
                @endif
            </flux:text>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left border-b border-zinc-200 dark:border-zinc-700">
                            <th class="pb-2 pr-4 font-medium text-zinc-500 dark:text-zinc-400">Name</th>
                            <th class="pb-2 pr-4 font-medium text-zinc-500 dark:text-zinc-400 text-right">Amount</th>
                            <th class="pb-2 pr-4 font-medium text-zinc-500 dark:text-zinc-400">Status</th>
                            <th class="pb-2 font-medium text-zinc-500 dark:text-zinc-400">Reason</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        @foreach($this->payouts as $payout)
                            <tr wire:key="payout-summary-{{ $payout->id }}">
                                <td class="py-2 pr-4">
                                    <flux:link href="{{ route('profile.show', $payout->user) }}">{{ $payout->user->name }}</flux:link>
                                </td>
                                <td class="py-2 pr-4 text-right font-medium">
                                    @if($payout->status === 'paid')
                                        {{ $payout->amount }} ✦
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="py-2 pr-4">
                                    @if($payout->status === 'paid')
                                        <flux:badge color="green" size="sm">Paid</flux:badge>
                                    @elseif($payout->status === 'skipped')
                                        <flux:badge color="zinc" size="sm">Skipped</flux:badge>
                                    @elseif($payout->status === 'pending')
                                        <flux:badge color="yellow" size="sm">Pending</flux:badge>
                                    @else
                                        <flux:badge color="red" size="sm">Failed</flux:badge>
                                    @endif
                                </td>
                                <td class="py-2 text-zinc-500 dark:text-zinc-400">
                                    {{ $payout->skip_reason ?? '—' }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </flux:card>
    @endif
</div>
