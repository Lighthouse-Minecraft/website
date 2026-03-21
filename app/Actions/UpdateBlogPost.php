<?php

namespace App\Actions;

use App\Models\BlogPost;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class UpdateBlogPost
{
    use AsAction;

    public function handle(BlogPost $post, array $data): BlogPost
    {
        return DB::transaction(function () use ($post, $data) {
            $updateData = [];

            if (isset($data['title']) && $data['title'] !== $post->title) {
                $updateData['title'] = $data['title'];
                $updateData['slug'] = GenerateBlogPostSlug::run($data['title'], $post->id);
            }

            if (array_key_exists('body', $data)) {
                $updateData['body'] = $data['body'];
            }

            if (array_key_exists('hero_image_id', $data)) {
                $updateData['hero_image_id'] = $data['hero_image_id'];
            }

            if (array_key_exists('meta_description', $data)) {
                $updateData['meta_description'] = $data['meta_description'];
            }

            if (array_key_exists('og_image_id', $data)) {
                $updateData['og_image_id'] = $data['og_image_id'];
            }

            if (array_key_exists('category_id', $data)) {
                $updateData['category_id'] = $data['category_id'];
            }

            if (array_key_exists('community_question_id', $data)) {
                $updateData['community_question_id'] = $data['community_question_id'];
            }

            if ($post->isPublished() && ! empty($updateData)) {
                $updateData['is_edited'] = true;
            }

            $post->update($updateData);

            if (isset($data['tag_ids'])) {
                $post->tags()->sync($data['tag_ids']);
            }

            if (isset($data['community_response_ids'])) {
                $syncData = [];
                foreach ($data['community_response_ids'] as $index => $responseId) {
                    $syncData[$responseId] = ['sort_order' => $index];
                }
                $post->communityResponses()->sync($syncData);
            }

            SyncBlogPostImages::run($post);

            RecordActivity::run($post, 'blog_post_updated', "Blog post \"{$post->title}\" updated.");

            return $post->fresh();
        });
    }
}
