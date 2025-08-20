<?php

use Livewire\Volt\Component;

new class extends Component {
    public $announcement;

    public function mount($announcement)
    {
        $this->announcement = $announcement;
    }
};

?>

<div>
    <div style="margin-top: 0.5rem;">
        <strong>Comments:</strong>
        @php
            $user = auth()->user();
            $isAdmin = $user && $user->is_admin;
        @endphp
        @if($announcement->comments->isNotEmpty())
            <ul style="margin:0.5rem 0 0 0;padding-left:1rem;">
                @foreach($announcement->comments as $comment)
                    <li style="color:#cbd5e1;font-size:0.95em; position: relative;">
                        <span class="prose max-w-none whitespace-pre-wrap break-words [&_pre]:whitespace-pre-wrap [&_pre]:break-words [&_pre]:max-w-full [&_pre]:w-full [&_pre]:overflow-x-auto [&_code]:break-words [&_code]:break-all">{!! $comment->content !!}</span>
                        <br><br>
                        <em style="color:#94a3b8;">— {{ $comment->author->name ?? 'Unknown' }}</em>
                        <br><br>
                        <hr>
                        <span style="font-size:0.85em;color:#64748b;">(
                            <time class="comment-ts" datetime="{{ $comment->created_at->toIso8601String() }}">{{ $comment->created_at->format('M d, Y H:i') }}</time>
                        )</span>
                        @can('review', $comment)
                            @if($comment->needs_review)
                                <span class="inline-flex items-center rounded-full border border-amber-500 bg-amber-500/10 text-amber-200 text-[11px] px-2 py-0.5" style="margin-left:6px;">Needs Review</span>
                            @elseif($comment->reviewed_by)
                                <span class="inline-flex items-center rounded-full border border-green-600 bg-green-600/10 text-green-200 text-[11px] px-2 py-0.5" style="margin-left:6px;">Reviewed</span>
                            @endif
                        @endcan
                        @can('delete', $comment)
                            <form method="POST" action="{{ route('comments.destroy', $comment->id) }}" style="display:inline; margin-left:8px;">
                                @csrf
                                @method('DELETE')
                                <button type="submit" style="color:#ef4444;background:none;border:none;cursor:pointer;">Delete</button>
                            </form>
                        @endcan
                    </li>
                @endforeach
            </ul>
        @else
            <span class="text-gray-400">No comments yet.</span>
        @endif
    </div>

    <form method="POST" action="{{ route('comments.store') }}" style="margin-top:1rem;">
        @csrf
        <input type="hidden" name="commentable_id" value="{{ $announcement->id }}">
        <input type="hidden" name="commentable_type" value="announcement">
        <div x-data="{ v: '', show: false, check(){ const t=(this.v||'').replace(/\r/g,''); const lines=t.split('\n'); const last=(lines[lines.length-1]||'').trimEnd(); this.show=/```[\w-]*$/.test(last);} }" x-init="check()" x-effect="check()">
            <textarea name="content" rows="3" class="w-full border rounded p-2" placeholder="Add a quick comment..." required maxlength="2000" id="comment-content" x-model="v"></textarea>
        </div>
        <flux:button type="submit" variant="primary" class="mt-2">Post</flux:button>
        <div class="text-sm text-gray-500 mt-2" style="display: flex; align-items: center; gap: 8px;">
            By
            @if(auth()->check())
                <span style="display: inline-flex; align-items: center;">
                    @if(!empty(auth()->user()->avatar))
                        <flux:avatar size="xs" src="{{ auth()->user()->avatar }}" style="vertical-align: middle; margin-right: 4px;" />
                    @endif
                    <flux:link href="{{ route('profile.show', ['user' => auth()->user()]) }}">
                        {{ auth()->user()->name ?? 'Unknown' }}
                    </flux:link>
                </span>
            @else
                <span>Unknown</span>
            @endif
            <span x-data="{ t: new Date() }"
                  x-init="setInterval(() => { t = new Date() }, 1000)"
                  x-text="t.toLocaleString('en-US', { month: 'short', day: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' })"></span>
        </div>

    </form>
</div>
