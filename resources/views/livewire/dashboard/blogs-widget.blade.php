<?php

use App\Models\{Blog};
use Livewire\{WithPagination, Volt\Component};

new class extends Component {
    use WithPagination;

    public $activeBlogTab = 'active-blogs';
    public int $perPage = 4;

    public function getBlogsProperty()
    {
        return Blog::query()
            ->where('is_published', true)
            ->with(['author', 'categories', 'tags'])
            ->orderBy('created_at', 'desc')
            ->paginate($this->perPage, ['*'], 'blog_widget_page');
    }

}; ?>

<flux:card class="w-full">
    <flux:heading size="md" class="mb-2">Community Blogs</flux:heading>

    <flux:table :paginate="$this->blogs">
        <flux:table.rows>
            @foreach ($this->blogs as $blog)
                <flux:table.row>
                    <flux:table.cell>
                        <flux:link href="{{ route('blogs.show', $blog->id) }}">{{  $blog->title }}</flux:link>
                    </flux:table.cell>
                </flux:table.row>
            @endforeach
        </flux:table.rows>
    </flux:table>
</flux:card>
