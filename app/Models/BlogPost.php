<?php

namespace App\Models;

use App\Enums\BlogPostStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class BlogPost extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'title',
        'slug',
        'body',
        'hero_image_path',
        'meta_description',
        'og_image_path',
        'status',
        'scheduled_at',
        'published_at',
        'author_id',
        'category_id',
        'community_question_id',
        'is_edited',
    ];

    protected function casts(): array
    {
        return [
            'status' => BlogPostStatus::class,
            'scheduled_at' => 'datetime',
            'published_at' => 'datetime',
            'is_edited' => 'boolean',
        ];
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(BlogCategory::class, 'category_id');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(BlogTag::class, 'blog_post_tag');
    }

    public function communityQuestion(): BelongsTo
    {
        return $this->belongsTo(CommunityQuestion::class, 'community_question_id');
    }

    public function communityResponses(): BelongsToMany
    {
        return $this->belongsToMany(CommunityResponse::class, 'blog_post_community_response')
            ->withPivot('sort_order')
            ->orderBy('sort_order');
    }

    public function renderBody(): string
    {
        $html = Str::markdown($this->body, [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);

        $html = preg_replace_callback('/\{\{story:(\d+)\}\}/', function ($matches) {
            $responseId = (int) $matches[1];
            $response = CommunityResponse::with('user')->find($responseId);

            if (! $response) {
                return '';
            }

            $name = e($response->user->name);
            $avatarUrl = $response->user->avatarUrl();
            $content = e($response->body);

            $avatarHtml = $avatarUrl
                ? '<img src="'.e($avatarUrl).'" alt="'.$name.'" class="h-10 w-10 rounded-full object-cover" />'
                : '<div class="flex h-10 w-10 items-center justify-center rounded-full bg-zinc-300 text-sm font-bold text-zinc-700 dark:bg-zinc-600 dark:text-zinc-200">'.mb_substr($name, 0, 1).'</div>';

            return '<blockquote class="not-prose my-6 rounded-lg border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-700 dark:bg-zinc-800">'
                .'<div class="mb-2 flex items-center gap-3">'
                .$avatarHtml
                .'<span class="font-semibold text-zinc-900 dark:text-zinc-100">'.$name.'</span>'
                .'</div>'
                .'<p class="text-zinc-700 dark:text-zinc-300">'.nl2br($content).'</p>'
                .'</blockquote>';
        }, $html);

        return $html;
    }

    public function isDraft(): bool
    {
        return $this->status === BlogPostStatus::Draft;
    }

    public function isPublished(): bool
    {
        return $this->status === BlogPostStatus::Published;
    }

    public function heroImageUrl(): ?string
    {
        if (! $this->hero_image_path) {
            return null;
        }

        return \App\Services\StorageService::publicUrl($this->hero_image_path);
    }

    public function ogImageUrl(): ?string
    {
        if (! $this->og_image_path) {
            return null;
        }

        return \App\Services\StorageService::publicUrl($this->og_image_path);
    }
}
