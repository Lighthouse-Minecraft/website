<?php

use App\Enums\BlogPostStatus;
use App\Models\BlogPost;
use Illuminate\Support\Str;
use Livewire\Volt\Component;

new class extends Component {
    public BlogPost $post;
    public bool $isTrashed = false;
    public string $renderedBody = '';

    public function mount(string $slug): void
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

    public function with(): array
    {
        $jsonLd = null;
        $canonicalUrl = route('blog.show', $this->post->slug);

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

<x-layouts.app>
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
        @endif
    </div>
</x-layouts.app>
