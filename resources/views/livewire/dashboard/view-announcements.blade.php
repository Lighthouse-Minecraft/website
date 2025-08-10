<?php

use App\Enums\{MembershipLevel};
use App\Models\{Announcement, Category, Comment, Role, Tag, User};
use Flux\{Flux};
use Livewire\{WithPagination};
use Livewire\Volt\{Component};

new class extends Component {
    public $announcements;

    public function mount()
    {
        $this->announcements = Announcement::where('is_published', true)->with('author', 'categories', 'tags')->get();
    }
};

?>

<div class="w-full space-y-6">

    @foreach($announcements as $announcement)
        <flux:callout color="sky">
            <flux:callout.heading>{{  $announcement->title }}</flux:callout.heading>
            <flux:callout.text>
                {!! nl2br(e($announcement->excerpt())) !!}
            </flux:callout.text>
        </flux:callout>
    @endforeach

    <div class="d-flex flex-column gap-md">
        @foreach($announcements as $announcement)
            @if($announcements->isNotEmpty() && $announcement->is_published)
            <flux:card style="
                background: #1e2230;
                border: 1.5px solid #2c313c;
                border-radius: 14px;
                box-shadow: 0 4px 16px rgba(0,0,0,0.12);
                max-width: 50vw;
                margin-bottom: 1.2rem;"
            >
                <div>
                    <flux:text
                        size="xl"
                        weight="bold"
                        style="color:#fff;
                            text-align: center;"
                    >
                        {!! $announcement->title !!}
                        <hr>
                        {{--
                        --------- Optional Badges to show announcement status ---------
                            <flux:badge color="success" size="sm" style="margin-left: 0.5rem;">New</flux:badge>
                            <flux:badge color="info" size="sm" style="margin-left: 0.5rem;">Featured</flux:badge>
                            <flux:badge color="warning" size="sm" style="margin-left: 0.5rem;">Important</flux:badge>
                            <flux:badge color="danger" size="sm" style="margin-left: 0.5rem;">Urgent</flux:badge>
                            <flux:badge color="secondary" size="sm" style="margin-left: 0.5rem;">Archived</flux:badge>
                        --}}
                    </flux:text>
                    <div style="
                        margin: 0.75rem 0 0.5rem 0;
                        color:#cbd5e1;
                        font-size:.9rem;"
                    >
                        {!! nl2br(e($announcement->excerpt())) !!}
                    </div>
                    <hr style="border: none; border-top: 1.5px solid #33363cff; margin: 0.5rem 0 0.3rem 0;">
                    <flux:text
                        size="sm"
                        style="
                            color:#94a3b8;
                            margin-top:0;
                            text-align: right;"
                    >
                        @php $author = $announcement->author; @endphp
                            <span style="display: flex; align-items: right; gap: 6px;">
                                    <span style="display: flex; align-items: center; gap: 6px; justify-content: flex-end; width: 100%;">
                                        Published by
                                        @if($author)
                                            <span style="display: inline-flex; align-items: center;">
                                                @if(!empty($author->avatar))
                                                    <flux:avatar size="xs" src="{{ $author->avatar }}" style="vertical-align: middle; margin-right: 4px;" />
                                                @endif
                                                <flux:link href="{{ route('profile.show', ['user' => $author]) }}" style="vertical-align: middle;">
                                                    {{ $author->name }}
                                                </flux:link>
                                            </span>
                                        @else
                                            Unknown
                                        @endif
                                        on {{ $announcement->created_at ? $announcement->created_at->format('Y-m-d H:i') : 'N/A' }}
                                    </span>
                            </span>
                    </flux:text>
                        {{-- Categories --}}
                        @if($announcement->categories && $announcement->categories->isNotEmpty())
                            <div style="margin-top: 0.5rem;">
                                <strong>Categories:</strong>
                                @foreach($announcement->categories as $category)
                                    <span style="background:#334155;color:#fff;padding:2px 8px;border-radius:8px;margin-right:4px;font-size:0.85em;">{{ $category->name }}</span>
                                @endforeach
                            </div>
                        @endif

                        {{-- Tags --}}
                        @if($announcement->tags && $announcement->tags->isNotEmpty())
                            <div style="margin-top: 0.5rem;">
                                <strong>Tags:</strong>
                                @foreach($announcement->tags as $tag)
                                    <span style="background:#64748b;color:#fff;padding:2px 8px;border-radius:8px;margin-right:4px;font-size:0.85em;">{{ $tag->name }}</span>
                                @endforeach
                            </div>
                        @endif

                        {{-- Comments --}}
                        <div style="margin-top: 0.5rem;">
                            <strong>Comments:</strong>
                            {{ $announcement->comments->count() }}
                            @if($announcement->comments->isNotEmpty())
                                <ul style="margin:0.5rem 0 0 0;padding-left:1rem;">
                                    @foreach($announcement->comments->take(2) as $comment)
                                        <li style="color:#cbd5e1;font-size:0.95em;">{{ $comment->body }} <em style="color:#94a3b8;">— {{ $comment->author->name ?? 'Unknown' }}</em></li>
                                    @endforeach
                                    @if($announcement->comments->count() > 2)
                                        <li style="color:#94a3b8;font-size:0.9em;">...and {{ $announcement->comments->count() - 2 }} more</li>
                                    @endif
                                </ul>
                            @endif
                        </div>
                </div>
            </flux:card>
            @endif
        @endforeach
    </div>
</div>
