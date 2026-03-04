<?php

use App\Actions\CreateBoardMember;
use App\Actions\UpdateBoardMember;
use App\Actions\DeleteBoardMember;
use App\Actions\LinkBoardMemberToUser;
use App\Actions\UnlinkBoardMemberFromUser;
use App\Models\BoardMember;
use App\Models\User;
use Flux\Flux;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Computed;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new class extends Component {
    use WithFileUploads;

    // Create form
    public string $newDisplayName = '';
    public string $newTitle = '';
    public string $newBio = '';
    public $newPhoto;
    public int $newSortOrder = 0;

    // Edit form
    public ?int $editId = null;
    public string $editDisplayName = '';
    public string $editTitle = '';
    public string $editBio = '';
    public $editPhoto;
    public int $editSortOrder = 0;
    public bool $editIsLinked = false;

    // Link user
    public ?int $linkBoardMemberId = null;
    public ?int $linkUserId = null;
    public string $userSearch = '';

    #[Computed]
    public function boardMembers()
    {
        $this->authorize('viewAny', BoardMember::class);

        return BoardMember::with('user')->ordered()->get();
    }

    public function createBoardMember(): void
    {
        $this->authorize('create', BoardMember::class);

        $this->validate([
            'newDisplayName' => 'required|string|max:255',
            'newTitle' => 'nullable|string|max:255',
            'newBio' => 'nullable|string|max:2000',
            'newPhoto' => 'nullable|image|max:2048',
            'newSortOrder' => 'required|integer|min:0',
        ]);

        $photoPath = null;
        if ($this->newPhoto) {
            $photoPath = $this->newPhoto->store('board-member-photos', 'public');
        }

        CreateBoardMember::run(
            displayName: $this->newDisplayName,
            title: $this->newTitle ?: null,
            bio: $this->newBio ?: null,
            photoPath: $photoPath,
            sortOrder: $this->newSortOrder,
        );

        Flux::modal('create-board-member-modal')->close();
        Flux::toast('Board member created.', 'Created', variant: 'success');
        $this->reset(['newDisplayName', 'newTitle', 'newBio', 'newPhoto', 'newSortOrder']);
    }

    public function openEditModal(int $id): void
    {
        $boardMember = BoardMember::findOrFail($id);
        $this->authorize('update', $boardMember);

        $this->editId = $id;
        $this->editDisplayName = $boardMember->display_name;
        $this->editTitle = $boardMember->title ?? '';
        $this->editBio = $boardMember->bio ?? '';
        $this->editSortOrder = $boardMember->sort_order;
        $this->editIsLinked = $boardMember->isLinked();
        $this->editPhoto = null;
    }

    public function updateBoardMember(): void
    {
        $boardMember = BoardMember::findOrFail($this->editId);
        $this->authorize('update', $boardMember);

        $this->validate([
            'editDisplayName' => 'required|string|max:255',
            'editTitle' => 'nullable|string|max:255',
            'editBio' => 'nullable|string|max:2000',
            'editPhoto' => 'nullable|image|max:2048',
            'editSortOrder' => 'required|integer|min:0',
        ]);

        $photoPath = $boardMember->photo_path;
        if ($this->editPhoto) {
            $newPath = $this->editPhoto->store('board-member-photos', 'public');
            if ($newPath) {
                if ($boardMember->photo_path) {
                    Storage::disk('public')->delete($boardMember->photo_path);
                }
                $photoPath = $newPath;
            }
        }

        UpdateBoardMember::run(
            boardMember: $boardMember,
            displayName: $this->editDisplayName,
            title: $this->editTitle ?: null,
            bio: $this->editBio ?: null,
            photoPath: $photoPath,
            sortOrder: $this->editSortOrder,
        );

        Flux::modal('edit-board-member-modal')->close();
        Flux::toast('Board member updated.', 'Updated', variant: 'success');
        $this->reset(['editId', 'editDisplayName', 'editTitle', 'editBio', 'editPhoto', 'editSortOrder', 'editIsLinked']);
    }

    public function deleteBoardMember(int $id): void
    {
        $boardMember = BoardMember::findOrFail($id);
        $this->authorize('delete', $boardMember);

        DeleteBoardMember::run($boardMember);
        Flux::toast('Board member deleted.', 'Deleted', variant: 'success');
    }

    public function openLinkModal(int $id): void
    {
        $boardMember = BoardMember::findOrFail($id);
        $this->authorize('update', $boardMember);

        $this->linkBoardMemberId = $id;
        $this->linkUserId = null;
        $this->userSearch = '';
    }

    public function getSearchedUsersProperty()
    {
        if (strlen(trim($this->userSearch)) < 2) {
            return collect();
        }

        return User::where('name', 'like', '%'.trim($this->userSearch).'%')
            ->whereNotIn('id', BoardMember::whereNotNull('user_id')->pluck('user_id'))
            ->limit(10)
            ->get(['id', 'name']);
    }

    public function selectUser(int $userId): void
    {
        $this->linkUserId = $userId;
    }

    public function linkUser(): void
    {
        $boardMember = BoardMember::findOrFail($this->linkBoardMemberId);
        $this->authorize('update', $boardMember);

        $this->validate([
            'linkUserId' => 'required|exists:users,id',
        ]);

        $user = User::findOrFail($this->linkUserId);
        LinkBoardMemberToUser::run($boardMember, $user);

        Flux::modal('link-user-modal')->close();
        Flux::toast("Linked to {$user->name}.", 'Linked', variant: 'success');
        $this->reset(['linkBoardMemberId', 'linkUserId', 'userSearch']);
    }

    public function unlinkUser(int $id): void
    {
        $boardMember = BoardMember::findOrFail($id);
        $this->authorize('update', $boardMember);

        UnlinkBoardMemberFromUser::run($boardMember);
        Flux::toast('User unlinked.', 'Unlinked', variant: 'success');
    }
}; ?>

