@php
    $tz = $tz ?? auth()->user()?->timezone ?? 'UTC';
    $visibleFlags = auth()->check()
        ? $message->flags->filter(function ($flag) {
            return auth()->user()->can('viewFlagged', \App\Models\Thread::class)
                || $flag->flagged_by_user_id === auth()->id();
        })
        : collect();
@endphp

@if($visibleFlags->isNotEmpty())
    <div class="mt-2 space-y-2">
        @foreach($visibleFlags as $flag)
            <div wire:key="flag-{{ $flag->id }}" class="rounded border border-red-300 dark:border-red-700 bg-red-50 dark:bg-red-950 p-3">
                <div class="text-sm">
                    <strong>Flagged by <a href="{{ route('profile.show', $flag->flaggedBy) }}" class="text-blue-600 dark:text-blue-400 hover:underline">{{ $flag->flaggedBy->name }}</a></strong> on {{ $flag->created_at->setTimezone($tz)->format('M j, Y g:i A') }}
                    <div class="mt-1 text-zinc-700 dark:text-zinc-300">{{ $flag->note }}</div>
                    @if($flag->status->value === 'acknowledged')
                        <div class="mt-2 text-xs text-zinc-600 dark:text-zinc-400">
                            @if($flag->reviewedBy && $flag->reviewed_at)
                                <strong>Acknowledged by <a href="{{ route('profile.show', $flag->reviewedBy) }}" class="text-blue-600 dark:text-blue-400 hover:underline">{{ $flag->reviewedBy->name }}</a></strong> on {{ $flag->reviewed_at->setTimezone($tz)->format('M j, Y g:i A') }}
                            @else
                                <strong>Acknowledged</strong>
                            @endif
                            @if($flag->staff_notes)
                                <div class="mt-1">{{ $flag->staff_notes }}</div>
                            @endif
                        </div>
                    @endif
                </div>
                @if($flag->status->value === 'new' && auth()->user()->can('viewFlagged', \App\Models\Thread::class))
                    <div class="mt-3">
                        <flux:button wire:click="openAcknowledgeModal({{ $flag->id }})" variant="primary" size="sm">
                            Acknowledge Flag
                        </flux:button>
                    </div>
                @endif
            </div>
        @endforeach
    </div>
@endif
