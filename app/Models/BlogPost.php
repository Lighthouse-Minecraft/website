<?php

namespace App\Models;

use App\Enums\BlogPostStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class BlogPost extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'title',
        'slug',
        'body',
        'hero_image_id',
        'meta_description',
        'og_image_id',
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

    public function commentThread(): MorphOne
    {
        return $this->morphOne(Thread::class, 'topicable');
    }

    public function heroImage(): BelongsTo
    {
        return $this->belongsTo(BlogImage::class, 'hero_image_id');
    }

    public function ogImage(): BelongsTo
    {
        return $this->belongsTo(BlogImage::class, 'og_image_id');
    }

    public function images(): BelongsToMany
    {
        return $this->belongsToMany(BlogImage::class, 'blog_image_post')
            ->withPivot('created_at');
    }

    public function communityResponses(): BelongsToMany
    {
        return $this->belongsToMany(CommunityResponse::class, 'blog_post_community_response')
            ->withPivot('sort_order')
            ->orderBy('sort_order');
    }

    public function url(): string
    {
        return route('blog.show', [$this->category->slug ?? 'uncategorized', $this->slug]);
    }

    public function renderBody(): string
    {
        $html = Str::markdown($this->body, [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);

        // Batch-load all referenced images to avoid N+1
        preg_match_all('/\{\{image:(\d+)(?:\|[^}]*)?\}\}/', $html, $allMatches);
        $imageMap = ! empty($allMatches[1])
            ? BlogImage::whereIn('id', array_unique($allMatches[1]))->get()->keyBy('id')
            : collect();

        $html = preg_replace_callback('/\{\{image:(\d+)(?:\|([^}]+))?\}\}/', function ($matches) use ($imageMap) {
            $image = $imageMap->get((int) $matches[1]);

            if (! $image) {
                return '';
            }

            $altText = isset($matches[2]) ? e(trim($matches[2])) : e($image->alt_text);
            $url = e($image->url());

            return '<img src="'.$url.'" alt="'.$altText.'" class="rounded-lg" />';
        }, $html);

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

    public function renderPreview(int $length = 200): string
    {
        $body = $this->body;

        // Truncate safely: never cut inside a {{image:ID}} or {{story:ID}} tag
        if (mb_strlen($body) > $length) {
            $truncated = mb_substr($body, 0, $length);

            // If we're inside an opening {{ but haven't hit }}, find the tag boundary
            $lastOpen = mb_strrpos($truncated, '{{');
            $lastClose = mb_strrpos($truncated, '}}');

            if ($lastOpen !== false && ($lastClose === false || $lastClose < $lastOpen)) {
                // We cut inside a tag — find the closing }} in the full body
                $closingPos = mb_strpos($body, '}}', $lastOpen);
                if ($closingPos !== false) {
                    $truncated = mb_substr($body, 0, $closingPos + 2);
                } else {
                    // Malformed tag — truncate before it
                    $truncated = mb_substr($body, 0, $lastOpen);
                }
            }

            $body = rtrim($truncated).'...';
        }

        // Strip story tags from preview
        $body = preg_replace('/\{\{story:\d+\}\}/', '', $body);

        $html = Str::markdown($body, [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);

        // Batch-load all referenced images to avoid N+1
        preg_match_all('/\{\{image:(\d+)(?:\|[^}]*)?\}\}/', $html, $allMatches);
        $imageMap = ! empty($allMatches[1])
            ? BlogImage::whereIn('id', array_unique($allMatches[1]))->get()->keyBy('id')
            : collect();

        // Render image tags as thumbnails
        $html = preg_replace_callback('/\{\{image:(\d+)(?:\|([^}]+))?\}\}/', function ($matches) use ($imageMap) {
            $image = $imageMap->get((int) $matches[1]);

            if (! $image) {
                return '';
            }

            $altText = isset($matches[2]) ? e(trim($matches[2])) : e($image->alt_text);
            $url = e($image->url());

            return '<img src="'.$url.'" alt="'.$altText.'" class="inline-block h-16 w-16 rounded object-cover" />';
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
        if (! $this->hero_image_id) {
            return null;
        }

        return $this->heroImage?->url();
    }

    public function ogImageUrl(): ?string
    {
        if (! $this->og_image_id) {
            return null;
        }

        return $this->ogImage?->url();
    }
}
