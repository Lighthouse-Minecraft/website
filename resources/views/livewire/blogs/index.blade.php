<div>
    <h1>Blog List</h1>
    <input type="text" wire:model.lazy="search" placeholder="Search blogs..." class="border rounded px-2 py-1 mb-4" />
    <ul>
        @forelse($blogs as $blog)
            <li>
                <strong>{{ $blog->title }}</strong>
                <span class="text-gray-500">({{ $blog->created_at->format('Y-m-d') }})</span>
            </li>
        @empty
            <li>No blogs found.</li>
        @endforelse
    </ul>
</div>
