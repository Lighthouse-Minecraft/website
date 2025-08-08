<?php



    use App\Models\{Role, User};
    use App\Enums\{MembershipLevel};
    use Flux\{Flux};
    use Livewire\Volt\{Component};
    new class extends Component {

        public $announcements;

        public function mount()
        {
            $this->announcements = \App\Models\Announcement::whereNotNull('is_published')->get();
        }
    };
?>

<div>
    <flux:heading size="xl">Announcements</flux:heading>
    <div class="d-flex flex-column gap-md">
        @foreach($announcements as $announcement)
            @if($announcement->is_published)
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
                    </flux:text>
                    <div style="
                        margin: 0.75rem 0 0.5rem 0;
                        color:#cbd5e1;
                        font-size:.9rem;"
                    >
                        {!! $announcement->content !!}
                    </div>
                    <hr style="border: none; border-top: 1.5px solid #33363cff; margin: 0.5rem 0 0.3rem 0;">
                    <flux:text
                        size="sm"
                        style="
                            color:#94a3b8;
                            margin-top:0;
                            text-align: right;"
                    >
                        Published by {{ $announcement->author->name ?? 'Unknown' }} on {{ $announcement->created_at ? $announcement->created_at->format('Y-m-d H:i') : 'N/A' }}
                    </flux:text>
                </div>
            </flux:card>
            @endif
        @endforeach
    </div>
</div>
