{!! '<?xml version="1.0" encoding="UTF-8"?>' !!}
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
    <url>
        <loc>{{ route('blog.index') }}</loc>
        <changefreq>daily</changefreq>
        <priority>0.8</priority>
    </url>
    @foreach($posts as $post)
        <url>
            <loc>{{ $post->url() }}</loc>
            <lastmod>{{ $post->updated_at->toW3cString() }}</lastmod>
            <changefreq>weekly</changefreq>
            <priority>0.7</priority>
        </url>
    @endforeach
    @foreach($categories as $category)
        <url>
            <loc>{{ route('blog.category', $category->slug) }}</loc>
            <changefreq>weekly</changefreq>
            <priority>0.5</priority>
        </url>
    @endforeach
    @foreach($tags as $tag)
        <url>
            <loc>{{ route('blog.tag', $tag->slug) }}</loc>
            <changefreq>weekly</changefreq>
            <priority>0.4</priority>
        </url>
    @endforeach
</urlset>
