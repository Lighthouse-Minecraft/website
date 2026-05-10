<?php

use App\Actions\AddBackgroundCheckNote;
use App\Actions\AttachBackgroundCheckDocuments;
use App\Actions\CreateBackgroundCheck;
use App\Actions\DeleteBackgroundCheckDocument;
use App\Actions\UpdateBackgroundCheckStatus;
use App\Enums\BackgroundCheckStatus;
use App\Models\BackgroundCheck;
use App\Models\BackgroundCheckDocument;
use App\Models\User;
use App\Services\StorageService;
use Carbon\Carbon;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new class extends Component {
    use WithFileUploads;

    #[Locked]
    public int $userId;
    #[Locked]
    public bool $canManage = false;
    #[Locked]
    public bool $canViewRenewal = false;

    // Add-check modal
    public string $newService = '';
    public string $newCompletedDate = '';
    public string $newInitialNotes = '';

    // Note adding
    public ?int $noteCheckId = null;
    public string $noteText = '';

    // Document upload
    public ?int $uploadCheckId = null;
    public array $pendingDocuments = [];

    public function mount(User $user): void
    {
        $auth = Auth::user();
        $isSelf = $auth && $auth->id === $user->id;
        $canView = $auth && $auth->can('background-checks-view');

        if (! $isSelf && ! $canView) {
            abort(403);
        }

        $this->userId = $user->id;
        $this->canManage = (bool) ($auth && $auth->can('background-checks-manage'));
        $this->canViewRenewal = (bool) $canView;
    }

    #[Computed]
    public function user(): User
    {
        return User::with([
            'backgroundChecks' => fn ($q) => $q->with(['documents.uploadedByUser', 'runByUser'])->latest('id'),
            'latestTerminalBackgroundCheck',
            'latestPassedBackgroundCheck',
        ])->findOrFail($this->userId);
    }

    #[Computed]
    public function renewalBadge(): ?array
    {
        if (! $this->canViewRenewal) {
            return null;
        }

        $latestTerminal = $this->user->latestTerminalBackgroundCheck;

        if ($latestTerminal && $latestTerminal->status === BackgroundCheckStatus::Waived) {
            return ['color' => 'violet', 'label' => 'Waived'];
        }

        $latestPassed = $this->user->latestPassedBackgroundCheck;

        if (! $latestPassed) {
            return ['color' => 'red', 'label' => 'Overdue'];
        }

        $expiresAt = $latestPassed->completed_date->copy()->addYears(2);
        $dueSoonAt = $expiresAt->copy()->subDays(90);

        if ($expiresAt->lte(now())) {
            return ['color' => 'red', 'label' => 'Overdue'];
        }

        if ($dueSoonAt->lte(now())) {
            return ['color' => 'amber', 'label' => 'Due Soon'];
        }

        return null;
    }

    public function openAddCheckModal(): void
    {
        $this->authorize('background-checks-manage');
        $this->newService = '';
        $this->newCompletedDate = '';
        $this->newInitialNotes = '';
        Flux::modal('add-bg-check-modal-' . $this->userId)->show();
    }

    public function submitNewCheck(): void
    {
        $this->authorize('background-checks-manage');

        $this->validate([
            'newService' => 'required|string|max:255',
            'newCompletedDate' => 'required|date|before_or_equal:today',
            'newInitialNotes' => 'nullable|string|max:5000',
        ]);

        $user = User::findOrFail($this->userId);
        CreateBackgroundCheck::run(
            $user,
            Auth::user(),
            $this->newService,
            Carbon::parse($this->newCompletedDate),
            $this->newInitialNotes ?: null,
        );

        $this->newService = '';
        $this->newCompletedDate = '';
        $this->newInitialNotes = '';
        Flux::modal('add-bg-check-modal-' . $this->userId)->close();
        Flux::toast('Background check record added.', 'Added', variant: 'success');
    }

    public function openNoteForm(int $checkId): void
    {
        $this->authorize('background-checks-manage');
        $this->noteCheckId = $checkId;
        $this->noteText = '';
        $this->uploadCheckId = null;
        $this->pendingDocuments = [];
    }

    public function cancelNote(): void
    {
        $this->noteCheckId = null;
        $this->noteText = '';
    }

    public function submitNote(): void
    {
        $this->authorize('background-checks-manage');
        $this->validate(['noteText' => 'required|string|min:1|max:5000']);
        $check = BackgroundCheck::findOrFail($this->noteCheckId);
        AddBackgroundCheckNote::run($check, $this->noteText, Auth::user());
        $this->noteCheckId = null;
        $this->noteText = '';
        Flux::toast('Note saved.', 'Note Added', variant: 'success');
    }

    public function updateStatus(int $checkId, string $status): void
    {
        $this->authorize('background-checks-manage');
        $parsed = BackgroundCheckStatus::tryFrom($status);
        if (! $parsed) {
            Flux::toast('Invalid status value.', 'Error', variant: 'danger');

            return;
        }
        $check = BackgroundCheck::findOrFail($checkId);
        UpdateBackgroundCheckStatus::run($check, $parsed, Auth::user());
        Flux::toast('Status updated.', 'Updated', variant: 'success');
    }

    public function openUploadForm(int $checkId): void
    {
        $this->authorize('background-checks-manage');
        $this->uploadCheckId = $checkId;
        $this->pendingDocuments = [];
        $this->noteCheckId = null;
        $this->noteText = '';
    }

    public function cancelUpload(): void
    {
        $this->uploadCheckId = null;
        $this->pendingDocuments = [];
    }

    public function submitDocuments(): void
    {
        $this->authorize('background-checks-manage');

        if (empty($this->pendingDocuments)) {
            Flux::toast('No documents selected.', 'Upload', variant: 'warning');

            return;
        }

        $check = BackgroundCheck::findOrFail($this->uploadCheckId);
        AttachBackgroundCheckDocuments::run($check, $this->pendingDocuments, Auth::user());
        $this->uploadCheckId = null;
        $this->pendingDocuments = [];
        Flux::toast('Documents uploaded.', 'Uploaded', variant: 'success');
    }

    public function deleteDocument(int $docId): void
    {
        $this->authorize('background-checks-manage');
        $doc = BackgroundCheckDocument::findOrFail($docId);
        DeleteBackgroundCheckDocument::run($doc, Auth::user());
        Flux::toast('Document deleted.', 'Deleted', variant: 'success');
    }

    public function documentUrl(string $path): string
    {
        return StorageService::publicUrl($path);
    }
}; ?>

