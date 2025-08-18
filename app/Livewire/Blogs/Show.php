<?php

namespace App\Livewire\Blogs;

use App\Models\Blog;
use Livewire\Component;

class Show extends Component
{
    public Blog $blog;

    public function mount(Blog $blog)
    {
        $this->blog = $blog;
    }

    public function render()
    {
        return view('livewire.blogs.show', [
            'blog' => $this->blog,
        ]);
    }
}
