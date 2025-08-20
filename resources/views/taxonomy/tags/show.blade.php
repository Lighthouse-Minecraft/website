<div class="p-4">
    <h1 class="text-2xl font-bold">{{ $tag->name }}</h1>

    @if (!empty($tag->description))
        <p class="mt-2 text-gray-700">{{ $tag->description }}</p>
    @endif
</div>
