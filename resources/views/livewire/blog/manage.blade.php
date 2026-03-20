<?php

use App\Actions\ApproveBlogPost;
use App\Actions\ArchiveBlogPost;
use App\Actions\CreateBlogPost;
use App\Actions\DeleteBlogPost;
use App\Actions\SubmitBlogPostForReview;
use App\Actions\UpdateBlogPost;
use App\Enums\BlogPostStatus;
use App\Enums\CommunityResponseStatus;
use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\BlogTag;
use App\Models\CommunityQuestion;
use App\Models\CommunityResponse;
use App\Models\SiteConfig;
use Carbon\Carbon;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

new class extends Component {
    use WithFileUploads, WithPagination;

    // Filters
    public string $statusFilter = '';
    public string $search = '';

    // Post form
    public ?int $editingPostId = null;
    public string $postTitle = '';
    public string $postBody = '';
    public ?int $postCategoryId = null;
    public array $postTagIds = [];
    public string $postMetaDescription = '';
    public $heroImage = null;
    public $ogImage = null;
    public ?string $existingHeroImageUrl = null;
    public ?string $existingOgImageUrl = null;

    // Category management
    public ?int $editingCategoryId = null;
    public string $categoryName = '';
    public string $categorySlug = '';
    public bool $categoryIncludeInSitemap = true;

    // Tag management
    public string $newTagName = '';

    // Community story integration
    public ?int $postCommunityQuestionId = null;
    public array $postCommunityResponseIds = [];

    // Preview
    public string $previewHtml = '';

    // Approval modal
    public ?int $approvingPostId = null;
    public string $approveScheduledAt = '';
    public bool $approvePublishImmediately = true;

    public function mount(): void
    {
        $this->authorize('manage-blog');
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function openCreatePostModal(): void
    {
        $this->authorize('create', BlogPost::class);
        $this->resetPostForm();
        Flux::modal('post-form-modal')->show();
    }

    public function openEditPostModal(int $postId): void
    {
        $post = BlogPost::with(['tags', 'communityResponses'])->findOrFail($postId);
        $this->authorize('update', $post);

        $this->editingPostId = $post->id;
        $this->postTitle = $post->title;
        $this->postBody = $post->body;
        $this->postCategoryId = $post->category_id;
        $this->postTagIds = $post->tags->pluck('id')->toArray();
        $this->postMetaDescription = $post->meta_description ?? '';
        $this->postCommunityQuestionId = $post->community_question_id;
        $this->postCommunityResponseIds = $post->communityResponses->pluck('id')->toArray();
        $this->heroImage = null;
        $this->ogImage = null;
        $this->existingHeroImageUrl = $post->heroImageUrl();
        $this->existingOgImageUrl = $post->ogImageUrl();

        Flux::modal('post-form-modal')->show();
    }

    public function savePost(): void
    {
        $this->validate([
            'postTitle' => 'required|string|max:255',
            'postBody' => 'required|string|min:10',
            'postCategoryId' => 'nullable|exists:blog_categories,id',
            'postMetaDescription' => 'nullable|string|max:160',
            'heroImage' => 'nullable|mimes:jpg,jpeg,png,gif,webp|max:' . SiteConfig::getValue('max_image_size_kb', '2048'),
            'ogImage' => 'nullable|mimes:jpg,jpeg,png,gif,webp|max:' . SiteConfig::getValue('max_image_size_kb', '2048'),
        ]);

        $heroImagePath = null;
        $ogImagePath = null;

        if ($this->heroImage) {
            $heroImagePath = $this->heroImage->store('blog/hero', config('filesystems.public_disk'));
        }

        if ($this->ogImage) {
            $ogImagePath = $this->ogImage->store('blog/og', config('filesystems.public_disk'));
        }

        $data = [
            'title' => $this->postTitle,
            'body' => $this->postBody,
            'category_id' => $this->postCategoryId,
            'tag_ids' => $this->postTagIds,
            'meta_description' => $this->postMetaDescription ?: null,
            'community_question_id' => $this->postCommunityQuestionId ?: null,
            'community_response_ids' => $this->postCommunityResponseIds,
        ];

        if ($this->editingPostId) {
            $post = BlogPost::findOrFail($this->editingPostId);
            $this->authorize('update', $post);

            if ($heroImagePath) {
                if ($post->hero_image_path) {
                    Storage::disk(config('filesystems.public_disk'))->delete($post->hero_image_path);
                }
                $data['hero_image_path'] = $heroImagePath;
            }

            if ($ogImagePath) {
                if ($post->og_image_path) {
                    Storage::disk(config('filesystems.public_disk'))->delete($post->og_image_path);
                }
                $data['og_image_path'] = $ogImagePath;
            }

            UpdateBlogPost::run($post, $data);
            Flux::toast('Post updated successfully.', 'Updated', variant: 'success');
        } else {
            $this->authorize('create', BlogPost::class);

            $data['hero_image_path'] = $heroImagePath;
            $data['og_image_path'] = $ogImagePath;

            CreateBlogPost::run(Auth::user(), $data);
            Flux::toast('Post created successfully.', 'Created', variant: 'success');
        }

        Flux::modal('post-form-modal')->close();
        $this->resetPostForm();
    }

    public function deletePost(int $postId): void
    {
        $post = BlogPost::findOrFail($postId);
        $this->authorize('delete', $post);

        DeleteBlogPost::run($post);
        Flux::toast('Post deleted successfully.', 'Deleted', variant: 'success');
    }

    public function openPreviewModal(): void
    {
        $tempPost = new BlogPost(['body' => $this->postBody]);
        $this->previewHtml = $tempPost->renderBody();
        Flux::modal('preview-modal')->show();
    }

    // Status transitions

    public function submitForReview(int $postId): void
    {
        $post = BlogPost::findOrFail($postId);
        $this->authorize('submitForReview', $post);

        SubmitBlogPostForReview::run($post, Auth::user());
        Flux::toast('Post submitted for review.', 'Submitted', variant: 'success');
    }

    public function openApproveModal(int $postId): void
    {
        $post = BlogPost::findOrFail($postId);
        $this->authorize('approve', $post);

        $this->approvingPostId = $post->id;
        $this->approvePublishImmediately = true;
        $this->approveScheduledAt = '';
        Flux::modal('approve-modal')->show();
    }

    public function approvePost(): void
    {
        $post = BlogPost::findOrFail($this->approvingPostId);
        $this->authorize('approve', $post);

        $scheduledAt = null;

        if (! $this->approvePublishImmediately) {
            $this->validate([
                'approveScheduledAt' => 'required|date|after:now',
            ]);

            $userTimezone = Auth::user()->timezone ?? 'UTC';
            $scheduledAt = Carbon::parse($this->approveScheduledAt, $userTimezone)->utc();
        }

        ApproveBlogPost::run($post, Auth::user(), $scheduledAt);

        Flux::modal('approve-modal')->close();
        $this->approvingPostId = null;

        $message = $scheduledAt ? 'Post approved and scheduled.' : 'Post approved and published.';
        Flux::toast($message, 'Approved', variant: 'success');
    }

    public function archivePost(int $postId): void
    {
        $post = BlogPost::findOrFail($postId);
        $this->authorize('archive', $post);

        ArchiveBlogPost::run($post);
        Flux::toast('Post archived.', 'Archived', variant: 'success');
    }

    // Category management

    public function openCreateCategoryModal(): void
    {
        $this->authorize('manage-blog');
        $this->resetCategoryForm();
        Flux::modal('category-form-modal')->show();
    }

    public function openEditCategoryModal(int $categoryId): void
    {
        $this->authorize('manage-blog');
        $category = BlogCategory::findOrFail($categoryId);
        $this->editingCategoryId = $category->id;
        $this->categoryName = $category->name;
        $this->categorySlug = $category->slug;
        $this->categoryIncludeInSitemap = $category->include_in_sitemap;
        Flux::modal('category-form-modal')->show();
    }

    public function saveCategory(): void
    {
        $this->authorize('manage-blog');

        $rules = [
            'categoryName' => 'required|string|max:100',
            'categorySlug' => 'required|string|max:100',
            'categoryIncludeInSitemap' => 'boolean',
        ];

        if ($this->editingCategoryId) {
            $rules['categorySlug'] .= '|unique:blog_categories,slug,' . $this->editingCategoryId;
        } else {
            $rules['categorySlug'] .= '|unique:blog_categories,slug';
        }

        $this->validate($rules);

        if ($this->editingCategoryId) {
            $category = BlogCategory::findOrFail($this->editingCategoryId);
            $category->update([
                'name' => $this->categoryName,
                'slug' => Str::slug($this->categorySlug),
                'include_in_sitemap' => $this->categoryIncludeInSitemap,
            ]);
            Flux::toast('Category updated.', 'Updated', variant: 'success');
        } else {
            BlogCategory::create([
                'name' => $this->categoryName,
                'slug' => Str::slug($this->categorySlug),
                'include_in_sitemap' => $this->categoryIncludeInSitemap,
            ]);
            Flux::toast('Category created.', 'Created', variant: 'success');
        }

        Flux::modal('category-form-modal')->close();
        $this->resetCategoryForm();
    }

    public function deleteCategory(int $categoryId): void
    {
        $this->authorize('manage-blog');
        $category = BlogCategory::findOrFail($categoryId);

        if ($category->posts()->exists()) {
            Flux::toast('Cannot delete a category that has posts.', 'Error', variant: 'danger');

            return;
        }

        $category->delete();
        Flux::toast('Category deleted.', 'Deleted', variant: 'success');
    }

    public function updatedCategoryName(): void
    {
        if (! $this->editingCategoryId) {
            $this->categorySlug = Str::slug($this->categoryName);
        }
    }

    // Tag management

    public function createTag(): void
    {
        $this->authorize('manage-blog');

        $this->validate([
            'newTagName' => 'required|string|max:50',
        ]);

        $slug = Str::slug($this->newTagName);

        if (BlogTag::where('slug', $slug)->exists()) {
            Flux::toast('A tag with that name already exists.', 'Error', variant: 'danger');

            return;
        }

        BlogTag::create([
            'name' => $this->newTagName,
            'slug' => $slug,
        ]);

        $this->newTagName = '';
        Flux::toast('Tag created.', 'Created', variant: 'success');
    }

    public function deleteTag(int $tagId): void
    {
        $this->authorize('manage-blog');
        $tag = BlogTag::findOrFail($tagId);
        $tag->posts()->detach();
        $tag->delete();
        Flux::toast('Tag deleted.', 'Deleted', variant: 'success');
    }

    // Helpers

    protected function resetPostForm(): void
    {
        $this->editingPostId = null;
        $this->postTitle = '';
        $this->postBody = '';
        $this->postCategoryId = null;
        $this->postTagIds = [];
        $this->postMetaDescription = '';
        $this->postCommunityQuestionId = null;
        $this->postCommunityResponseIds = [];
        $this->heroImage = null;
        $this->ogImage = null;
        $this->existingHeroImageUrl = null;
        $this->existingOgImageUrl = null;
    }

    protected function resetCategoryForm(): void
    {
        $this->editingCategoryId = null;
        $this->categoryName = '';
        $this->categorySlug = '';
        $this->categoryIncludeInSitemap = true;
    }

    public function updatedPostCommunityQuestionId(): void
    {
        $this->postCommunityResponseIds = [];
    }

    public function removeHeroImage(): void
    {
        $this->heroImage = null;
    }

    public function removeOgImage(): void
    {
        $this->ogImage = null;
    }

    public function with(): array
    {
        $query = BlogPost::with(['author', 'category', 'tags'])
            ->orderBy('created_at', 'desc');

        if ($this->statusFilter) {
            $query->where('status', $this->statusFilter);
        }

        if ($this->search) {
            $query->where('title', 'like', '%' . $this->search . '%');
        }

        $communityQuestions = CommunityQuestion::orderBy('question_text')->get();
        $availableResponses = collect();

        if ($this->postCommunityQuestionId) {
            $availableResponses = CommunityResponse::with('user')
                ->where('community_question_id', $this->postCommunityQuestionId)
                ->where('status', CommunityResponseStatus::Approved)
                ->orderBy('created_at')
                ->get();
        }

        return [
            'posts' => $query->paginate(15),
            'categories' => BlogCategory::orderBy('name')->get(),
            'tags' => BlogTag::orderBy('name')->get(),
            'statuses' => BlogPostStatus::cases(),
            'communityQuestions' => $communityQuestions,
            'availableResponses' => $availableResponses,
            'maxImageSizeLabel' => ((int) SiteConfig::getValue('max_image_size_kb', '2048')) >= 1024
                ? round((int) SiteConfig::getValue('max_image_size_kb', '2048') / 1024) . 'MB'
                : SiteConfig::getValue('max_image_size_kb', '2048') . 'KB',
        ];
    }
}; ?>

<x-layouts.app>
    <div class="mb-6 flex items-center justify-between">
        <flux:heading size="xl">Blog Management</flux:heading>
        <div class="flex gap-2">
            <flux:button wire:click="openCreatePostModal" variant="primary" icon="plus">
                New Post
            </flux:button>
            <flux:button wire:click="$set('activeTab', 'categories')" variant="ghost" icon="tag">
                Categories & Tags
            </flux:button>
        </div>
    </div>

    @php $activeTab = $activeTab ?? 'posts'; @endphp

    <div x-data="{ tab: @entangle('activeTab').live ?? 'posts' }">
        <div class="mb-4 flex gap-2">
            <flux:button variant="ghost" x-on:click="tab = 'posts'" x-bind:class="tab === 'posts' ? 'font-bold' : ''">
                Posts
            </flux:button>
            <flux:button variant="ghost" x-on:click="tab = 'categories'" x-bind:class="tab === 'categories' ? 'font-bold' : ''">
                Categories & Tags
            </flux:button>
        </div>

        {{-- Posts Tab --}}
        <div x-show="tab === 'posts'" x-cloak>
            <flux:card>
                <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center">
                    <flux:input wire:model.live.debounce.300ms="search" type="text" placeholder="Search posts..." class="sm:max-w-xs" icon="magnifying-glass" />
                    <flux:select wire:model.live="statusFilter" class="sm:max-w-xs">
                        <option value="">All Statuses</option>
                        @foreach($statuses as $status)
                            <option value="{{ $status->value }}">{{ $status->label() }}</option>
                        @endforeach
                    </flux:select>
                </div>

                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>Title</flux:table.column>
                        <flux:table.column>Author</flux:table.column>
                        <flux:table.column>Category</flux:table.column>
                        <flux:table.column>Status</flux:table.column>
                        <flux:table.column>Scheduled</flux:table.column>
                        <flux:table.column>Created</flux:table.column>
                        <flux:table.column>Actions</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @forelse($posts as $post)
                            <flux:table.row wire:key="post-{{ $post->id }}">
                                <flux:table.cell>
                                    {{ $post->title }}
                                    @if($post->is_edited)
                                        <flux:badge variant="warning" size="sm">Edited</flux:badge>
                                    @endif
                                </flux:table.cell>
                                <flux:table.cell>{{ $post->author->name }}</flux:table.cell>
                                <flux:table.cell>{{ $post->category?->name ?? '—' }}</flux:table.cell>
                                <flux:table.cell>
                                    <flux:badge :variant="$post->status->color()">{{ $post->status->label() }}</flux:badge>
                                </flux:table.cell>
                                <flux:table.cell>{{ $post->scheduled_at?->format('M j, Y g:ia') ?? '—' }}</flux:table.cell>
                                <flux:table.cell>{{ $post->created_at->format('M j, Y') }}</flux:table.cell>
                                <flux:table.cell>
                                    <div class="flex flex-wrap gap-1">
                                        @can('update', $post)
                                            <flux:button wire:click="openEditPostModal({{ $post->id }})" variant="ghost" size="sm" icon="pencil-square">
                                                Edit
                                            </flux:button>
                                        @endcan
                                        @can('submitForReview', $post)
                                            <flux:button wire:click="submitForReview({{ $post->id }})" wire:confirm="Submit this post for review?" variant="ghost" size="sm" icon="paper-airplane">
                                                Submit
                                            </flux:button>
                                        @endcan
                                        @can('approve', $post)
                                            <flux:button wire:click="openApproveModal({{ $post->id }})" variant="ghost" size="sm" icon="check-circle">
                                                Approve
                                            </flux:button>
                                        @endcan
                                        @can('archive', $post)
                                            <flux:button wire:click="archivePost({{ $post->id }})" wire:confirm="Archive this post?" variant="ghost" size="sm" icon="archive-box">
                                                Archive
                                            </flux:button>
                                        @endcan
                                        @can('delete', $post)
                                            <flux:button wire:click="deletePost({{ $post->id }})" wire:confirm="Are you sure you want to delete this post?" variant="ghost" size="sm" icon="trash">
                                                Delete
                                            </flux:button>
                                        @endcan
                                    </div>
                                </flux:table.cell>
                            </flux:table.row>
                        @empty
                            <flux:table.row>
                                <flux:table.cell colspan="7">
                                    <flux:text variant="subtle" class="text-center">No blog posts found.</flux:text>
                                </flux:table.cell>
                            </flux:table.row>
                        @endforelse
                    </flux:table.rows>
                </flux:table>

                <div class="mt-4">
                    {{ $posts->links() }}
                </div>
            </flux:card>
        </div>

        {{-- Categories & Tags Tab --}}
        <div x-show="tab === 'categories'" x-cloak>
            <div class="grid gap-6 lg:grid-cols-2">
                {{-- Categories --}}
                <flux:card>
                    <div class="mb-4 flex items-center justify-between">
                        <flux:heading size="md">Categories</flux:heading>
                        <flux:button wire:click="openCreateCategoryModal" variant="primary" size="sm" icon="plus">
                            New Category
                        </flux:button>
                    </div>

                    <flux:table>
                        <flux:table.columns>
                            <flux:table.column>Name</flux:table.column>
                            <flux:table.column>Slug</flux:table.column>
                            <flux:table.column>Sitemap</flux:table.column>
                            <flux:table.column>Actions</flux:table.column>
                        </flux:table.columns>
                        <flux:table.rows>
                            @forelse($categories as $category)
                                <flux:table.row wire:key="cat-{{ $category->id }}">
                                    <flux:table.cell>{{ $category->name }}</flux:table.cell>
                                    <flux:table.cell>{{ $category->slug }}</flux:table.cell>
                                    <flux:table.cell>
                                        @if($category->include_in_sitemap)
                                            <flux:badge variant="success">Yes</flux:badge>
                                        @else
                                            <flux:badge variant="zinc">No</flux:badge>
                                        @endif
                                    </flux:table.cell>
                                    <flux:table.cell>
                                        <div class="flex gap-1">
                                            <flux:button wire:click="openEditCategoryModal({{ $category->id }})" variant="ghost" size="sm" icon="pencil-square" />
                                            <flux:button wire:click="deleteCategory({{ $category->id }})" wire:confirm="Are you sure?" variant="ghost" size="sm" icon="trash" />
                                        </div>
                                    </flux:table.cell>
                                </flux:table.row>
                            @empty
                                <flux:table.row>
                                    <flux:table.cell colspan="4">
                                        <flux:text variant="subtle" class="text-center">No categories yet.</flux:text>
                                    </flux:table.cell>
                                </flux:table.row>
                            @endforelse
                        </flux:table.rows>
                    </flux:table>
                </flux:card>

                {{-- Tags --}}
                <flux:card>
                    <div class="mb-4 flex items-center justify-between">
                        <flux:heading size="md">Tags</flux:heading>
                    </div>

                    <div class="mb-4 flex gap-2">
                        <flux:input wire:model="newTagName" type="text" placeholder="New tag name..." class="flex-1" />
                        <flux:button wire:click="createTag" variant="primary" size="sm" icon="plus">
                            Add
                        </flux:button>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        @forelse($tags as $tag)
                            <div wire:key="tag-{{ $tag->id }}" class="flex items-center gap-1">
                                <flux:badge>{{ $tag->name }}</flux:badge>
                                <flux:button wire:click="deleteTag({{ $tag->id }})" wire:confirm="Delete this tag?" variant="ghost" size="sm" icon="x-mark" />
                            </div>
                        @empty
                            <flux:text variant="subtle">No tags yet.</flux:text>
                        @endforelse
                    </div>
                </flux:card>
            </div>
        </div>
    </div>

    {{-- Post Create/Edit Modal --}}
    <flux:modal name="post-form-modal" class="w-full lg:w-3/4 max-h-[90vh] overflow-y-auto">
        <div class="space-y-4">
            <flux:heading size="lg">{{ $editingPostId ? 'Edit Post' : 'Create Post' }}</flux:heading>

            <flux:field>
                <flux:label>Title <span class="text-red-500">*</span></flux:label>
                <flux:input wire:model="postTitle" type="text" placeholder="Post title..." />
                <flux:error name="postTitle" />
            </flux:field>

            <flux:field>
                <flux:label>Body (Markdown) <span class="text-red-500">*</span></flux:label>
                <flux:description>Write your post content in Markdown format.</flux:description>
                <flux:textarea wire:model="postBody" rows="12" placeholder="Write your post in markdown..." />
                <flux:error name="postBody" />
            </flux:field>

            <div class="flex gap-2">
                <flux:button wire:click="openPreviewModal" variant="ghost" size="sm" icon="eye">
                    Preview
                </flux:button>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <flux:field>
                    <flux:label>Category</flux:label>
                    <flux:select wire:model="postCategoryId">
                        <option value="">No Category</option>
                        @foreach($categories as $category)
                            <option value="{{ $category->id }}">{{ $category->name }}</option>
                        @endforeach
                    </flux:select>
                </flux:field>

                <flux:field>
                    <flux:label>Tags</flux:label>
                    <flux:select wire:model="postTagIds" multiple>
                        @foreach($tags as $tag)
                            <option value="{{ $tag->id }}">{{ $tag->name }}</option>
                        @endforeach
                    </flux:select>
                </flux:field>
            </div>

            <flux:field>
                <flux:label>Meta Description</flux:label>
                <flux:description>For SEO. Max 160 characters.</flux:description>
                <flux:textarea wire:model="postMetaDescription" rows="2" placeholder="A brief description for search engines..." />
                <flux:error name="postMetaDescription" />
            </flux:field>

            {{-- Community Story Integration --}}
            <flux:card class="bg-zinc-50 dark:bg-zinc-800/50">
                <flux:heading size="sm" class="mb-3">Community Stories</flux:heading>
                <flux:description class="mb-3">Optionally link a Community Question and select approved responses to feature. Use <code>@{{story:ID}}</code> in the body to place story cards.</flux:description>

                <flux:field>
                    <flux:label>Community Question</flux:label>
                    <flux:select wire:model.live="postCommunityQuestionId">
                        <option value="">None</option>
                        @foreach($communityQuestions as $cq)
                            <option value="{{ $cq->id }}">{{ $cq->question_text }}</option>
                        @endforeach
                    </flux:select>
                </flux:field>

                @if($postCommunityQuestionId && $availableResponses->count())
                    <div class="mt-3">
                        <flux:label class="mb-2">Select Responses to Feature</flux:label>
                        <div class="max-h-60 space-y-2 overflow-y-auto rounded border border-zinc-200 p-2 dark:border-zinc-700">
                            @foreach($availableResponses as $cr)
                                <label wire:key="cr-{{ $cr->id }}" class="flex cursor-pointer items-start gap-2 rounded p-2 hover:bg-zinc-100 dark:hover:bg-zinc-700">
                                    <input type="checkbox" wire:model="postCommunityResponseIds" value="{{ $cr->id }}" class="mt-1 rounded" />
                                    <div class="flex-1">
                                        <span class="text-sm font-medium">{{ $cr->user->name }}</span>
                                        <span class="text-xs text-zinc-500"> (ID: {{ $cr->id }})</span>
                                        <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">{{ Str::limit($cr->body, 120) }}</p>
                                    </div>
                                </label>
                            @endforeach
                        </div>
                    </div>
                @elseif($postCommunityQuestionId)
                    <flux:text variant="subtle" class="mt-3">No approved responses for this question.</flux:text>
                @endif
            </flux:card>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <flux:file-upload wire:model="heroImage" label="Hero Image">
                        <flux:file-upload.dropzone
                            heading="Hero image"
                            :text="'JPG, PNG, GIF, WEBP up to ' . $maxImageSizeLabel"
                        />
                    </flux:file-upload>
                    @if($heroImage)
                        <flux:file-item
                            :heading="$heroImage->getClientOriginalName()"
                            :image="$heroImage->temporaryUrl()"
                            :size="$heroImage->getSize()"
                        >
                            <x-slot name="actions">
                                <flux:file-item.remove wire:click="removeHeroImage" />
                            </x-slot>
                        </flux:file-item>
                    @elseif($existingHeroImageUrl)
                        <div class="mt-2">
                            <img src="{{ $existingHeroImageUrl }}" alt="Current hero image" class="w-full max-w-xs rounded-lg" />
                        </div>
                    @endif
                </div>

                <div>
                    <flux:file-upload wire:model="ogImage" label="OG Image">
                        <flux:file-upload.dropzone
                            heading="Open Graph image"
                            :text="'JPG, PNG, GIF, WEBP up to ' . $maxImageSizeLabel"
                        />
                    </flux:file-upload>
                    @if($ogImage)
                        <flux:file-item
                            :heading="$ogImage->getClientOriginalName()"
                            :image="$ogImage->temporaryUrl()"
                            :size="$ogImage->getSize()"
                        >
                            <x-slot name="actions">
                                <flux:file-item.remove wire:click="removeOgImage" />
                            </x-slot>
                        </flux:file-item>
                    @elseif($existingOgImageUrl)
                        <div class="mt-2">
                            <img src="{{ $existingOgImageUrl }}" alt="Current OG image" class="w-full max-w-xs rounded-lg" />
                        </div>
                    @endif
                </div>
            </div>

            <div class="flex justify-end gap-2 pt-4">
                <flux:button variant="ghost" x-on:click="$flux.modal('post-form-modal').close()">
                    Cancel
                </flux:button>
                <flux:button wire:click="savePost" variant="primary">
                    {{ $editingPostId ? 'Update Post' : 'Create Post' }}
                </flux:button>
            </div>
        </div>
    </flux:modal>

    {{-- Preview Modal --}}
    <flux:modal name="preview-modal" class="w-full lg:w-3/4 max-h-[90vh] overflow-y-auto">
        <div class="space-y-4">
            <flux:heading size="lg">Preview</flux:heading>
            <flux:heading size="md">{{ $postTitle ?: 'Untitled Post' }}</flux:heading>
            <div class="prose max-w-none dark:prose-invert">
                {!! $previewHtml !!}
            </div>
            <div class="flex justify-end pt-4">
                <flux:button variant="ghost" x-on:click="$flux.modal('preview-modal').close()">
                    Close
                </flux:button>
            </div>
        </div>
    </flux:modal>

    {{-- Approve Modal --}}
    <flux:modal name="approve-modal" class="w-full lg:w-1/2">
        <div class="space-y-4">
            <flux:heading size="lg">Approve Blog Post</flux:heading>

            <flux:field>
                <flux:checkbox wire:model.live="approvePublishImmediately" label="Publish immediately" />
            </flux:field>

            @if(! $approvePublishImmediately)
                <flux:field>
                    <flux:label>Schedule publication <span class="text-red-500">*</span></flux:label>
                    <flux:description>Date and time in your timezone ({{ Auth::user()->timezone ?? 'UTC' }}).</flux:description>
                    <flux:input wire:model="approveScheduledAt" type="datetime-local" />
                    <flux:error name="approveScheduledAt" />
                </flux:field>
            @endif

            <div class="flex justify-end gap-2 pt-4">
                <flux:button variant="ghost" x-on:click="$flux.modal('approve-modal').close()">
                    Cancel
                </flux:button>
                <flux:button wire:click="approvePost" variant="primary">
                    Approve
                </flux:button>
            </div>
        </div>
    </flux:modal>

    {{-- Category Create/Edit Modal --}}
    <flux:modal name="category-form-modal" class="w-full lg:w-1/2">
        <div class="space-y-4">
            <flux:heading size="lg">{{ $editingCategoryId ? 'Edit Category' : 'Create Category' }}</flux:heading>

            <flux:field>
                <flux:label>Name <span class="text-red-500">*</span></flux:label>
                <flux:input wire:model.live="categoryName" type="text" placeholder="Category name..." />
                <flux:error name="categoryName" />
            </flux:field>

            <flux:field>
                <flux:label>Slug <span class="text-red-500">*</span></flux:label>
                <flux:input wire:model="categorySlug" type="text" placeholder="category-slug" />
                <flux:error name="categorySlug" />
            </flux:field>

            <flux:field>
                <flux:checkbox wire:model="categoryIncludeInSitemap" label="Include in sitemap" />
            </flux:field>

            <div class="flex justify-end gap-2 pt-4">
                <flux:button variant="ghost" x-on:click="$flux.modal('category-form-modal').close()">
                    Cancel
                </flux:button>
                <flux:button wire:click="saveCategory" variant="primary">
                    {{ $editingCategoryId ? 'Update Category' : 'Create Category' }}
                </flux:button>
            </div>
        </div>
    </flux:modal>
</x-layouts.app>