<div class="space-y-6">
    <flux:heading size="xl">Manage Board Members</flux:heading>

    <flux:table>
        <flux:table.columns>
            <flux:table.column>Sort</flux:table.column>
            <flux:table.column>Name</flux:table.column>
            <flux:table.column>Title</flux:table.column>
            <flux:table.column>Linked User</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
            @foreach($this->boardMembers as $member)
                <flux:table.row wire:key="board-member-{{ $member->id }}">
                    <flux:table.cell>{{ $member->sort_order }}</flux:table.cell>
                    <flux:table.cell class="font-medium">{{ $member->display_name }}</flux:table.cell>
                    <flux:table.cell>{{ $member->title ?? '—' }}</flux:table.cell>
                    <flux:table.cell>
                        @if($member->isLinked())
                            <div class="flex items-center gap-2">
                                <span>{{ $member->user->name }}</span>
                                <flux:button size="xs" variant="ghost" icon="x-mark" wire:click="unlinkUser({{ $member->id }})" wire:confirm="Unlink this user from the board member record?">Unlink</flux:button>
                            </div>
                        @else
                            <flux:modal.trigger name="link-user-modal">
                                <flux:button size="xs" variant="ghost" icon="link" wire:click="openLinkModal({{ $member->id }})">Link User</flux:button>
                            </flux:modal.trigger>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell>
                        <div class="flex gap-1">
                            <flux:modal.trigger name="edit-board-member-modal">
                                <flux:button size="sm" icon="pencil-square" wire:click="openEditModal({{ $member->id }})">Edit</flux:button>
                            </flux:modal.trigger>
                            <flux:button size="sm" icon="trash" variant="ghost" wire:click="deleteBoardMember({{ $member->id }})" wire:confirm="Delete this board member? This cannot be undone.">Delete</flux:button>
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @endforeach
        </flux:table.rows>
    </flux:table>

    <div class="w-full text-right">
        <flux:modal.trigger name="create-board-member-modal">
            <flux:button variant="primary">Add Board Member</flux:button>
        </flux:modal.trigger>
    </div>

    {{-- Create Modal --}}
    <flux:modal name="create-board-member-modal" variant="flyout" class="space-y-6">
        <flux:heading size="xl">Add Board Member</flux:heading>
        <form wire:submit.prevent="createBoardMember">
            <div class="space-y-6">
                <flux:input label="Display Name" wire:model="newDisplayName" required placeholder="e.g. John D." />
                <flux:input label="Title" wire:model="newTitle" placeholder="e.g. Board Chair, Treasurer (optional)" />

                <flux:field>
                    <flux:label>Photo</flux:label>
                    <flux:description>Upload a photo (max 2MB). For linked users, their staff photo is used instead.</flux:description>
                    <input type="file" wire:model="newPhoto" accept="image/*" class="block w-full text-sm text-zinc-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 dark:file:bg-blue-950 dark:file:text-blue-300" />
                    @error('newPhoto') <flux:error>{{ $message }}</flux:error> @enderror
                </flux:field>

                <flux:textarea label="Bio" wire:model="newBio" rows="4" placeholder="Brief introduction..." />
                <flux:input label="Sort Order" wire:model="newSortOrder" type="number" min="0" />

                <flux:button type="submit" variant="primary">Create</flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- Edit Modal --}}
    <flux:modal name="edit-board-member-modal" variant="flyout" class="space-y-6">
        <flux:heading size="xl">Edit Board Member</flux:heading>
        <form wire:submit.prevent="updateBoardMember">
            <div class="space-y-6">
                <flux:input label="Display Name" wire:model="editDisplayName" required />
                <flux:input label="Title" wire:model="editTitle" placeholder="e.g. Board Chair" />

                @if(! $editIsLinked)
                    <flux:field>
                        <flux:label>Photo</flux:label>
                        <input type="file" wire:model="editPhoto" accept="image/*" class="block w-full text-sm text-zinc-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 dark:file:bg-blue-950 dark:file:text-blue-300" />
                        @error('editPhoto') <flux:error>{{ $message }}</flux:error> @enderror
                    </flux:field>
                    <flux:textarea label="Bio" wire:model="editBio" rows="4" />
                @else
                    <flux:text variant="subtle">Bio and photo are managed by the linked user through their Staff Bio settings.</flux:text>
                @endif

                <flux:input label="Sort Order" wire:model="editSortOrder" type="number" min="0" />
                <flux:button type="submit" variant="primary">Save Changes</flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- Link User Modal --}}
    <flux:modal name="link-user-modal" class="space-y-6">
        <flux:heading size="xl">Link User to Board Member</flux:heading>
        <flux:input label="Search Users" wire:model.live.debounce.300ms="userSearch" placeholder="Type at least 2 characters..." />

        @if($this->searchedUsers->isNotEmpty())
            <div class="space-y-1">
                @foreach($this->searchedUsers as $searchUser)
                    <div wire:key="search-user-{{ $searchUser->id }}" class="flex items-center justify-between p-2 rounded hover:bg-zinc-100 dark:hover:bg-zinc-800 {{ $linkUserId === $searchUser->id ? 'bg-blue-50 dark:bg-blue-950' : '' }}">
                        <span>{{ $searchUser->name }}</span>
                        <flux:button size="xs" wire:click="selectUser({{ $searchUser->id }})">Select</flux:button>
                    </div>
                @endforeach
            </div>
        @endif

        @if($linkUserId)
            <div class="pt-4 border-t border-zinc-200 dark:border-zinc-700">
                <flux:button variant="primary" wire:click="linkUser">Link User</flux:button>
            </div>
        @endif
    </flux:modal>
</div>
