<?php

namespace App\Http\Controllers;

use App\Enums\BlogPostStatus;
use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\BlogTag;
use Illuminate\Http\Response;

class BlogSitemapController extends Controller
{
    public function __invoke(): Response
    {
        $posts = BlogPost::with('category')
            ->where('status', BlogPostStatus::Published)
            ->orderBy('published_at', 'desc')
            ->get();

        $categories = BlogCategory::where('include_in_sitemap', true)->get();

        $tags = BlogTag::where('include_in_sitemap', true)->get();

        $content = view('blog.sitemap', [
            'posts' => $posts,
            'categories' => $categories,
            'tags' => $tags,
        ])->render();

        return response($content, 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
        ]);
    }
}
