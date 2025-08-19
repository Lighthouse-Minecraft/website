<?php

use App\Models\Announcement;
use App\Models\Blog;
use App\Models\Comment;
use Flux\Flux;
use Livewire\Volt\Component;

new class extends Component {
    public int $commentable_id = 0;
    public string $commentContent = '';
    public string $commentable_type = '';

    public function getBlogOptionsProperty(): array
    {
        return Blog::query()
            ->get(['id', 'title'])
            ->map(fn ($b) => ['label' => $b->title, 'value' => (int) $b->id])
            ->toArray();
    }

    public function getAnnouncementOptionsProperty(): array
    {
        return Announcement::query()
            ->get(['id', 'title'])
            ->map(fn ($a) => ['label' => $a->title, 'value' => (int) $a->id])
            ->toArray();
    }

    public function saveComment()
    {
        $this->validate([
            'commentContent' => 'required|string|max:2000',
            'commentable_id' => 'required|integer',
            'commentable_type' => 'required|in:blog,announcement',
        ]);

        Comment::create([
            'content' => $this->commentContent,
            'author_id' => auth()->id(),
            'commentable_id' => $this->commentable_id,
            'commentable_type' => $this->commentable_type,
        ]);

        Flux::toast('Comment updated successfully!', 'Success', variant: 'success');
        return redirect()->route('acp.index', ['tab' => 'comment-manager']);
    }
};

?>

<flux:card class="max-w-2xl mx-auto mt-10">
    <div class="space-y-6 p-6">
        <flux:heading size="xl" class="mb-4">Edit Comment</flux:heading>

        @if(auth()->id() === $comment->author_id)
            <form wire:submit.prevent="saveComment">
                <div class="space-y-6">
                    <div>
                        <flux:editor
                            wire:model.defer="commentContent"
                            label="Edit comment"
                            placeholder="Type your comment..."
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
                            style="text-align: justify;"
                        />
                    </div>

                    <flux:field>
                        <flux:label>Attached To</flux:label>
                        @php
                            $rawType = (string) ($comment->getRawOriginal('commentable_type') ?? '');
                            $type = strtolower(class_basename($rawType));
                            if (! in_array($type, ['blog', 'announcement'], true)) {
                                $type = strtolower($rawType);
                            }
                            $title = $comment->commentable_title ?: ($comment->commentable->title ?? 'Unknown');
                            $url = null;
                            if ($type === 'blog') {
                                $url = route('blogs.show', ['id' => $comment->commentable_id, 'from' => 'acp']);
                            } elseif ($type === 'announcement') {
                                $url = route('announcements.show', ['id' => $comment->commentable_id, 'from' => 'acp']);
                            }
                        @endphp

                        <div class="text-sm text-gray-300">
                            <span class="uppercase text-xs text-gray-400">{{ ucfirst($type ?: 'Unknown') }}</span>
                            <span class="mx-2">—</span>
                            @if($url)
                                <flux:link wire:navigate href="{{ $url }}" class="font-medium">{{ $title }}</flux:link>
                            @else
                                <span class="font-medium">{{ $title }}</span>
                            @endif
                        </div>
                    </flux:field>

                    <div class="w-full text-right flex gap-2">
                        <flux:button type="submit" icon="document-check" variant="primary">Update Comment</flux:button>

                        @php
                            $from = request('from');
                            $returnUrl = request('return');
                            $backUrl = null;

                            if ($from === 'acp' || request()->routeIs('acp.*')) {
                                $backUrl = route('acp.index', ['tab' => 'comment-manager']);
                            } elseif (!empty($returnUrl)) {
                                $backUrl = $returnUrl;
                            } elseif ($from === 'dashboard') {
                                $backUrl = route('dashboard');
                            } else {
                                // Fallback to the parent show page if available
                                $rawType = (string) ($comment->getRawOriginal('commentable_type') ?? '');
                                $type = strtolower(class_basename($rawType));
                                if (! in_array($type, ['blog', 'announcement'], true)) {
                                    $type = strtolower($rawType);
                                }
                                if ($type === 'blog') {
                                    $backUrl = route('blogs.show', $comment->commentable_id);
                                } elseif ($type === 'announcement') {
                                    $backUrl = route('announcements.show', $comment->commentable_id);
                                } else {
                                    $backUrl = url('/');
                                }
                            }
                        @endphp
                        
                        <flux:button
                            onclick="if (document.referrer) { event.preventDefault(); window.history.back(); }"
                            href="{{ $backUrl }}"
                            wire:navigate
                            variant="primary"
                            class="mx-4"
                        >
                            Cancel
                        </flux:button>
                    </div>
                </div>
            </form>
        @else
            <div class="text-red-500 font-semibold">You are not authorized to edit this comment.</div>
        @endif
    </div>
</flux:card>
