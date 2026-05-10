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

    // Update modal (status + note + document combined)
    public ?int $updateCheckId = null;
    public string $pendingStatusValue = '';
    public string $updateNote = '';
    public $pendingDocument = null;

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

    public function openUpdateModal(int $checkId): void
    {
        $this->authorize('background-checks-manage');
        $this->updateCheckId = $checkId;
        $this->pendingStatusValue = '';
        $this->updateNote = '';
        $this->pendingDocument = null;
        Flux::modal('update-bg-check-modal-' . $this->userId)->show();
    }

    public function removeUpdateDocument(): void
    {
        $this->pendingDocument = null;
    }

    public function submitUpdate(): void
    {
        $this->authorize('background-checks-manage');

        $check = BackgroundCheck::findOrFail($this->updateCheckId);
        $auth = Auth::user();
        $acted = false;

        // Status update (only if unlocked and a new status is selected)
        if (! $check->isLocked() && $this->pendingStatusValue !== '') {
            $parsed = BackgroundCheckStatus::tryFrom($this->pendingStatusValue);
            if ($parsed && $parsed !== $check->status) {
                UpdateBackgroundCheckStatus::run($check, $parsed, $auth);
                $check->refresh();
                $acted = true;
            }
        }

        // Note addition
        if (trim($this->updateNote) !== '') {
            $this->validate(['updateNote' => 'required|string|min:1|max:5000']);
            AddBackgroundCheckNote::run($check, $this->updateNote, $auth);
            $acted = true;
        }

        // Document upload
        if ($this->pendingDocument !== null) {
            $this->validate([
                'pendingDocument' => 'nullable|mimes:pdf',
            ]);
            AttachBackgroundCheckDocuments::run($check, [$this->pendingDocument], $auth);
            $acted = true;
        }

        $this->updateCheckId = null;
        $this->pendingStatusValue = '';
        $this->updateNote = '';
        $this->pendingDocument = null;

        Flux::modal('update-bg-check-modal-' . $this->userId)->close();

        if ($acted) {
            Flux::toast('Background check updated.', 'Updated', variant: 'success');
        }
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
                    @php
                        $tz = auth()->user()->timezone ?? 'UTC';
                        $parsedNotes = [];
                        if ($check->notes) {
                            foreach (explode("\n", $check->notes) as $line) {
                                $line = trim($line);
                                if ($line === '') {
                                    continue;
                                }
                                if (preg_match('/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2})\] (.+?): (.+)$/s', $line, $m)) {
                                    $authorName = $m[2];
                                    $initials = collect(explode(' ', $authorName))
                                        ->map(fn ($w) => strtoupper($w[0] ?? ''))
                                        ->take(2)
                                        ->join('');
                                    $parsedNotes[] = [
                                        'type' => 'timestamped',
                                        'timestamp' => \Carbon\Carbon::createFromFormat('Y-m-d H:i', $m[1], 'UTC')->setTimezone($tz)->format('M j, Y g:i A'),
                                        'author' => $authorName,
                                        'initials' => $initials,
                                        'text' => $m[3],
                                    ];
                                } else {
                                    $parsedNotes[] = ['type' => 'plain', 'text' => $line];
                                }
                            }
                        }
                    @endphp
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

                        {{-- Notes display as chat bubbles --}}
                        @if(count($parsedNotes) > 0)
                            <div class="mt-3 space-y-2">
                                @foreach($parsedNotes as $note)
                                    @if($note['type'] === 'timestamped')
                                        <div class="chat-message chat-message-start">
                                            <flux:avatar size="xs" :initials="$note['initials']" class="shrink-0 mt-1" />
                                            <div class="min-w-0">
                                                <div class="flex items-baseline gap-2 mb-1">
                                                    <span class="font-semibold text-xs text-zinc-700 dark:text-zinc-300">{{ $note['author'] }}</span>
                                                    <span class="text-xs text-zinc-400 dark:text-zinc-500">{{ $note['timestamp'] }}</span>
                                                </div>
                                                <div class="chat-bubble chat-bubble-start bg-zinc-100 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700">
                                                    <div class="text-sm text-zinc-700 dark:text-zinc-300">{{ $note['text'] }}</div>
                                                </div>
                                            </div>
                                        </div>
                                    @else
                                        <div class="pl-3 border-l-2 border-zinc-200 dark:border-zinc-600">
                                            <flux:text class="text-xs whitespace-pre-line text-zinc-600 dark:text-zinc-400">{{ $note['text'] }}</flux:text>
                                        </div>
                                    @endif
                                @endforeach
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
                            <div class="mt-3 pt-3 border-t border-zinc-100 dark:border-zinc-700">
                                <flux:button size="xs" variant="ghost" icon="pencil-square" wire:click="openUpdateModal({{ $check->id }})">Update Background Check</flux:button>
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

        {{-- Update Check Modal --}}
        <flux:modal name="update-bg-check-modal-{{ $userId }}" class="w-full md:max-w-lg">
            @php $updatingCheck = $updateCheckId ? $this->user->backgroundChecks->firstWhere('id', $updateCheckId) : null; @endphp
            @if($updatingCheck)
                <div class="space-y-4">
                    <div>
                        <flux:heading size="lg">Update Background Check</flux:heading>
                        <flux:text variant="subtle" class="text-sm mt-1">{{ $updatingCheck->service }} · {{ $updatingCheck->completed_date->format('M j, Y') }}</flux:text>
                    </div>

                    {{-- Status update (only for unlocked checks) --}}
                    @if(! $updatingCheck->isLocked())
                        <flux:field>
                            <flux:label>Change Status</flux:label>
                            <flux:select wire:model="pendingStatusValue" placeholder="No change">
                                <flux:select.option value="">No change</flux:select.option>
                                @foreach(\App\Enums\BackgroundCheckStatus::cases() as $status)
                                    @if($status !== $updatingCheck->status)
                                        <flux:select.option value="{{ $status->value }}">{{ $status->label() }}</flux:select.option>
                                    @endif
                                @endforeach
                            </flux:select>
                        </flux:field>
                    @else
                        <flux:callout icon="lock-closed" color="zinc">
                            <flux:callout.heading>Status Locked</flux:callout.heading>
                            <flux:callout.text>This record has a terminal status and cannot be changed.</flux:callout.text>
                        </flux:callout>
                    @endif

                    {{-- Note --}}
                    <flux:field>
                        <flux:label>Add a Note</flux:label>
                        <flux:textarea wire:model="updateNote" rows="3" placeholder="Optional — leave a note on this check record" />
                        <flux:error name="updateNote" />
                    </flux:field>

                    {{-- Document upload --}}
                    <flux:field>
                        <flux:label>Attach PDF Document</flux:label>
                        <flux:file-upload wire:model="pendingDocument" label="PDF document (optional)">
                            <flux:file-upload.dropzone
                                heading="Drop a PDF here or click to browse"
                                text="PDF files only"
                            />
                        </flux:file-upload>
                        @if($pendingDocument)
                            <flux:file-item
                                :heading="$pendingDocument->getClientOriginalName()"
                                :size="$pendingDocument->getSize()"
                            >
                                <x-slot name="actions">
                                    <flux:file-item.remove wire:click="removeUpdateDocument" />
                                </x-slot>
                            </flux:file-item>
                        @endif
                        <flux:error name="pendingDocument" />
                    </flux:field>

                    <div class="flex justify-end gap-2">
                        <flux:modal.close>
                            <flux:button variant="ghost">Cancel</flux:button>
                        </flux:modal.close>
                        <flux:button variant="primary" wire:click="submitUpdate()" wire:loading.attr="disabled" wire:target="submitUpdate">Save Changes</flux:button>
                    </div>
                </div>
            @endif
        </flux:modal>
    @endif
</div>
