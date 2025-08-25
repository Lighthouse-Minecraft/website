<?php

use App\Actions\AcknowledgeAnnouncement;
use App\Actions\AcknowledgeBlog;
use App\Models\Announcement;
use App\Models\Blog;
use Flux\Flux;
use Livewire\Volt\Component;

new class extends Component {
    /**
     * @var array<int, array{
     *   type: 'announcement'|'blog',
     *   id: int,
     *   title: string,
     *   excerpt: string,
     *   content: string,
     *   created_at: string,
     *   can_acknowledge: bool,
     * }>
     */
    public array $items = [];

    public function mount(): void
    {
        $ann = Announcement::query()
            ->where('is_published', true)
            ->whereDoesntHave('acknowledgers', fn($q) => $q->where('users.id', auth()->id()))
            ->with('author')
            ->get()
            ->map(fn($a) => [
                'type' => 'announcement',
                'id' => $a->id,
                'title' => (string) $a->title,
                'excerpt' => (string) $a->excerpt(),
                'content' => (string) $a->content,
                'created_at' => $a->created_at?->toImmutable() ?? now()->toImmutable(),
                'can_acknowledge' => auth()->user()?->can('acknowledge', $a) ?? false,
            ])
            ->toBase();

        $blogs = Blog::query()
            ->where('is_published', true)
            ->whereDoesntHave('acknowledgers', fn($q) => $q->where('users.id', auth()->id()))
            ->with('author')
            ->get()
            ->map(fn($b) => [
                'type' => 'blog',
                'id' => $b->id,
                'title' => (string) $b->title,
                'excerpt' => (string) $b->excerpt(),
                'content' => (string) $b->content,
                'created_at' => $b->created_at?->toImmutable() ?? now()->toImmutable(),
                'can_acknowledge' => auth()->user()?->can('acknowledge', $b) ?? false,
            ])
            ->toBase();

        $this->items = $ann->merge($blogs)
            ->sortByDesc('created_at')
            ->values()
            ->all();
    }

    public function acknowledge(string $type, int $id): void
    {
        if ($type === 'announcement') {
            $model = Announcement::findOrFail($id);
            if (auth()->user()->can('acknowledge', $model)) {
                AcknowledgeAnnouncement::run($model, auth()->user());
                Flux::toast('Announcement acknowledged.', 'Success', variant: 'success');
            } else {
                Flux::toast('Not allowed to acknowledge this announcement.', 'Error', variant: 'danger');
            }
            Flux::modal('notify-ann-'.$id)->close();
        } else {
            $model = Blog::findOrFail($id);
            if (auth()->user()->can('acknowledge', $model)) {
                AcknowledgeBlog::run($model, auth()->user());
                Flux::toast('Blog acknowledged.', 'Success', variant: 'success');
            } else {
                Flux::toast('Not allowed to acknowledge this blog.', 'Error', variant: 'danger');
            }
            Flux::modal('notify-blog-'.$id)->close();
        }

        redirect()->route('dashboard');
    }

    public function closeModal(string $type, int $id): void
    {
    $name = $type === 'announcement' ? 'notify-ann-' . $id : 'notify-blog-' . $id;
        Flux::modal($name)->close();
    }
}; ?>

<div>
    <div class="pointer-events-none fixed top-4 right-4 z-40 flex max-h-[80vh] w-[min(28rem,calc(100vw-2rem))] flex-col items-end gap-3 overflow-y-auto pr-1">
        @foreach ($items as $item)
            @php($isAnn = $item['type'] === 'announcement')
            <div
                x-data="{ show: true, timer: null, start(){ this.stop(); this.timer = setTimeout(() => this.show = false, 10000) }, stop(){ if(this.timer){ clearTimeout(this.timer); this.timer = null } } }"
                x-init="start()"
                x-on:mouseenter="stop()"
                x-on:mouseleave="start()"
                x-show="show"
                x-transition.opacity.duration.300ms
                x-cloak
                class="pointer-events-auto w-full rounded-lg border border-white/15 bg-white/10 p-3 text-sm shadow-lg backdrop-blur-md ring-1 ring-white/10 dark:bg-zinc-900/60 dark:ring-white/10"
            >
                <div class="flex items-start gap-3">
                    <flux:icon :name="$isAnn ? 'megaphone' : 'book-open'" class="mt-0.5 size-4 {{ $isAnn ? 'text-fuchsia-300' : 'text-blue-300' }}" />
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center justify-between gap-3">
                            <div class="truncate font-semibold">{{ $item['title'] }}</div>
                            <div class="shrink-0 text-xs text-zinc-400">
                                @php(
                                    $createdAt = $item['created_at'] instanceof \Carbon\CarbonInterface
                                        ? $item['created_at']
                                        : \Illuminate\Support\Carbon::parse($item['created_at'])
                                )
                                <time datetime="{{ $createdAt->toIso8601String() }}" title="{{ $createdAt->toDayDateTimeString() }}" data-fallback="{{ $createdAt->format('M d, Y H:i') }}">{{ $createdAt->diffForHumans() }}</time>
                            </div>
                        </div>
                        <div class="mt-1 line-clamp-2 text-zinc-200/80">{!! nl2br(e($item['excerpt'])) !!}</div>
                        <div class="mt-2 flex justify-end gap-2">
                            <flux:modal.trigger :name="$isAnn ? 'notify-ann-'.$item['id'] : 'notify-blog-'.$item['id']">
                                <flux:button size="xs" variant="primary">Read Full</flux:button>
                            </flux:modal.trigger>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
    @foreach ($items as $item)
        @php($isAnn = $item['type'] === 'announcement')
        <!-- Detail modals rendered outside of pointer-events-none wrapper so they are clickable -->
        @if ($isAnn)
            <flux:modal :name="'notify-ann-'.$item['id']" class="w-full md:w-3/4 xl:w-1/2">
                <flux:heading size="xl" class="mb-4 text-center">{{ $item['title'] }}</flux:heading>
                <div class="prose max-w-none whitespace-pre-wrap break-words [&_pre]:whitespace-pre-wrap [&_pre]:break-words [&_pre]:max-w-full [&_pre]:w-full [&_pre]:overflow-x-auto [&_code]:break-words [&_code]:break-all" style="text-align: justify;">
                    {!! $item['content'] !!}
                </div>

                <div class="w-full flex justify-end gap-2 mb-4">
                    @if($item['can_acknowledge'])
                        <flux:button wire:click="acknowledge('announcement', {{ $item['id'] }})" size="xs" variant="primary">
                            Mark As Read
                        </flux:button>
                    @endif
                </div>
            </flux:modal>
        @else
            <flux:modal :name="'notify-blog-'.$item['id']" class="w-full md:w-3/4 xl:w-1/2">
                <flux:heading size="xl" class="mb-4 text-center">{{ $item['title'] }}</flux:heading>
                <div class="prose max-w-none whitespace-pre-wrap break-words [&_pre]:whitespace-pre-wrap [&_pre]:break-words [&_pre]:max-w-full [&_pre]:w-full [&_pre]:overflow-x-auto [&_code]:break-words [&_code]:break-all" style="text-align: justify;">
                    {!! $item['content'] !!}
                </div>

                <div class="w-full flex justify-end gap-2 mb-4">
                    @if($item['can_acknowledge'])
                        <flux:button wire:click="acknowledge('blog', {{ $item['id'] }})" size="xs" variant="primary">
                            Mark As Read
                        </flux:button>
                    @endif
                </div>
            </flux:modal>
        @endif
    @endforeach
</div>