<div>
    <flux:card class="w-full">
        <div class="flex items-center justify-between gap-3 flex-wrap">
            <div class="flex items-center gap-2 flex-wrap">
                <flux:heading size="md">Background Checks</flux:heading>
                @if($this->renewalBadge)
                    <flux:badge size="sm" color="{{ $this->renewalBadge['color'] }}">{{ $this->renewalBadge['label'] }}</flux:badge>
                @endif
            </div>
            @if($canManage)
                <flux:button size="sm" icon="plus" wire:click="openAddCheckModal()">Add Check</flux:button>
            @endif
        </div>

        <flux:separator variant="subtle" class="my-2" />

        @if($this->user->backgroundChecks->isEmpty())
            <flux:text variant="subtle" class="text-center py-4">No background check records on file.</flux:text>
        @else
            <div class="space-y-4">
                @foreach($this->user->backgroundChecks as $check)
                    <div wire:key="bg-check-{{ $check->id }}" class="p-3 rounded-lg border border-zinc-200 dark:border-zinc-700">
                        {{-- Check header --}}
                        <div class="flex items-center gap-2 flex-wrap">
                            <flux:badge size="sm" color="{{ $check->status->color() }}">{{ $check->status->label() }}</flux:badge>
                            <flux:text class="text-sm font-medium">{{ $check->service }}</flux:text>
                            <flux:text variant="subtle" class="text-xs">{{ $check->completed_date->format('M j, Y') }}</flux:text>
                            <flux:text variant="subtle" class="text-xs">· Run by {{ $check->runByUser->name }}</flux:text>
                            @if($check->isLocked())
                                <flux:icon name="lock-closed" class="w-3.5 h-3.5 text-zinc-400 shrink-0" title="Record locked" />
                            @endif
                        </div>

                        {{-- Notes display --}}
                        @if($check->notes)
                            <div class="mt-2 pl-3 border-l-2 border-zinc-200 dark:border-zinc-600">
                                <flux:text class="text-xs whitespace-pre-line text-zinc-600 dark:text-zinc-400">{{ $check->notes }}</flux:text>
                            </div>
                        @endif

                        {{-- Documents --}}
                        @if($check->documents->isNotEmpty())
                            <div class="mt-2 space-y-1">
                                @foreach($check->documents as $doc)
                                    <div wire:key="doc-{{ $doc->id }}" class="flex items-center gap-2">
                                        <flux:icon name="document" class="w-4 h-4 text-zinc-400 shrink-0" />
                                        <flux:link href="{{ $this->documentUrl($doc->path) }}" target="_blank" class="text-sm truncate">{{ $doc->original_filename }}</flux:link>
                                        @if($canManage && ! $check->isLocked())
                                            <flux:button
                                                size="xs"
                                                variant="ghost"
                                                icon="trash"
                                                wire:click="deleteDocument({{ $doc->id }})"
                                                wire:confirm="Delete this document? This cannot be undone."
                                                class="text-red-500 hover:text-red-700 shrink-0"
                                            />
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        @endif

                        {{-- Manage actions --}}
                        @if($canManage)
                            <div class="mt-3 pt-3 border-t border-zinc-100 dark:border-zinc-700 space-y-2">
                                {{-- Status update (non-terminal only) --}}
                                @if(! $check->isLocked())
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <flux:text class="text-xs text-zinc-500 shrink-0">Set status:</flux:text>
                                        @foreach(\App\Enums\BackgroundCheckStatus::cases() as $status)
                                            @if($status !== $check->status)
                                                <flux:button
                                                    size="xs"
                                                    variant="ghost"
                                                    wire:click="updateStatus({{ $check->id }}, '{{ $status->value }}')"
                                                    wire:confirm="Set status to {{ $status->label() }}?"
                                                >{{ $status->label() }}</flux:button>
                                            @endif
                                        @endforeach
                                    </div>
                                @endif

                                {{-- Note form --}}
                                @if($noteCheckId === $check->id)
                                    <div class="space-y-2">
                                        <flux:textarea wire:model="noteText" rows="2" placeholder="Enter note..." />
                                        <div class="flex gap-2">
                                            <flux:button size="sm" variant="primary" wire:click="submitNote()">Save Note</flux:button>
                                            <flux:button size="sm" variant="ghost" wire:click="cancelNote()">Cancel</flux:button>
                                        </div>
                                    </div>
                                @else
                                    <div class="flex items-center gap-2">
                                        <flux:button size="xs" variant="ghost" icon="chat-bubble-left" wire:click="openNoteForm({{ $check->id }})">Add Note</flux:button>

                                        {{-- Upload button (only when upload form not open for this check) --}}
                                        @if($uploadCheckId !== $check->id)
                                            <flux:button size="xs" variant="ghost" icon="arrow-up-tray" wire:click="openUploadForm({{ $check->id }})">Upload PDF</flux:button>
                                        @endif
                                    </div>
                                @endif

                                {{-- Upload form --}}
                                @if($uploadCheckId === $check->id)
                                    <div class="space-y-2">
                                        <input
                                            type="file"
                                            wire:model="pendingDocuments"
                                            multiple
                                            accept=".pdf,application/pdf"
                                            class="block w-full text-sm text-zinc-500 dark:text-zinc-400 file:mr-4 file:py-1 file:px-3 file:rounded file:border-0 file:text-xs file:font-medium file:bg-zinc-100 dark:file:bg-zinc-700 file:text-zinc-700 dark:file:text-zinc-300"
                                        />
                                        <div class="flex gap-2">
                                            <flux:button size="sm" variant="primary" wire:click="submitDocuments()" wire:loading.attr="disabled" wire:target="pendingDocuments">Upload</flux:button>
                                            <flux:button size="sm" variant="ghost" wire:click="cancelUpload()">Cancel</flux:button>
                                        </div>
                                    </div>
                                @endif
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </flux:card>

    {{-- Add Check Modal --}}
    @if($canManage)
        <flux:modal name="add-bg-check-modal-{{ $userId }}" class="w-full md:max-w-lg">
            <div class="space-y-4">
                <flux:heading size="lg">Add Background Check</flux:heading>

                <flux:field>
                    <flux:label>Service / Provider</flux:label>
                    <flux:input wire:model="newService" placeholder="e.g., Checkr, Sterling" />
                    <flux:error name="newService" />
                </flux:field>

                <flux:field>
                    <flux:label>Completed Date</flux:label>
                    <flux:input type="date" wire:model="newCompletedDate" :max="date('Y-m-d')" />
                    <flux:error name="newCompletedDate" />
                </flux:field>

                <flux:field>
                    <flux:label>Initial Notes</flux:label>
                    <flux:textarea wire:model="newInitialNotes" rows="3" placeholder="Optional — any initial observations or context" />
                    <flux:error name="newInitialNotes" />
                </flux:field>

                <div class="flex justify-end gap-2">
                    <flux:modal.close>
                        <flux:button variant="ghost">Cancel</flux:button>
                    </flux:modal.close>
                    <flux:button variant="primary" wire:click="submitNewCheck()" wire:loading.attr="disabled" wire:target="submitNewCheck">Add Check</flux:button>
                </div>
            </div>
        </flux:modal>
    @endif
</div>
