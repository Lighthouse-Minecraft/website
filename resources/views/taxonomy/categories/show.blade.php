<div class="p-4">
    <h1 class="text-2xl font-bold">{{ $category->name }}</h1>

    @if (!empty($category->description))
        <p class="mt-2 text-gray-700">{{ $category->description }}</p>
    @endif
</div>
