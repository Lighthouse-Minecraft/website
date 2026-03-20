<?php

use App\Actions\ApproveBlogPost;
use App\Actions\ArchiveBlogPost;
use App\Actions\DeleteBlogPost;
use App\Actions\SubmitBlogPostForReview;
use App\Enums\BlogPostStatus;
use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\BlogTag;
use Carbon\Carbon;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Models\SiteConfig;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

new class extends Component {
    use WithFileUploads, WithPagination;

    // Tabs
    public string $activeTab = 'posts';

    // Filters
    public string $statusFilter = '';
    public string $search = '';

    // Category management
    public ?int $editingCategoryId = null;
    public string $categoryName = '';
    public string $categorySlug = '';
    public string $categoryContent = '';
    public $categoryHeroImage = null;
    public ?string $existingCategoryHeroImageUrl = null;
    public bool $categoryIncludeInSitemap = true;

    // Tag management
    public string $newTagName = '';

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

    public function deletePost(int $postId): void
    {
        $post = BlogPost::findOrFail($postId);
        $this->authorize('delete', $post);

        DeleteBlogPost::run($post);
        Flux::toast('Post deleted successfully.', 'Deleted', variant: 'success');
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
        $this->categoryContent = $category->content ?? '';
        $this->categoryHeroImage = null;
        $this->existingCategoryHeroImageUrl = $category->heroImageUrl();
        $this->categoryIncludeInSitemap = $category->include_in_sitemap;
        Flux::modal('category-form-modal')->show();
    }

    public function saveCategory(): void
    {
        $this->authorize('manage-blog');

        $maxImageSize = SiteConfig::getValue('max_image_size_kb', '2048');

        $rules = [
            'categoryName' => 'required|string|max:100',
            'categorySlug' => 'required|string|max:100',
            'categoryContent' => 'nullable|string|max:10000',
            'categoryHeroImage' => 'nullable|mimes:jpg,jpeg,png,gif,webp|max:' . $maxImageSize,
            'categoryIncludeInSitemap' => 'boolean',
        ];

        if ($this->editingCategoryId) {
            $rules['categorySlug'] .= '|unique:blog_categories,slug,' . $this->editingCategoryId;
        } else {
            $rules['categorySlug'] .= '|unique:blog_categories,slug';
        }

        $this->validate($rules);

        $heroImagePath = null;

        if ($this->categoryHeroImage) {
            $heroImagePath = $this->categoryHeroImage->store('blog/category-hero', config('filesystems.public_disk'));
        }

        if ($this->editingCategoryId) {
            $category = BlogCategory::findOrFail($this->editingCategoryId);

            $data = [
                'name' => $this->categoryName,
                'slug' => Str::slug($this->categorySlug),
                'content' => $this->categoryContent ?: null,
                'include_in_sitemap' => $this->categoryIncludeInSitemap,
            ];

            if ($heroImagePath) {
                if ($category->hero_image_path) {
                    Storage::disk(config('filesystems.public_disk'))->delete($category->hero_image_path);
                }
                $data['hero_image_path'] = $heroImagePath;
            }

            $category->update($data);
            Flux::toast('Category updated.', 'Updated', variant: 'success');
        } else {
            BlogCategory::create([
                'name' => $this->categoryName,
                'slug' => Str::slug($this->categorySlug),
                'content' => $this->categoryContent ?: null,
                'hero_image_path' => $heroImagePath,
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

    protected function resetCategoryForm(): void
    {
        $this->editingCategoryId = null;
        $this->categoryName = '';
        $this->categorySlug = '';
        $this->categoryContent = '';
        $this->categoryHeroImage = null;
        $this->existingCategoryHeroImageUrl = null;
        $this->categoryIncludeInSitemap = true;
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

        return [
            'posts' => $query->paginate(15),
            'categories' => BlogCategory::orderBy('name')->get(),
            'tags' => BlogTag::orderBy('name')->get(),
            'statuses' => BlogPostStatus::cases(),
        ];
    }
}; ?>

<div>
    <div class="mb-6 flex items-center justify-between">
        <flux:heading size="xl">Blog Management</flux:heading>
        <div class="flex gap-2">
            <flux:button href="{{ route('blog.create') }}" variant="primary" icon="plus" wire:navigate>
                New Post
            </flux:button>
            <flux:button wire:click="$set('activeTab', 'categories')" variant="ghost" icon="tag">
                Categories & Tags
            </flux:button>
        </div>
    </div>

    <div x-data="{ tab: @entangle('activeTab').live }">
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
                                            <flux:button href="{{ route('blog.edit', $post) }}" variant="ghost" size="sm" icon="pencil-square" wire:navigate>
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

                    <form wire:submit="createTag" class="mb-4 flex gap-2">
                        <flux:input wire:model="newTagName" type="text" placeholder="New tag name..." class="flex-1" />
                        <flux:button type="submit" variant="primary" size="sm" icon="plus">
                            Add
                        </flux:button>
                    </form>

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
                <flux:label>Content (Markdown)</flux:label>
                <flux:description>Optional content displayed on the category page.</flux:description>
                <flux:textarea wire:model="categoryContent" rows="6" placeholder="Describe this category..." />
                <flux:error name="categoryContent" />
            </flux:field>

            <div>
                <flux:label class="mb-2">Hero Image</flux:label>
                <flux:file-upload wire:model="categoryHeroImage">
                    <flux:file-upload.dropzone
                        heading="Category hero image"
                        text="JPG, PNG, GIF, WEBP"
                    />
                </flux:file-upload>
                @if($categoryHeroImage)
                    <div class="mt-2">
                        <img src="{{ $categoryHeroImage->temporaryUrl() }}" alt="Preview" class="w-full max-w-xs rounded-lg" />
                    </div>
                @elseif($existingCategoryHeroImageUrl)
                    <div class="mt-2">
                        <img src="{{ $existingCategoryHeroImageUrl }}" alt="Current hero image" class="w-full max-w-xs rounded-lg" />
                    </div>
                @endif
                <flux:error name="categoryHeroImage" />
            </div>

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
</div>
