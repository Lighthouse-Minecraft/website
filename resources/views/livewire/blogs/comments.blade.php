
<?php
use Livewire\Volt\{Component};
new class extends Component {
    public $blog;
    public function mount($blog)
    {
        $this->blog = $blog;
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
        @if($blog->comments->isNotEmpty())
            <ul style="margin:0.5rem 0 0 0;padding-left:1rem;">
                @foreach($blog->comments as $comment)
                    <li style="color:#cbd5e1;font-size:0.95em;">
                        {!! $comment->content !!} <em style="color:#94a3b8;">— {{ $comment->author->name ?? 'Unknown' }}</em>
                        <span style="font-size:0.85em;color:#64748b;">({{ $comment->created_at->format('M d, Y H:i') }})</span>
                        @if($isAdmin || ($user && $comment->author_id === $user->id))
                            <form method="POST" action="{{ route('comments.destroy', $comment->id) }}" style="display:inline; margin-left:8px;">
                                @csrf
                                @method('DELETE')
                                <button type="submit" style="color:#ef4444;background:none;border:none;cursor:pointer;">Delete</button>
                            </form>
                        @endif
                    </li>
                @endforeach
            </ul>
        @else
            <span class="text-gray-400">No comments yet.</span>
        @endif
    </div>

    <form method="POST" action="{{ route('comments.store') }}" style="margin-top:1rem;">
        @csrf
        <input type="hidden" name="commentable_id" value="{{ $blog->id }}">
        <input type="hidden" name="commentable_type" value="blog">
        <div>
            <textarea name="content" rows="3" class="w-full border rounded p-2" placeholder="Add a comment..." required maxlength="2000" id="comment-content"></textarea>
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
            <span id="comment-preview-timestamp">{{ now()->format('M d, Y H:i') }}</span>
        </div>
        <script>
            function updateTimestamp() {
                const now = new Date();
                const options = { month: 'short', day: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' };
                document.getElementById('comment-preview-timestamp').textContent = now.toLocaleString('en-US', options);
            }
            setInterval(updateTimestamp, 1000);
        </script>
    </form>
</div>
