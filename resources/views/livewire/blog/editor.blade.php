<?php

use App\Actions\CreateBlogPost;
use App\Actions\UpdateBlogPost;
use App\Actions\UploadBlogImage;
use App\Enums\CommunityResponseStatus;
use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\BlogTag;
use App\Models\CommunityQuestion;
use App\Models\CommunityResponse;
use App\Models\SiteConfig;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new class extends Component {
    use WithFileUploads;

    public ?BlogPost $editingPost = null;

    // Post form
    public string $postTitle = '';
    public string $postBody = '';
    public ?int $postCategoryId = null;
    public array $postTagIds = [];
    public ?int $selectedTagId = null;
    public string $postMetaDescription = '';
    public $heroImage = null;
    public $ogImage = null;
    public $inlineImage = null;
    public ?string $existingHeroImageUrl = null;
    public ?string $existingOgImageUrl = null;

    // Blog image upload modal
    public $blogImageFile = null;
    public string $blogImageTitle = '';
    public string $blogImageAltText = '';

    // Community story integration
    public ?int $postCommunityQuestionId = null;
    public array $postCommunityResponseIds = [];

    // Preview
    public string $previewHtml = '';

    public function mount(?int $post = null): void
    {
        $this->authorize('manage-blog');

        if ($post) {
            $this->editingPost = BlogPost::with(['tags', 'communityResponses'])->findOrFail($post);
            $this->authorize('update', $this->editingPost);

            $this->postTitle = $this->editingPost->title;
            $this->postBody = $this->editingPost->body;
            $this->postCategoryId = $this->editingPost->category_id;
            $this->postTagIds = $this->editingPost->tags->pluck('id')->toArray();
            $this->postMetaDescription = $this->editingPost->meta_description ?? '';
            $this->postCommunityQuestionId = $this->editingPost->community_question_id;
            $this->postCommunityResponseIds = $this->editingPost->communityResponses->pluck('id')->toArray();
            $this->existingHeroImageUrl = $this->editingPost->heroImageUrl();
            $this->existingOgImageUrl = $this->editingPost->ogImageUrl();
        } else {
            $this->authorize('create', BlogPost::class);
        }
    }

    public function updatedHeroImage(): void
    {
        $this->validateOnly('heroImage', [
            'heroImage' => 'nullable|mimes:jpg,jpeg,png,gif,webp|max:' . SiteConfig::getValue('max_image_size_kb', '2048'),
        ]);
    }

    public function updatedOgImage(): void
    {
        $this->validateOnly('ogImage', [
            'ogImage' => 'nullable|mimes:jpg,jpeg,png,gif,webp|max:' . SiteConfig::getValue('max_image_size_kb', '2048'),
        ]);
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

        if ($this->editingPost) {
            $this->authorize('update', $this->editingPost);

            if ($heroImagePath) {
                if ($this->editingPost->hero_image_path) {
                    Storage::disk(config('filesystems.public_disk'))->delete($this->editingPost->hero_image_path);
                }
                $data['hero_image_path'] = $heroImagePath;
            }

            if ($ogImagePath) {
                if ($this->editingPost->og_image_path) {
                    Storage::disk(config('filesystems.public_disk'))->delete($this->editingPost->og_image_path);
                }
                $data['og_image_path'] = $ogImagePath;
            }

            UpdateBlogPost::run($this->editingPost, $data);
            Flux::toast('Post updated successfully.', 'Updated', variant: 'success');
        } else {
            $this->authorize('create', BlogPost::class);

            $data['hero_image_path'] = $heroImagePath;
            $data['og_image_path'] = $ogImagePath;

            $this->editingPost = CreateBlogPost::run(Auth::user(), $data);
            Flux::toast('Post created successfully.', 'Created', variant: 'success');
        }

        $this->redirect(route('blog.manage'), navigate: true);
    }

    public function openPreviewModal(): void
    {
        $tempPost = new BlogPost(['body' => $this->postBody]);
        $this->previewHtml = $tempPost->renderBody();
        Flux::modal('preview-modal')->show();
    }

    public function updatedPostCommunityQuestionId(): void
    {
        $this->postCommunityResponseIds = [];
    }

    public function updatedInlineImage(): void
    {
        $this->validateOnly('inlineImage', [
            'inlineImage' => 'nullable|mimes:jpg,jpeg,png,gif,webp|max:' . SiteConfig::getValue('max_image_size_kb', '2048'),
        ]);
    }

    public function uploadInlineImage(): void
    {
        $this->validate([
            'inlineImage' => 'required|mimes:jpg,jpeg,png,gif,webp|max:' . SiteConfig::getValue('max_image_size_kb', '2048'),
        ]);

        $path = $this->inlineImage->store('blog/inline', config('filesystems.public_disk'));
        $url = \App\Services\StorageService::publicUrl($path);
        $filename = $this->inlineImage->getClientOriginalName();

        $this->postBody = rtrim($this->postBody) . "\n\n![{$filename}]({$url})\n";

        $this->inlineImage = null;
        Flux::toast('Image inserted into post body.', 'Uploaded', variant: 'success');
    }

    public function updatedBlogImageFile(): void
    {
        $this->validateOnly('blogImageFile', [
            'blogImageFile' => 'nullable|mimes:jpg,jpeg,png,gif,webp|max:' . SiteConfig::getValue('max_image_size_kb', '2048'),
        ]);
    }

    public function uploadBlogImage(): void
    {
        $this->authorize('manage-blog');

        $this->validate([
            'blogImageFile' => 'required|mimes:jpg,jpeg,png,gif,webp|max:' . SiteConfig::getValue('max_image_size_kb', '2048'),
            'blogImageTitle' => 'required|string|max:255',
            'blogImageAltText' => 'required|string|max:255',
        ]);

        $image = UploadBlogImage::run(
            Auth::user(),
            $this->blogImageFile,
            $this->blogImageTitle,
            $this->blogImageAltText,
        );

        $this->postBody = rtrim($this->postBody) . "\n\n{{image:{$image->id}}}\n";

        $this->blogImageFile = null;
        $this->blogImageTitle = '';
        $this->blogImageAltText = '';

        Flux::modal('blog-image-upload-modal')->close();
        Flux::toast('Image uploaded and inserted into post body.', 'Uploaded', variant: 'success');
    }

    public function removeHeroImage(): void
    {
        $this->heroImage = null;
    }

    public function removeOgImage(): void
    {
        $this->ogImage = null;
    }

    public function addTag(): void
    {
        if ($this->selectedTagId && ! in_array($this->selectedTagId, $this->postTagIds)) {
            $this->postTagIds[] = $this->selectedTagId;
        }
        $this->selectedTagId = null;
    }

    public function removeTag(int $tagId): void
    {
        $this->postTagIds = array_values(array_filter($this->postTagIds, fn ($id) => $id !== $tagId));
    }

    public function with(): array
    {
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
            'categories' => BlogCategory::orderBy('name')->get(),
            'tags' => BlogTag::orderBy('name')->get(),
            'communityQuestions' => $communityQuestions,
            'availableResponses' => $availableResponses,
            'maxImageSizeLabel' => ((int) SiteConfig::getValue('max_image_size_kb', '2048')) >= 1024
                ? round((int) SiteConfig::getValue('max_image_size_kb', '2048') / 1024) . 'MB'
                : SiteConfig::getValue('max_image_size_kb', '2048') . 'KB',
        ];
    }
}; ?>

