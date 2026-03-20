<?php

use App\Enums\BlogPostStatus;
use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\BlogTag;
use App\Models\User;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    public ?string $categorySlug = null;
    public ?string $tagSlug = null;
    public ?string $authorSlug = null;

    public ?BlogCategory $filterCategory = null;
    public ?BlogTag $filterTag = null;
    public ?User $filterAuthor = null;

    public function mount(?string $categorySlug = null, ?string $tagSlug = null, ?string $authorSlug = null): void
    {
        $this->categorySlug = $categorySlug;
        $this->tagSlug = $tagSlug;
        $this->authorSlug = $authorSlug;

        if ($this->categorySlug) {
            $this->filterCategory = BlogCategory::where('slug', $this->categorySlug)->firstOrFail();
        }

        if ($this->tagSlug) {
            $this->filterTag = BlogTag::where('slug', $this->tagSlug)->firstOrFail();
        }

        if ($this->authorSlug) {
            $this->filterAuthor = User::where('slug', $this->authorSlug)->firstOrFail();
        }
    }

    public function with(): array
    {
        $query = BlogPost::with(['author', 'category', 'tags'])
            ->where('status', BlogPostStatus::Published)
            ->orderBy('published_at', 'desc');

        if ($this->filterCategory) {
            $query->where('category_id', $this->filterCategory->id);
        }

        if ($this->filterTag) {
            $query->whereHas('tags', fn ($q) => $q->where('blog_tags.id', $this->filterTag->id));
        }

        if ($this->filterAuthor) {
            $query->where('author_id', $this->filterAuthor->id);
        }

        return [
            'posts' => $query->paginate(12),
            'categories' => BlogCategory::withCount(['posts' => fn ($q) => $q->where('status', BlogPostStatus::Published)])->orderBy('name')->get(),
        ];
    }
}; ?>

<div class="mx-auto max-w-5xl px-4 py-8">
        <div class="mb-6 flex items-center justify-between">
            <div>
                <flux:heading size="xl">
                    @if($filterCategory)
                        Category: {{ $filterCategory->name }}
                    @elseif($filterTag)
                        Tag: {{ $filterTag->name }}
                    @elseif($filterAuthor)
                        Posts by {{ $filterAuthor->name }}
                    @else
                        Blog
                    @endif
                </flux:heading>
                @if($filterCategory || $filterTag || $filterAuthor)
                    <flux:link href="{{ route('blog.index') }}" class="mt-2 inline-block text-sm" wire:navigate>
                        &larr; Back to all posts
                    </flux:link>
                @endif
            </div>
            <div class="flex gap-2">
                @can('manage-blog')
                    <flux:button href="{{ route('blog.manage') }}" variant="ghost" size="sm" icon="cog-6-tooth" wire:navigate>
                        Manage
                    </flux:button>
                @endcan
                <flux:button href="{{ route('blog.rss') }}" variant="ghost" size="sm" icon="rss">
                    RSS
                </flux:button>
            </div>
        </div>

        <div class="grid gap-8 lg:grid-cols-4">
            {{-- Main content --}}
            <div class="lg:col-span-3">
                @forelse($posts as $post)
                    <article wire:key="blog-post-{{ $post->id }}" class="mb-8">
                        <flux:card>
                            @if($post->heroImageUrl())
                                <a href="{{ $post->url() }}" wire:navigate>
                                    <img src="{{ $post->heroImageUrl() }}" alt="{{ $post->title }}" class="mb-4 w-full rounded-lg object-cover" style="max-height: 300px;" />
                                </a>
                            @endif

                            <flux:heading size="lg">
                                <a href="{{ $post->url() }}" class="hover:underline" wire:navigate>
                                    {{ $post->title }}
                                </a>
                            </flux:heading>

                            <div class="mt-2 flex flex-wrap items-center gap-3 text-sm text-zinc-500 dark:text-zinc-400">
                                <span>
                                    By <a href="{{ route('blog.author', $post->author->slug) }}" class="hover:underline" wire:navigate>{{ $post->author->name }}</a>
                                </span>
                                <span>&middot;</span>
                                <span>{{ $post->published_at->format('M j, Y') }}</span>
                                @if($post->category)
                                    <span>&middot;</span>
                                    <a href="{{ route('blog.category', $post->category->slug) }}" wire:navigate>
                                        <flux:badge>{{ $post->category->name }}</flux:badge>
                                    </a>
                                @endif
                            </div>

                            @if($post->meta_description)
                                <flux:text class="mt-3">{{ $post->meta_description }}</flux:text>
                            @else
                                <flux:text class="mt-3">{{ Str::limit(strip_tags($post->body), 200) }}</flux:text>
                            @endif

                            @if($post->tags->count())
                                <div class="mt-3 flex flex-wrap gap-1">
                                    @foreach($post->tags as $tag)
                                        <a href="{{ route('blog.tag', $tag->slug) }}" wire:navigate>
                                            <flux:badge variant="outline" size="sm">{{ $tag->name }}</flux:badge>
                                        </a>
                                    @endforeach
                                </div>
                            @endif

                            <div class="mt-4">
                                <flux:link href="{{ $post->url() }}" wire:navigate>
                                    Read more &rarr;
                                </flux:link>
                            </div>
                        </flux:card>
                    </article>
                @empty
                    <flux:card>
                        <flux:text variant="subtle" class="text-center py-8">No blog posts found.</flux:text>
                    </flux:card>
                @endforelse

                <div class="mt-4">
                    {{ $posts->links() }}
                </div>
            </div>

            {{-- Sidebar --}}
            <div class="lg:col-span-1">
                <flux:card>
                    <flux:heading size="md" class="mb-3">Categories</flux:heading>
                    <div class="space-y-1">
                        @foreach($categories as $category)
                            <div>
                                <flux:link href="{{ route('blog.category', $category->slug) }}" wire:navigate class="text-sm">
                                    {{ $category->name }} ({{ $category->posts_count }})
                                </flux:link>
                            </div>
                        @endforeach
                    </div>
                </flux:card>
            </div>
        </div>
</div>
