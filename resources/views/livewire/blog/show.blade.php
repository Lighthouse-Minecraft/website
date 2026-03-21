<?php

use App\Actions\AcknowledgeFlag;
use App\Actions\DeleteMessage;
use App\Actions\FlagMessage;
use App\Actions\PostBlogComment;
use App\Enums\BlogPostStatus;
use App\Enums\MessageKind;
use App\Models\BlogPost;
use App\Models\Message;
use App\Models\MessageFlag;
use Flux\Flux;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Volt\Component;

new class extends Component {
    public BlogPost $post;
    public bool $isTrashed = false;
    public string $renderedBody = '';
    public string $commentBody = '';

    // Flagging
    public ?int $flaggingMessageId = null;
    public string $flagReason = '';
    public ?int $acknowledgingFlagId = null;
    public string $staffNotes = '';

    public function mount(string $categorySlug, string $slug): void
    {
        $post = BlogPost::withTrashed()
            ->with(['author', 'category', 'tags'])
            ->where('slug', $slug)
            ->first();

        if (! $post) {
            abort(404);
        }

        if ($post->trashed()) {
            $this->isTrashed = true;
            $this->post = $post;

            return;
        }

        if ($post->status !== BlogPostStatus::Published) {
            abort(404);
        }

        $this->post = $post;
        $this->renderedBody = $post->renderBody();
    }

    #[Computed]
    public function comments()
    {
        $thread = $this->post->commentThread;

        if (! $thread) {
            return collect();
        }

        $query = $thread->messages()
            ->where('kind', MessageKind::Message)
            ->where('is_pending_moderation', false)
            ->with(['user.minecraftAccounts', 'user.discordAccounts', 'flags.flaggedBy', 'flags.reviewedBy', 'deletedBy']);

        if (auth()->check() && auth()->user()->can('viewFlagged', \App\Models\Thread::class)) {
            $query->withTrashed();
        }

        return $query->orderBy('created_at')->get();
    }

    #[Computed]
    public function canComment(): bool
    {
        return auth()->check() && auth()->user()->can('post-blog-comment');
    }

    public function postComment(): void
    {
        $this->authorize('post-blog-comment');

        $this->validate([
            'commentBody' => 'required|string|min:3|max:5000',
        ]);

        $message = PostBlogComment::run($this->post, auth()->user(), $this->commentBody);

        $this->commentBody = '';

        if ($message->is_pending_moderation) {
            Flux::toast('Your comment has been submitted for moderation.', 'Submitted', variant: 'success');
        } else {
            Flux::toast('Comment posted!', 'Success', variant: 'success');
        }

        unset($this->comments);
    }

    #[Computed]
    public function canViewFlagged(): bool
    {
        return auth()->check() && auth()->user()->can('viewFlagged', \App\Models\Thread::class);
    }

    public function canFlagComment(Message $message): bool
    {
        if (! auth()->check()) {
            return false;
        }

        $user = auth()->user();

        return $user->can('flag-blog-comment')
            && $message->user_id !== $user->id;
    }

    public function openFlagModal(int $messageId): void
    {
        $message = Message::findOrFail($messageId);

        if (! $this->canFlagComment($message)) {
            abort(403);
        }

        $this->flaggingMessageId = $messageId;
        $this->flagReason = '';

        Flux::modal('flag-comment')->show();
    }

    public function submitFlag(): void
    {
        $validator = Validator::make(
            ['flagReason' => $this->flagReason],
            ['flagReason' => 'required|string|min:10']
        );

        if ($validator->fails()) {
            $this->addError('flagReason', $validator->errors()->first('flagReason'));

            return;
        }

        $message = Message::findOrFail($this->flaggingMessageId);

        if (! $this->canFlagComment($message)) {
            abort(403);
        }

        FlagMessage::run($message, auth()->user(), $this->flagReason);

        $this->flaggingMessageId = null;
        $this->flagReason = '';

        Flux::modal('flag-comment')->close();
        Flux::toast('Comment flagged for review. Staff will be notified.', variant: 'success');

        unset($this->comments);
    }

    public function openAcknowledgeModal(int $flagId): void
    {
        if (! $this->canViewFlagged) {
            abort(403);
        }

        $this->acknowledgingFlagId = $flagId;
        $this->staffNotes = '';

        Flux::modal('acknowledge-flag')->show();
    }

    public function acknowledgeFlag(): void
    {
        if (! $this->canViewFlagged) {
            abort(403);
        }

        $flag = MessageFlag::findOrFail($this->acknowledgingFlagId);

        AcknowledgeFlag::run($flag, auth()->user(), $this->staffNotes ?: null);

        $this->acknowledgingFlagId = null;
        $this->staffNotes = '';

        Flux::modal('acknowledge-flag')->close();
        Flux::toast('Flag acknowledged successfully!', variant: 'success');

        unset($this->comments);
    }

    public function deleteMessage(int $messageId): void
    {
        $message = Message::withTrashed()->findOrFail($messageId);
        $this->authorize('delete', $message);

        DeleteMessage::run($message, auth()->user());

        Flux::toast('Comment deleted.', variant: 'success');

        unset($this->comments);
    }

    public function with(): array
    {
        $jsonLd = null;
        $canonicalUrl = $this->post->url();

        if (! $this->isTrashed) {
            $jsonLd = [
                '@context' => 'https://schema.org',
                '@type' => 'Article',
                'headline' => $this->post->title,
                'datePublished' => $this->post->published_at?->toIso8601String(),
                'dateModified' => $this->post->updated_at->toIso8601String(),
                'author' => [
                    '@type' => 'Person',
                    'name' => $this->post->author->name,
                    'url' => route('blog.author', $this->post->author->slug),
                ],
                'publisher' => [
                    '@type' => 'Organization',
                    'name' => config('app.name'),
                ],
                'mainEntityOfPage' => [
                    '@type' => 'WebPage',
                    '@id' => $canonicalUrl,
                ],
            ];

            if ($this->post->meta_description) {
                $jsonLd['description'] = $this->post->meta_description;
            }

            if ($this->post->heroImageUrl()) {
                $jsonLd['image'] = $this->post->heroImageUrl();
            }
        }

        return [
            'jsonLd' => $jsonLd,
            'canonicalUrl' => $canonicalUrl,
        ];
    }
}; ?>

