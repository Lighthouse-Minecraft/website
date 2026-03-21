@php
    $tz = $tz ?? auth()->user()?->timezone ?? 'UTC';
    $visibleFlags = auth()->check()
        ? $message->flags->filter(function ($flag) {
            return auth()->user()->can('viewFlagged', \App\Models\Thread::class)
                || $flag->flagged_by_user_id === auth()->id();
        })
        : collect();

    $openFlags = $visibleFlags->where('status.value', 'new');
    $dismissedFlags = $visibleFlags->where('status.value', 'acknowledged');
@endphp

@if($visibleFlags->isNotEmpty())
    {{-- Open (undismissed) flags — show full alert box --}}
    @foreach($openFlags as $flag)
        <div wire:key="flag-{{ $flag->id }}" class="mt-2 rounded border border-red-300 dark:border-red-700 bg-red-50 dark:bg-red-950 p-3">
            <div class="text-sm">
                <strong>Flagged by <a href="{{ route('profile.show', $flag->flaggedBy) }}" class="text-blue-600 dark:text-blue-400 hover:underline">{{ $flag->flaggedBy->name }}</a></strong> on {{ $flag->created_at->setTimezone($tz)->format('M j, Y g:i A') }}
                <div class="mt-1 text-zinc-700 dark:text-zinc-300">{{ $flag->note }}</div>
            </div>
            @if(auth()->user()->can('viewFlagged', \App\Models\Thread::class) && ! $message->trashed())
                <div class="mt-3 flex gap-2">
                    <flux:button wire:click="openDismissModal({{ $flag->id }})" variant="primary" size="sm">
                        Dismiss Flag
                    </flux:button>
                    @can('delete', $message)
                        <flux:button wire:click="confirmDeleteMessage({{ $message->id }})" variant="danger" size="sm" icon="trash">
                            Delete Message
                        </flux:button>
                    @endcan
                </div>
            @endif
        </div>
    @endforeach

    {{-- Dismissed flags — collapsed into a warning icon --}}
    @if($dismissedFlags->isNotEmpty())
        <div class="mt-2 inline-flex">
            <flux:modal.trigger name="dismissed-flags-{{ $message->id }}">
                <flux:button variant="ghost" size="xs" class="!p-1 text-amber-500 hover:text-amber-600 dark:text-amber-400 dark:hover:text-amber-300" aria-label="View dismissed flags">
                    <flux:icon.exclamation-triangle class="size-4" />
                    <span class="text-xs ml-1">{{ $dismissedFlags->count() }} dismissed {{ Str::plural('flag', $dismissedFlags->count()) }}</span>
                </flux:button>
            </flux:modal.trigger>
        </div>

        <flux:modal name="dismissed-flags-{{ $message->id }}" class="w-full md:w-lg">
            <div class="space-y-4">
                <flux:heading size="lg">Dismissed Flags</flux:heading>
                <flux:subheading>{{ $dismissedFlags->count() }} {{ Str::plural('flag', $dismissedFlags->count()) }} on this message</flux:subheading>

                <div class="space-y-3 max-h-96 overflow-y-auto">
                    @foreach($dismissedFlags as $flag)
                        <div wire:key="dismissed-flag-{{ $flag->id }}" class="rounded border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800 p-3">
                            <div class="text-sm">
                                <strong>Flagged by <a href="{{ route('profile.show', $flag->flaggedBy) }}" class="text-blue-600 dark:text-blue-400 hover:underline">{{ $flag->flaggedBy->name }}</a></strong> on {{ $flag->created_at->setTimezone($tz)->format('M j, Y g:i A') }}
                                <div class="mt-1 text-zinc-700 dark:text-zinc-300">{{ $flag->note }}</div>
                                <div class="mt-2 text-xs text-zinc-500 dark:text-zinc-400">
                                    @if($flag->reviewedBy && $flag->reviewed_at)
                                        <strong>Dismissed by <a href="{{ route('profile.show', $flag->reviewedBy) }}" class="text-blue-600 dark:text-blue-400 hover:underline">{{ $flag->reviewedBy->name }}</a></strong> on {{ $flag->reviewed_at->setTimezone($tz)->format('M j, Y g:i A') }}
                                    @else
                                        <strong>Dismissed</strong>
                                    @endif
                                    @if($flag->staff_notes)
                                        <div class="mt-1">{{ $flag->staff_notes }}</div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="flex justify-end pt-2">
                    <flux:modal.close>
                        <flux:button variant="ghost">Close</flux:button>
                    </flux:modal.close>
                </div>
            </div>
        </flux:modal>
    @endif
@endif
