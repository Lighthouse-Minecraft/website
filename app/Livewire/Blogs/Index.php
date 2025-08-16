<?php

namespace App\Livewire\Blogs;

use App\Models\Blog;
use Livewire\Component;

class Index extends Component
{
    public $search = '';

    public function render()
    {
        $blogs = Blog::query()
            ->when($this->search, function ($query) {
                $query->where('title', 'like', "%{$this->search}%");
            })
            ->get();

        return view('blogs.index', [
            'blogs' => $blogs,
        ]);
    }
}
