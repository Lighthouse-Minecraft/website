<div class="space-y-6">
    <flux:heading size="xl">Create New Comment</flux:heading>

    <form wire:submit.prevent="saveComment">
        <div class="space-y-6">
            <div>
                <flux:editor
                    label="Comment Content"
                    wire:model="commentContent"
                    class="
                        w-full max-w-full overflow-hidden
                        [&_[data-slot=content]_.ProseMirror]:break-words
                        [&_[data-slot=content]_.ProseMirror]:break-all
                        [&_[data-slot=content]_.ProseMirror]:whitespace-pre-wrap
                        [&_[data-slot=content]_.ProseMirror]:w-full
                        [&_[data-slot=content]_.ProseMirror]:max-w-full
                        [&_[data-slot=content]_.ProseMirror]:overflow-x-auto
                        [&_[data-slot=content]]:max-h-[500px]
                        [&_[data-slot=content]]:overflow-y-auto
                        [&_[data-slot=content]_pre]:overflow-x-auto
                        [&_[data-slot=content]_pre]:!whitespace-pre-wrap
                        [&_[data-slot=content]_pre]:max-w-full
                        [&_[data-slot=content]_pre]:w-full
                        [&_[data-slot=content]_pre_code]:!break-words
                        [&_[data-slot=content]_pre_code]:break-all
                        [&_[data-slot=content]_pre]:rounded-md
                        [&_[data-slot=content]_pre]:p-3
                        [&_[data-slot=content]_pre]:my-3
                        [&_[data-slot=content]_pre]:border
                        [&_[data-slot=content]_pre]:bg-black/10
                        [&_[data-slot=content]_pre]:border-black/20
                        dark:[&_[data-slot=content]_pre]:bg-white/10
                        dark:[&_[data-slot=content]_pre]:border-white/20
                        [&_[data-slot=content]_pre]:font-mono
                        [&_[data-slot=content]_pre]:text-sm
                    "
                />
            </div>

            <flux:field>
                <flux:label>Type</flux:label>
                <flux:select wire:model.live="commentable_type" placeholder="Select Type" :invalid="$errors->has('commentable_type')" class="w-full max-w-xl">
                    <flux:select.option value="announcement">Announcement</flux:select.option>
                    <flux:select.option value="blog">Blog</flux:select.option>
                </flux:select>
                <flux:error name="commentable_type" />
            </flux:field>

            <flux:field>
                <flux:label>Resource</flux:label>
                <flux:select variant="listbox" wire:model.live="commentable_id">
                    <x-slot name="button">
                        <flux:select.button
                            class="w-full max-w-xl"
                            placeholder="Select Resource"
                            :invalid="$commentable_type && ($errors->has('commentable_id') || blank($commentable_id))"
                            :disabled="! $commentable_type"
                        />
                    </x-slot>
                    @foreach ($resourceOptions as $option)
                        <flux:select.option value="{{ $option['value'] }}">{{ $option['label'] }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="commentable_id" />
            </flux:field>

            <div class="w-full text-right">
                @if(auth()->check())
                    <flux:button wire:click="saveComment" icon="document-check" variant="primary">Save Comment</flux:button>
                @else
                    <flux:button disabled icon="lock" variant="primary">Login to comment</flux:button>
                @endif
            </div>
        </div>
    </form>
</div>
