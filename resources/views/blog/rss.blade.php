{!! '<?xml version="1.0" encoding="UTF-8"?>' !!}
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">
    <channel>
        <title>{{ config('app.name') }} Blog</title>
        <link>{{ route('blog.index') }}</link>
        <description>Latest blog posts from {{ config('app.name') }}</description>
        <language>en-us</language>
        <atom:link href="{{ route('blog.rss') }}" rel="self" type="application/rss+xml" />
        @if($posts->count())
            <lastBuildDate>{{ $posts->first()->published_at->toRfc2822String() }}</lastBuildDate>
        @endif
        @foreach($posts as $post)
            <item>
                <title>{{ htmlspecialchars($post->title, ENT_XML1, 'UTF-8') }}</title>
                <link>{{ route('blog.show', $post->slug) }}</link>
                <guid isPermaLink="true">{{ route('blog.show', $post->slug) }}</guid>
                <pubDate>{{ $post->published_at->toRfc2822String() }}</pubDate>
                <author>{{ htmlspecialchars($post->author->name, ENT_XML1, 'UTF-8') }}</author>
                @if($post->category)
                    <category>{{ htmlspecialchars($post->category->name, ENT_XML1, 'UTF-8') }}</category>
                @endif
                <description>{{ htmlspecialchars($post->meta_description ?: Str::limit(strip_tags($post->body), 300), ENT_XML1, 'UTF-8') }}</description>
            </item>
        @endforeach
    </channel>
</rss>
