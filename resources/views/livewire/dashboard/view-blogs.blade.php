<?php

use App\Actions\AcknowledgeBlog;
use App\Enums\MembershipLevel;
use App\Models\Blog;
use App\Models\Category;
use App\Models\Comment;
use App\Models\Role;
use App\Models\Tag;
use App\Models\User;
use Flux\Flux;
use Livewire\WithPagination;
use Livewire\Volt\Component;

new class extends Component {
    public $blogs;

    public function mount()
    {
        $this->blogs = Blog::where('is_published', true)
            ->whereDoesntHave('acknowledgers', function ($query) {
                $query->where('users.id', auth()->id());
            })
            ->with(['author', 'categories', 'tags'])
            ->get();
    }

    public function acknowledgeBlog($blogId)
    {
        $blog = Blog::findOrFail($blogId);

        if (auth()->user()->can('acknowledge', $blog)) {
            AcknowledgeBlog::run($blog, auth()->user());
            Flux::toast('Blog acknowledged successfully.', 'success');
        } else {
            Flux::toast('You do not have permission to acknowledge this blog.', 'error');
        }

        Flux::modal('view-blog-' . $blogId)->close();
        return redirect()->route('dashboard');
    }
};

?>

<div class="w-full space-y-6">
    @foreach($blogs as $blog)
    <flux:callout color="blue" inline class="mb-6">
            <flux:callout.heading>{{  $blog->title }}</flux:callout.heading>

            <flux:callout.text>
                {!! nl2br(e($blog->excerpt())) !!}
            </flux:callout.text>

            <x-slot name="actions">
                <flux:modal.trigger name="view-blog-{{ $blog->id }}">
                    <flux:button size="xs" variant="primary">Read Full Blog</flux:button>
                </flux:modal.trigger>
            </x-slot>

            <flux:modal name="view-blog-{{ $blog->id }}" class="w-full md:w-3/4 xl:w-1/2">
                <flux:heading size="xl" class="mb-4 text-center">{{ $blog->title }}</flux:heading>
                <div class="prose max-w-none whitespace-pre-wrap break-words [&_pre]:whitespace-pre-wrap [&_pre]:break-words [&_pre]:max-w-full [&_pre]:w-full [&_pre]:overflow-x-auto [&_code]:break-words [&_code]:break-all" style="text-align: justify;">
                    {!!  $blog->content !!}
                </div>

                @can('acknowledge', $blog)
                    <div class="w-full text-right mb-4">
                        <flux:button wire:click="acknowledgeBlog({{ $blog->id }})" size="xs" variant="primary">
                            Mark As Read
                        </flux:button>
                    </div>
                @endcan

                <flux:separator />
                <livewire:blogs.author-info :blog="$blog" />
                <livewire:blogs.categories :blog="$blog" />
                <livewire:blogs.tags :blog="$blog" />
                <livewire:blogs.comments :blog="$blog" />
            </flux:modal>
        </flux:callout>
    @endforeach
</div>