<div>
    @if(! $isTrashed)
        @push('meta')
            @if($post->meta_description)
                <meta name="description" content="{{ $post->meta_description }}" />
            @endif

            {{-- Open Graph --}}
            <meta property="og:type" content="article" />
            <meta property="og:title" content="{{ $post->title }}" />
            @if($post->meta_description)
                <meta property="og:description" content="{{ $post->meta_description }}" />
            @endif
            <meta property="og:url" content="{{ $canonicalUrl }}" />
            @if($post->ogImageUrl() || $post->heroImageUrl())
                <meta property="og:image" content="{{ $post->ogImageUrl() ?: $post->heroImageUrl() }}" />
            @endif

            {{-- Twitter Card --}}
            <meta name="twitter:card" content="summary_large_image" />
            <meta name="twitter:title" content="{{ $post->title }}" />
            @if($post->meta_description)
                <meta name="twitter:description" content="{{ $post->meta_description }}" />
            @endif
            @if($post->ogImageUrl() || $post->heroImageUrl())
                <meta name="twitter:image" content="{{ $post->ogImageUrl() ?: $post->heroImageUrl() }}" />
            @endif

            {{-- JSON-LD --}}
            @if($jsonLd)
                <script type="application/ld+json">{!! json_encode($jsonLd, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) !!}</script>
            @endif

            <link rel="canonical" href="{{ $canonicalUrl }}" />
        @endpush
    @endif

    <div class="mx-auto max-w-4xl px-4 py-8">
        <div class="mb-4">
            <flux:link href="{{ route('blog.index') }}" wire:navigate>
                &larr; Back to Blog
            </flux:link>
        </div>

        @if($isTrashed)
            <flux:card>
                <div class="py-12 text-center">
                    <flux:heading size="lg">This post has been removed</flux:heading>
                    <flux:text variant="subtle" class="mt-2">
                        The blog post you are looking for is no longer available.
                    </flux:text>
                    <div class="mt-4">
                        <flux:button href="{{ route('blog.index') }}" variant="primary" wire:navigate>
                            Browse all posts
                        </flux:button>
                    </div>
                </div>
            </flux:card>
        @else
            <article>
                @if($post->heroImageUrl())
                    <img src="{{ $post->heroImageUrl() }}" alt="{{ $post->title }}" class="mb-6 w-full rounded-lg object-cover" style="max-height: 400px;" />
                @endif

                <flux:heading size="xl">{{ $post->title }}</flux:heading>

                <div class="mt-3 flex flex-wrap items-center gap-3 text-sm text-zinc-500 dark:text-zinc-400">
                    <span>
                        By <a href="{{ route('blog.author', $post->author->slug) }}" class="font-medium hover:underline" wire:navigate>{{ $post->author->name }}</a>
                    </span>
                    <span>&middot;</span>
                    <span>{{ $post->published_at->format('F j, Y') }}</span>
                    @if($post->category)
                        <span>&middot;</span>
                        <a href="{{ route('blog.category', $post->category->slug) }}" wire:navigate>
                            <flux:badge>{{ $post->category->name }}</flux:badge>
                        </a>
                    @endif
                </div>

                @if($post->tags->count())
                    <div class="mt-3 flex flex-wrap gap-1">
                        @foreach($post->tags as $tag)
                            <a href="{{ route('blog.tag', $tag->slug) }}" wire:navigate>
                                <flux:badge variant="outline" size="sm">{{ $tag->name }}</flux:badge>
                            </a>
                        @endforeach
                    </div>
                @endif

                <div class="prose mt-8 max-w-none dark:prose-invert">
                    {!! $renderedBody !!}
                </div>

                {{-- Social Sharing --}}
                <div class="mt-8 border-t border-zinc-200 pt-6 dark:border-zinc-700">
                    <flux:heading size="sm" class="mb-3">Share this post</flux:heading>
                    <div class="flex gap-3">
                        <flux:button
                            as="a"
                            href="https://twitter.com/intent/tweet?url={{ urlencode($canonicalUrl) }}&text={{ urlencode($post->title) }}"
                            target="_blank"
                            rel="noopener noreferrer"
                            variant="ghost"
                            size="sm"
                        >
                            Twitter / X
                        </flux:button>

                        <flux:button
                            as="a"
                            href="https://www.facebook.com/sharer/sharer.php?u={{ urlencode($canonicalUrl) }}"
                            target="_blank"
                            rel="noopener noreferrer"
                            variant="ghost"
                            size="sm"
                        >
                            Facebook
                        </flux:button>

                        <flux:button
                            variant="ghost"
                            size="sm"
                            x-data
                            x-on:click="navigator.clipboard.writeText('{{ $canonicalUrl }}'); $flux.toast('Link copied to clipboard!', 'Copied', { variant: 'success' })"
                        >
                            Copy Link
                        </flux:button>
                    </div>
                </div>
            </article>

            {{-- Comments Section --}}
            <div class="mt-10 border-t border-zinc-200 pt-8 dark:border-zinc-700">
                <flux:heading size="lg" class="mb-6">Comments ({{ $this->comments->reject->trashed()->count() }})</flux:heading>

                @if($this->comments->isNotEmpty())
                    <div class="space-y-6">
                        @foreach($this->comments as $comment)
                            @if($comment->trashed())
                                {{-- Deleted comment — ghostly, visible only to moderators/admins --}}
                                <div wire:key="comment-{{ $comment->id }}" class="flex gap-3 opacity-40">
                                    <flux:avatar size="sm" :src="$comment->user->avatarUrl()" :initials="$comment->user->initials()" class="shrink-0 mt-1" />
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-baseline gap-2">
                                            <span class="font-semibold text-sm text-zinc-400 dark:text-zinc-500">{{ $comment->user->name }}</span>
                                            <span class="text-xs text-zinc-400 dark:text-zinc-500">{{ $comment->created_at->diffForHumans() }}</span>
                                            <flux:badge size="sm" color="red">Deleted</flux:badge>
                                        </div>
                                        <div class="mt-1 prose prose-sm dark:prose-invert max-w-none text-zinc-400 dark:text-zinc-600 line-through">
                                            {!! Str::markdown($comment->body, ['html_input' => 'strip', 'allow_unsafe_links' => false]) !!}
                                        </div>
                                        <div class="mt-1 text-xs text-zinc-400 dark:text-zinc-600">
                                            Deleted by {{ $comment->deletedBy?->name ?? 'unknown' }} {{ $comment->deleted_at->diffForHumans() }}
                                        </div>
                                    </div>
                                </div>
                            @else
                                <div wire:key="comment-{{ $comment->id }}" class="flex gap-3">
                                    <flux:avatar size="sm" :src="$comment->user->avatarUrl()" :initials="$comment->user->initials()" class="shrink-0 mt-1" />
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-baseline gap-2">
                                            <span class="font-semibold text-sm text-zinc-900 dark:text-zinc-100">{{ $comment->user->name }}</span>
                                            <span class="text-xs text-zinc-400 dark:text-zinc-500">{{ $comment->created_at->diffForHumans() }}</span>
                                            @if($this->canFlagComment($comment))
                                                <flux:button wire:click="openFlagModal({{ $comment->id }})" variant="ghost" size="xs" class="!p-0.5" aria-label="Flag comment">
                                                    <flux:icon.flag class="size-3.5" />
                                                </flux:button>
                                            @endif
                                            @can('delete', $comment)
                                                <flux:button wire:click="deleteMessage({{ $comment->id }})" wire:confirm="Delete this comment? It will be hidden from regular users." variant="ghost" size="xs" class="!p-0.5 text-red-500 hover:text-red-700" aria-label="Delete comment">
                                                    <flux:icon.trash class="size-3.5" />
                                                </flux:button>
                                            @endcan
                                        </div>
                                        <div class="mt-1 prose prose-sm dark:prose-invert max-w-none">
                                            {!! Str::markdown($comment->body, ['html_input' => 'strip', 'allow_unsafe_links' => false]) !!}
                                        </div>
                                        @include('livewire.topics.partials.flag-display', ['message' => $comment, 'tz' => auth()->user()?->timezone ?? 'UTC'])
                                    </div>
                                </div>
                            @endif
                        @endforeach
                    </div>
                @else
                    <flux:text variant="subtle">No comments yet. Be the first to share your thoughts!</flux:text>
                @endif

                {{-- Comment Form --}}
                @if($this->canComment)
                    <div class="mt-8">
                        <form wire:submit="postComment">
                            <flux:field>
                                <flux:label>Leave a comment</flux:label>
                                <flux:textarea wire:model="commentBody" rows="3" placeholder="Share your thoughts..." />
                                <flux:error name="commentBody" />
                            </flux:field>
                            <div class="mt-3">
                                <flux:button type="submit" variant="primary" size="sm">Post Comment</flux:button>
                            </div>
                        </form>
                    </div>
                @elseif(! auth()->check())
                    <div class="mt-8 rounded-lg border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-900 p-4 text-center">
                        <flux:text variant="subtle">
                            <a href="{{ route('login') }}" class="text-blue-600 dark:text-blue-400 hover:underline">Log in</a> to leave a comment.
                        </flux:text>
                    </div>
                @endif
            </div>
        @endif
    </div>

    {{-- Flag Comment Modal --}}
    @auth
        <flux:modal name="flag-comment" class="space-y-6">
            <div>
                <flux:heading size="lg">Flag Comment</flux:heading>
                <flux:subheading>Why are you flagging this comment?</flux:subheading>
            </div>

            <flux:field>
                <flux:label>Reason <span class="text-red-500">*</span></flux:label>
                <flux:textarea wire:model="flagReason" rows="4" placeholder="Please explain why this comment should be reviewed by staff..." />
                <flux:error name="flagReason" />
            </flux:field>

            <div class="flex justify-end">
                <flux:button wire:click="submitFlag" variant="danger">Submit Flag</flux:button>
            </div>
        </flux:modal>
    @endauth

    {{-- Acknowledge Flag Modal (staff only) --}}
    @if($this->canViewFlagged)
        <flux:modal name="acknowledge-flag" class="space-y-6">
            <div>
                <flux:heading size="lg">Acknowledge Flag</flux:heading>
                <flux:subheading>Add notes about your review of this flag (optional)</flux:subheading>
            </div>

            <flux:field>
                <flux:label>Staff Notes</flux:label>
                <flux:textarea wire:model="staffNotes" rows="4" placeholder="Add any notes about your review of this flag..." />
            </flux:field>

            <div class="flex justify-end">
                <flux:button wire:click="acknowledgeFlag" variant="primary">Acknowledge Flag</flux:button>
            </div>
        </flux:modal>
    @endif
</div>
