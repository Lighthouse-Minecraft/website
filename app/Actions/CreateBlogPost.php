<?php

namespace App\Actions;

use App\Enums\BlogPostStatus;
use App\Models\BlogPost;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class CreateBlogPost
{
    use AsAction;

    public function handle(User $author, array $data): BlogPost
    {
        return DB::transaction(function () use ($author, $data) {
            $slug = GenerateBlogPostSlug::run($data['title']);

            $post = BlogPost::create([
                'title' => $data['title'],
                'slug' => $slug,
                'body' => $data['body'],
                'hero_image_id' => $data['hero_image_id'] ?? null,
                'meta_description' => $data['meta_description'] ?? null,
                'og_image_id' => $data['og_image_id'] ?? null,
                'status' => BlogPostStatus::Draft,
                'author_id' => $author->id,
                'category_id' => $data['category_id'] ?? null,
                'community_question_id' => $data['community_question_id'] ?? null,
            ]);

            if (! empty($data['tag_ids'])) {
                $post->tags()->sync($data['tag_ids']);
            }

            if (! empty($data['community_response_ids'])) {
                $syncData = [];
                foreach ($data['community_response_ids'] as $index => $responseId) {
                    $syncData[$responseId] = ['sort_order' => $index];
                }
                $post->communityResponses()->sync($syncData);
            }

            SyncBlogPostImages::run($post);

            RecordActivity::run($post, 'blog_post_created', "Blog post \"{$post->title}\" created by {$author->name}.");

            return $post;
        });
    }
}
