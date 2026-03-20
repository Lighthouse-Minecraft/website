<?php

use App\Enums\BlogPostStatus;
use App\Models\BlogCategory;
use App\Models\BlogPost;
use Illuminate\Support\Str;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    public BlogCategory $category;

    public function mount(string $categorySlug): void
    {
        $this->category = BlogCategory::where('slug', $categorySlug)->firstOrFail();
    }

    public function with(): array
    {
        $posts = BlogPost::with(['author', 'category', 'tags'])
            ->where('status', BlogPostStatus::Published)
            ->where('category_id', $this->category->id)
            ->orderBy('published_at', 'desc')
            ->paginate(12);

        return [
            'posts' => $posts,
        ];
    }
}; ?>

<div class="mx-auto max-w-5xl px-4 py-8">
    <div class="mb-6">
        <flux:link href="{{ route('blog.index') }}" wire:navigate>
            &larr; Back to Blog
        </flux:link>
    </div>

    @if($category->heroImageUrl())
        <img src="{{ $category->heroImageUrl() }}" alt="{{ $category->name }}" class="mb-6 w-full rounded-lg object-cover" style="max-height: 300px;" />
    @endif

    <flux:heading size="xl" class="mb-4">{{ $category->name }}</flux:heading>

    @if($category->content)
        <div class="prose mb-8 max-w-none dark:prose-invert">
            {!! Str::markdown($category->content, ['html_input' => 'strip', 'allow_unsafe_links' => false]) !!}
        </div>
    @endif

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
            <flux:text variant="subtle" class="text-center py-8">No posts in this category yet.</flux:text>
        </flux:card>
    @endforelse

    <div class="mt-4">
        {{ $posts->links() }}
    </div>
</div>
