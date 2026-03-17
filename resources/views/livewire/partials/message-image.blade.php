@if($message->imageUrl())
    <img src="{{ $message->imageUrl() }}" alt="Attached image" class="rounded-lg max-h-64 mt-2" loading="lazy" />
@elseif($message->image_was_purged)
    <div class="mt-2 text-xs text-zinc-400 dark:text-zinc-500 italic">
        [Image removed — attachments are automatically purged after closure]
    </div>
@endif