<div class="mx-auto max-w-4xl space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <flux:link href="{{ route('blog.manage') }}" wire:navigate>&larr; Back to Blog Management</flux:link>
            <flux:heading size="xl" class="mt-2">{{ $editingPost ? 'Edit Post' : 'Create Post' }}</flux:heading>
        </div>
        <div class="flex gap-2">
            <flux:button wire:click="openPreviewModal" variant="ghost" icon="eye">
                Preview
            </flux:button>
            <flux:button wire:click="savePost" variant="primary" icon="check">
                {{ $editingPost ? 'Update Post' : 'Create Post' }}
            </flux:button>
        </div>
    </div>

    <flux:card>
        <div class="space-y-6">
            <flux:field>
                <flux:label>Title <span class="text-red-500">*</span></flux:label>
                <flux:input wire:model="postTitle" type="text" placeholder="Post title..." />
                <flux:error name="postTitle" />
            </flux:field>

            <flux:field>
                <flux:label>Body (Markdown) <span class="text-red-500">*</span></flux:label>
                <flux:description>Write your post content in Markdown format. Use <code>@{{story:ID}}</code> to embed community story cards.</flux:description>
                <flux:textarea wire:model="postBody" rows="20" placeholder="Write your post in markdown..." />
                <flux:error name="postBody" />
            </flux:field>

            {{-- Upload Image Button --}}
            <div>
                <flux:modal.trigger name="blog-image-upload-modal">
                    <flux:button variant="ghost" size="sm" icon="photo">
                        Upload Image
                    </flux:button>
                </flux:modal.trigger>
            </div>

            {{-- Inline Image Upload (legacy) --}}
            <div class="rounded-lg border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-700 dark:bg-zinc-800/50">
                <flux:label class="mb-1">Insert Image (Raw URL)</flux:label>
                <flux:description class="mb-2">Upload an image to insert as a raw markdown URL into the post body.</flux:description>
                <flux:file-upload wire:model="inlineImage">
                    <flux:file-upload.dropzone
                        heading="Drop an image here"
                        :text="'JPG, PNG, GIF, WEBP up to ' . $maxImageSizeLabel"
                    />
                </flux:file-upload>
                @if($inlineImage)
                    <div class="mt-3 flex items-center gap-3">
                        <img src="{{ $inlineImage->temporaryUrl() }}" alt="Preview" class="h-16 w-16 rounded object-cover" />
                        <span class="text-sm text-zinc-600 dark:text-zinc-400">{{ $inlineImage->getClientOriginalName() }}</span>
                        <flux:button wire:click="uploadInlineImage" variant="primary" size="sm" icon="arrow-up-tray">
                            Insert into Post
                        </flux:button>
                    </div>
                @endif
                <flux:error name="inlineImage" />
            </div>
        </div>
    </flux:card>

    <flux:card>
        <flux:heading size="md" class="mb-4">Metadata</flux:heading>
        <div class="space-y-4">
            <div class="grid gap-4 sm:grid-cols-2">
                <flux:field>
                    <flux:label>Category</flux:label>
                    <flux:description>A category is required for the URL to work properly. Posts without a category will use "uncategorized" in the URL.</flux:description>
                    <flux:select wire:model="postCategoryId">
                        <option value="">No Category</option>
                        @foreach($categories as $category)
                            <option value="{{ $category->id }}">{{ $category->name }}</option>
                        @endforeach
                    </flux:select>
                </flux:field>

                <flux:field>
                    <flux:label>Tags</flux:label>
                    <div class="flex flex-wrap items-center gap-2 mt-1">
                        @php $selectedTags = $tags->filter(fn ($t) => in_array($t->id, $postTagIds)); @endphp
                        @foreach($selectedTags as $tag)
                            <div wire:key="selected-tag-{{ $tag->id }}" class="flex items-center gap-1">
                                <flux:badge size="sm">{{ $tag->name }}</flux:badge>
                                <flux:button size="xs" variant="ghost" icon="x-mark" wire:click="removeTag({{ $tag->id }})" class="hover:!text-red-600 dark:hover:!text-red-400" />
                            </div>
                        @endforeach
                        <flux:modal.trigger name="tag-picker-modal">
                            <flux:button size="sm" icon="plus">
                                Add Tags
                            </flux:button>
                        </flux:modal.trigger>
                    </div>
                </flux:field>
            </div>

            <flux:field>
                <flux:label>Meta Description</flux:label>
                <flux:description>For SEO. Max 160 characters.</flux:description>
                <flux:textarea wire:model="postMetaDescription" rows="2" placeholder="A brief description for search engines..." />
                <flux:error name="postMetaDescription" />
            </flux:field>
        </div>
    </flux:card>

    <flux:card>
        <flux:heading size="md" class="mb-4">Images</flux:heading>
        <div class="grid gap-6 sm:grid-cols-2">
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
    </flux:card>

    <flux:card>
        <flux:heading size="md" class="mb-4">Community Stories</flux:heading>
        <flux:description class="mb-4">Optionally link a Community Question and select approved responses to feature. Use <code>@{{story:ID}}</code> in the body to place story cards.</flux:description>

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
            <div class="mt-4">
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

    <div class="flex justify-end gap-3 pb-8">
        <flux:button href="{{ route('blog.manage') }}" variant="ghost" wire:navigate>
            Cancel
        </flux:button>
        <flux:button wire:click="savePost" variant="primary" icon="check">
            {{ $editingPost ? 'Update Post' : 'Create Post' }}
        </flux:button>
    </div>

    {{-- Tag Picker Modal --}}
    <flux:modal name="tag-picker-modal" class="w-full lg:w-1/2 space-y-6">
        <flux:heading size="lg">Manage Tags</flux:heading>

        {{-- Assigned Tags --}}
        <div>
            <flux:heading size="sm" class="mb-2">Assigned Tags</flux:heading>
            @php $selectedTags = $tags->filter(fn ($t) => in_array($t->id, $postTagIds)); @endphp
            @if($selectedTags->isNotEmpty())
                <div class="flex flex-wrap gap-2">
                    @foreach($selectedTags as $tag)
                        <div wire:key="modal-tag-{{ $tag->id }}" class="flex items-center gap-1">
                            <flux:badge size="sm">{{ $tag->name }}</flux:badge>
                            <flux:button size="xs" variant="ghost" icon="x-mark" wire:click="removeTag({{ $tag->id }})" class="hover:!text-red-600 dark:hover:!text-red-400" />
                        </div>
                    @endforeach
                </div>
            @else
                <flux:text variant="subtle" class="text-sm">No tags assigned yet.</flux:text>
            @endif
        </div>

        {{-- Add Tag --}}
        @php $unselectedTags = $tags->reject(fn ($t) => in_array($t->id, $postTagIds)); @endphp
        @if($unselectedTags->isNotEmpty())
            <div>
                <flux:heading size="sm" class="mb-2">Add Tag</flux:heading>
                <div class="flex gap-2">
                    <flux:select wire:model="selectedTagId" class="flex-1">
                        <flux:select.option value="">Select a tag...</flux:select.option>
                        @foreach($unselectedTags as $tag)
                            <flux:select.option value="{{ $tag->id }}">{{ $tag->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:button wire:click="addTag" variant="primary" size="sm" icon="plus">Add</flux:button>
                </div>
            </div>
        @elseif($tags->isNotEmpty())
            <flux:text variant="subtle">All tags have been assigned.</flux:text>
        @else
            <flux:text variant="subtle">No tags available. Create tags in Blog Management.</flux:text>
        @endif

        <div class="flex justify-end">
            <flux:button variant="ghost" x-on:click="$flux.modal('tag-picker-modal').close()">Done</flux:button>
        </div>
    </flux:modal>

    {{-- Blog Image Upload Modal --}}
    <flux:modal name="blog-image-upload-modal" class="w-full lg:w-1/2 space-y-6">
        <flux:heading size="lg">Upload Image</flux:heading>

        <flux:field>
            <flux:label>Title <span class="text-red-500">*</span></flux:label>
            <flux:description>A short name for this image to help you find it later. Example: 'Summer Festival Group Photo'</flux:description>
            <flux:input wire:model="blogImageTitle" type="text" placeholder="Image title..." />
            <flux:error name="blogImageTitle" />
        </flux:field>

        <flux:field>
            <flux:label>Alt Text <span class="text-red-500">*</span></flux:label>
            <flux:description>Describe what the image shows for screen readers and SEO. Example: 'Players gathered around the fountain at spawn during the summer festival'</flux:description>
            <flux:input wire:model="blogImageAltText" type="text" placeholder="Describe the image..." />
            <flux:error name="blogImageAltText" />
        </flux:field>

        <flux:field>
            <flux:label>Image File <span class="text-red-500">*</span></flux:label>
            <flux:file-upload wire:model="blogImageFile">
                <flux:file-upload.dropzone
                    heading="Drop an image here"
                    :text="'JPG, PNG, GIF, WEBP up to ' . $maxImageSizeLabel"
                />
            </flux:file-upload>
            @if($blogImageFile)
                <div class="mt-3 flex items-center gap-3">
                    <img src="{{ $blogImageFile->temporaryUrl() }}" alt="Preview" class="h-16 w-16 rounded object-cover" />
                    <span class="text-sm text-zinc-600 dark:text-zinc-400">{{ $blogImageFile->getClientOriginalName() }}</span>
                </div>
            @endif
            <flux:error name="blogImageFile" />
        </flux:field>

        <div class="flex justify-end gap-3">
            <flux:button variant="ghost" x-on:click="$flux.modal('blog-image-upload-modal').close()">Cancel</flux:button>
            <flux:button wire:click="uploadBlogImage" variant="primary" icon="arrow-up-tray">Upload & Insert</flux:button>
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
</div>
