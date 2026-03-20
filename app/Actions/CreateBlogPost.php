<?php

namespace App\Actions;

use App\Enums\BlogPostStatus;
use App\Models\BlogPost;
use App\Models\User;
use Lorisleiva\Actions\Concerns\AsAction;

class CreateBlogPost
{
    use AsAction;

    public function handle(User $author, array $data): BlogPost
    {
        $slug = GenerateBlogPostSlug::run($data['title']);

        $post = BlogPost::create([
            'title' => $data['title'],
            'slug' => $slug,
            'body' => $data['body'],
            'hero_image_path' => $data['hero_image_path'] ?? null,
            'meta_description' => $data['meta_description'] ?? null,
            'og_image_path' => $data['og_image_path'] ?? null,
            'status' => BlogPostStatus::Draft,
            'author_id' => $author->id,
            'category_id' => $data['category_id'] ?? null,
        ]);

        if (! empty($data['tag_ids'])) {
            $post->tags()->sync($data['tag_ids']);
        }

        RecordActivity::run($post, 'blog_post_created', "Blog post \"{$post->title}\" created by {$author->name}.");

        return $post;
    }
}
