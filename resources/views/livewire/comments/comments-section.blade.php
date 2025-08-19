<?php

use App\Models\Comment;
use App\Models\User;
use App\Enums\StaffRank;

?>

<div wire:poll.2s class="space-y-6">
    <ul>
        @php
            $user = auth()->user();
        @endphp
        @forelse($comments as $existing)
            @php
                $cardClass = 'mb-4 relative';
                $cardStyle = '';
                if ($existing->needs_review && $user && ($user->isAdmin() || $user->staff_rank === StaffRank::Officer)) {
                    $cardStyle = 'background-color: rgba(245, 158, 11, 0.08); border: 2px solid #f59e0b;';
                } else {
                    $cardClass .= ' bg-gray-700';
                }
            @endphp
            <div class="{{ $cardClass }}" {!! $cardStyle ? 'style="' . $cardStyle . '"' : '' !!}>
                <flux:card>
                    @if(($existing->needs_review || $existing->reviewed_by) && $user && ($user->isAdmin() || $user->staff_rank === StaffRank::Officer))
                        <div class="absolute right-3 top-3 flex items-center gap-2">
                            @if(!$existing->reviewed_by)
                                <span class="inline-flex items-center rounded-full border border-amber-500 bg-amber-500/10 text-amber-200 text-[11px] px-2 py-0.5">Needs Review</span>
                                <flux:button size="xs" icon="check" variant="primary" color="amber" wire:click="markReviewed({{ $existing->id }})">Mark as Reviewed</flux:button>
                            @else
                                @php
                                    $reviewerUser = $existing->reviewed_by ? User::find($existing->reviewed_by) : null;
                                    $reviewer = $reviewerUser ? $reviewerUser->name : 'Unknown';
                                    $reviewedAt = $existing->reviewed_at ? $existing->reviewed_at->format('M d, Y H:i') : '';
                                @endphp
                                <span class="inline-flex items-center rounded-full border border-green-600 bg-green-600/10 text-green-200 text-[11px] px-2 py-0.5">
                                    Reviewed by @if($reviewerUser)&nbsp;<flux:link href="{{ route('profile.show', ['user' => $reviewerUser]) }}">{{ $reviewer }}</flux:link>@else&nbsp;{{ $reviewer }} @endif @if($reviewedAt)&nbsp;on {{ $reviewedAt }}@endif
                                </span>
                            @endif
                        </div>
                    @endif
                    <div class="prose max-w-none mt-6 text-base text-gray-200 whitespace-pre-wrap break-words [&_pre]:whitespace-pre-wrap [&_pre]:break-words [&_pre]:max-w-full [&_pre]:w-full [&_pre]:overflow-x-auto [&_code]:break-words [&_code]:break-all">{!! $existing->content !!}</div>
                    <div class="w-full flex justify-end mt-2">
                        @if(auth()->id() === $existing->author_id)
                            @php
                                $from = request('from');
                            @endphp
                            @if($from === 'acp' || request()->routeIs('acp.*'))
                                <flux:button wire:navigate href="{{ route('acp.comments.edit', $existing->id) }}" size="xs" icon="pencil" variant="primary" title="Edit"></flux:button>
                            @else
                                @php
                                    $returnUrl = null;
                                    if (isset($parent)) {
                                        $parentType = strtolower(class_basename(get_class($parent)));
                                        if ($parentType === 'blog') {
                                            $returnUrl = route('blogs.show', ['id' => $parent->id] + (!empty($from) ? ['from' => $from] : []));
                                        } elseif ($parentType === 'announcement') {
                                            $returnUrl = route('announcements.show', ['id' => $parent->id] + (!empty($from) ? ['from' => $from] : []));
                                        }
                                    }
                                    if (!$returnUrl) {
                                        $returnUrl = url()->previous() ?: url('/');
                                    }

                                    $qs = ['return' => $returnUrl];
                                    if (!empty($from)) { $qs['from'] = $from; }
                                    $href = url('/comments/'.$existing->id.'/edit') . (count($qs) ? ('?'.http_build_query($qs)) : '');
                                @endphp
                                <flux:button wire:navigate href="{{ $href }}" size="xs" icon="pencil" variant="primary" title="Edit"></flux:button>
                            @endif
                        @endif

                        @can('delete', $existing)
                            <form method="POST" action="{{ route('comments.destroy', $existing->id) }}" onsubmit="return confirm('Delete this comment?');" style="display:inline; margin-left:8px;">
                                @csrf
                                @method('DELETE')
                                <flux:button type="submit" size="xs" icon="trash" variant="danger">Delete</flux:button>
                            </form>
                        @endcan
                    </div>
                    <div class="text-xs text-gray-400">By
                        @if($existing->author)
                            <flux:link href="{{ route('profile.show', ['user' => $existing->author]) }}">
                                {{ $existing->author->name }}
                            </flux:link>
                        @else
                            <span class="text-gray-400">Unknown</span>
                        @endif
                        on {{ $existing->created_at->format('M d, Y H:i') }}
                    </div>
                </flux:card>
            </div>
        @empty
            <li class="text-gray-400">No comments yet.</li>
        @endforelse

        {{-- DEPRECATING IDEA --}}
        {{-- @foreach($pendingComments as $pending)
            @if($pendingComments->count())
                @php
                    $latestPending = $pendingComments->sortByDesc('created_at')->first();
                @endphp
                @if($latestPending && $user && ($user->id === $latestPending->author_id || $user->isAdmin() || $user->staff_rank === StaffRank::Officer))
                    <li class="mb-4 border-b pb-4 bg-yellow-900/30">
                        <div class="flex items-center justify-between">
                            <div class="text-xs text-yellow-400 font-bold flex items-center gap-2">
                                <span class="badge badge-warning">Pending</span>
                                <span id="auto-approve-timer-{{ $latestPending->id }}" data-created-at="{{ $latestPending->created_at->toIso8601String() }}">
                                    Auto-approves in 2m 0s
                                </span>
                            </div>
                            @if($user && ($user->isAdmin() || $user->staff_rank === StaffRank::Officer))
                                <form action="{{ route('acp.comments.approve', $latestPending->id) }}" method="POST" style="display:inline;">
                                    @csrf
                                    <flux:button type="submit" size="xs" icon="check" variant="primary" color="green" title="Approve Now"></flux:button>
                                </form>
                            @endif
                        </div>
                        <div class="flex items-center justify-between">
                            <div class="text-xs text-gray-500">By
                                @if($latestPending->author)
                                    <flux:link href="{{ route('profile.show', ['user' => $latestPending->author]) }}">
                                        {{ $latestPending->author->name }}
                                    </flux:link>
                                @else
                                    <span class="text-gray-400">Unknown</span>
                                @endif
                                on {{ $latestPending->created_at->format('M d, Y H:i') }}
                            </div>
                        </div>
                        <div class="mt-2 text-base text-gray-200 whitespace-pre-wrap break-words">{!! $latestPending->content !!}</div>
                    </li>
                    @push('scripts')
                        <script>
                            function startCommentTimers() {
                                document.querySelectorAll('[id^="auto-approve-timer-"]').forEach(function(timerEl) {
                                    var commentId = timerEl.id.replace('auto-approve-timer-', '');
                                    var createdAt = timerEl.getAttribute('data-created-at');
                                    if (!createdAt || timerEl.dataset.timerStarted) return;
                                    timerEl.dataset.timerStarted = 'true';
                                    var createdAtMs = new Date(createdAt).getTime();
                                    var now = new Date().getTime();
                                    var secondsPassed = Math.floor((now - createdAtMs) / 1000);
                                    var secondsLeft = Math.max(0, 120 - secondsPassed);
                                    function updateTimer() {
                                        if (secondsLeft <= 0) {
                                            timerEl.textContent = 'Auto-approving...';
                                            clearInterval(interval);
                                            return;
                                        }
                                        var m = Math.floor(secondsLeft / 60);
                                        var s = secondsLeft % 60;
                                        timerEl.textContent = 'Auto-approves in ' + m + 'm ' + s + 's';
                                        secondsLeft--;
                                    }
                                    updateTimer();
                                    var interval = setInterval(updateTimer, 1000);
                                });
                            }
                            document.addEventListener('DOMContentLoaded', startCommentTimers);
                            if (window.Livewire) {
                                window.Livewire.hook('message.processed', startCommentTimers);
                            }
                        </script>
                    @endpush
                @endif
            @endif
        @endforeach --}}
    </ul>

    <flux:editor
        wire:model.defer="content"
        wire:model="commentContent"
        label="Add a comment"
        placeholder="Type your comment..."
        rows="3"
        class="
            mb-1
            w-full max-w-full overflow-hidden
            [&_[data-slot=content]_.ProseMirror]:break-words
            [&_[data-slot=content]_.ProseMirror]:break-all
            [&_[data-slot=content]_.ProseMirror]:whitespace-pre-wrap
            [&_[data-slot=content]_.ProseMirror]:w-full
            [&_[data-slot=content]_.ProseMirror]:max-w-full
            [&_[data-slot=content]_.ProseMirror]:overflow-x-auto
            [&_[data-slot=content]]:max-h-[500px]
            [&_[data-slot=content]]:overflow-y-auto
            [&_[data-slot=content]_pre]:overflow-x-auto
            [&_[data-slot=content]_pre]:!whitespace-pre-wrap
            [&_[data-slot=content]_pre]:max-w-full
            [&_[data-slot=content]_pre]:w-full
            [&_[data-slot=content]_pre_code]:!break-words
            [&_[data-slot=content]_pre_code]:break-all
            [&_[data-slot=content]_pre]:rounded-md
            [&_[data-slot=content]_pre]:p-3
            [&_[data-slot=content]_pre]:my-3
            [&_[data-slot=content]_pre]:border
            [&_[data-slot=content]_pre]:bg-black/10
            [&_[data-slot=content]_pre]:border-black/20
            dark:[&_[data-slot=content]_pre]:bg-white/10
            dark:[&_[data-slot=content]_pre]:border-white/20
            [&_[data-slot=content]_pre]:font-mono
            [&_[data-slot=content]_pre]:text-sm
        "
        required
    />

    <div class="w-full text-right mt-2">
        <flux:button type="submit" icon="chat-bubble-left-right" variant="primary">Post Comment</flux:button>
    </div>
</div>
