<?php

namespace App\Http\Controllers;

use App\Enums\BlogPostStatus;
use App\Models\BlogPost;
use Illuminate\Http\Response;

class BlogRssController extends Controller
{
    public function __invoke(): Response
    {
        $posts = BlogPost::with(['author', 'category'])
            ->where('status', BlogPostStatus::Published)
            ->orderBy('published_at', 'desc')
            ->limit(50)
            ->get();

        $content = view('blog.rss', ['posts' => $posts])->render();

        return response($content, 200, [
            'Content-Type' => 'application/rss+xml; charset=UTF-8',
        ]);
    }
}
