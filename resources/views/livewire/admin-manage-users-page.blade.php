<?php

use App\Enums\StaffDepartment;
use App\Enums\StaffRank;
use App\Models\User;
use Livewire\Volt\Component;
use Illuminate\Support\Facades\Validator;
use Livewire\WithPagination;
use App\Actions\LinkParentByEmail;
use Flux\Flux;


new class extends Component {
    use WithPagination;

    protected const ALLOWED_SORTS = ['name', 'email', 'membership_level', 'staff_department', 'staff_rank', 'in_brig', 'created_at', 'last_login_at', 'risk_score'];

    public $sortBy = 'name';
    public $sortDirection = 'asc';
    public $perPage = 15;
    public $filterBrig = '';
    public $search = '';
    public $editUserId = null;
    public $editUserData = [
        'name' => '',
        'email' => '',
        'date_of_birth' => '',
        'parent_email' => '',
    ];
    public function openEditModal($userId)
    {
        $user = User::findOrFail($userId);
        $this->authorize('update', $user);
        $this->editUserId = $user->id;
        $this->editUserData = [
            'name' => $user->name,
            'email' => $user->email,
            'date_of_birth' => $user->date_of_birth?->format('Y-m-d') ?? '',
            'parent_email' => $user->parent_email ?? '',
        ];
    }

    /**
     * Update the current sort column and direction for the users list, toggling direction when the same column is selected and resetting pagination.
     *
     * @param string $column The column name to sort by.
     */
    public function sort($column) {
        if ($this->sortBy === $column) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $column;
            $this->sortDirection = 'asc';
        }
        $this->resetPage();
    }

    /**
     * Reset the pagination page when the brig filter changes.
     *
     * Triggered by Livewire after the public property `$filterBrig` is updated to ensure the listing
     * returns to the first page.
     */
    public function updatedFilterBrig()
    {
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    /**
     * Get a paginated list of users filtered, sorted, and paginated based on the component state.
     *
     * Optionally filtered by the `filterBrig` value
     * (`'in_brig'` to include only users with `in_brig = true`, `'not_brig'` to include only `in_brig = false`),
     * ordered by `sortBy` and `sortDirection` when `sortBy` is set, and paginated using `perPage`.
     *
     * @return \Illuminate\Pagination\LengthAwarePaginator<PersistentModel> A paginator of User models.
     */
    #[\Livewire\Attributes\Computed]
    public function users()
    {
        $sortColumn = in_array($this->sortBy, self::ALLOWED_SORTS) ? $this->sortBy : 'name';
        $sortDir = $this->sortDirection === 'desc' ? 'desc' : 'asc';

        $cutoff = now()->subDays(90);

        return \App\Models\User::query()
            ->select('users.*')
            // Point values must match ReportSeverity::points()
            ->addSelect([
                'risk_score' => \App\Models\DisciplineReport::selectRaw("COALESCE(SUM(
                    CASE severity
                        WHEN 'commendation' THEN 0
                        WHEN 'trivial' THEN 1
                        WHEN 'minor' THEN 2
                        WHEN 'moderate' THEN 4
                        WHEN 'major' THEN 7
                        WHEN 'severe' THEN 10
                        ELSE 0
                    END
                ), 0)")
                    ->whereColumn('discipline_reports.subject_user_id', 'users.id')
                    ->where('discipline_reports.status', 'published')
                    ->where('discipline_reports.published_at', '>=', $cutoff),
            ])
            ->when(trim($this->search) !== '', function ($q) {
                $term = mb_strtolower(trim($this->search));
                $q->where(fn ($q) =>
                    $q->whereRaw('LOWER(name) like ?', ["%{$term}%"])
                      ->orWhereRaw('LOWER(email) like ?', ["%{$term}%"])
                );
            })
            ->when($this->filterBrig === 'in_brig', fn ($q) => $q->where('in_brig', true))
            ->when($this->filterBrig === 'not_brig', fn ($q) => $q->where('in_brig', false))
            ->orderBy($sortColumn, $sortDir)
            ->paginate($this->perPage);
    }

    /**
     * Retrieve all role records.
     *
     * @return \Illuminate\Database\Eloquent\Collection|\App\Models\Role[] Collection of all Role models.
     */
    public function roles()
    {
        return \App\Models\Role::all();
    }

    public function editUser($userId)
    {
        $user = User::findOrFail($userId);
        $this->authorize('update', $user);

        $this->editUserId = $userId;
        $this->editUserData = $user->only(['name', 'email']);
    }

    /**
     * Validate the edit form, persist user changes, and close the edit modal.
     *
     * @throws \Illuminate\Validation\ValidationException If validation fails.
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException If the target user cannot be found.
     */
    public function saveUser()
    {
        Validator::make([
            'name' => $this->editUserData['name'],
            'email' => $this->editUserData['email'],
            'date_of_birth' => $this->editUserData['date_of_birth'] ?: null,
            'parent_email' => $this->editUserData['parent_email'] ?: null,
        ], [
            'name' => 'required|string|max:255',
            'email' => 'required|email',
            'date_of_birth' => 'nullable|date|before:today',
            'parent_email' => 'nullable|email',
        ])->validate();

        $user = User::findOrFail($this->editUserId);
        $this->authorize('update', $user);

        $updateData = $this->editUserData;
        $updateData['date_of_birth'] = $updateData['date_of_birth'] ?: null;
        $updateData['parent_email'] = $updateData['parent_email'] ?: null;
        // Note: Admin DOB edits are a raw override — age-dependent brig states
        // (AgeLock, ParentalPending) are not automatically re-evaluated here.
        // Use the scheduled ProcessAgeTransitions command for automated transitions.
        $user->update($updateData);

        if ($updateData['parent_email']) {
            LinkParentByEmail::run($user);
        }

        $this->editUserId = null;
        Flux::modal('edit-user-modal')->close();
        Flux::toast('User updated successfully!', 'Success', variant: 'success');
    }
};
?>

<div class="space-y-6">
    <flux:heading size="xl">Manage Users</flux:heading>
    <div class="flex items-center gap-4">
        <flux:input wire:model.live.debounce.300ms="search" placeholder="Search users..." icon="magnifying-glass" class="max-w-sm" />
        <flux:select wire:model.live="filterBrig" size="sm" class="w-48">
            <flux:select.option value="">All Users</flux:select.option>
            <flux:select.option value="in_brig">In the Brig</flux:select.option>
            <flux:select.option value="not_brig">Not in Brig</flux:select.option>
        </flux:select>
    </div>

    <flux:table :paginate="$this->users">
        <flux:table.columns>
            <flux:table.column sortable :sorted="$sortBy === 'name'" :direction="$sortDirection" wire:click="sort('name')">Name</flux:table.column>
            <flux:table.column sortable :sorted="$sortBy === 'email'" :direction="$sortDirection" wire:click="sort('email')">Email</flux:table.column>
            <flux:table.column sortable :sorted="$sortBy === 'membership_level'" :direction="$sortDirection" wire:click="sort('membership_level')">Level</flux:table.column>
            <flux:table.column sortable :sorted="$sortBy === 'staff_department'" :direction="$sortDirection" wire:click="sort('staff_department')">Department</flux:table.column>
            <flux:table.column sortable :sorted="$sortBy === 'staff_rank'" :direction="$sortDirection" wire:click="sort('staff_rank')">Rank</flux:table.column>
            <flux:table.column sortable :sorted="$sortBy === 'in_brig'" :direction="$sortDirection" wire:click="sort('in_brig')">Brig</flux:table.column>
            <flux:table.column sortable :sorted="$sortBy === 'risk_score'" :direction="$sortDirection" wire:click="sort('risk_score')">Risk Score</flux:table.column>
            <flux:table.column>Staff Title</flux:table.column>
            <flux:table.column>Admin</flux:table.column>
            <flux:table.column>Actions</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @foreach ($this->users as $user)
                <flux:table.row wire:key="user-{{ $user->id }}" :key="$user->id">
                    <flux:table.cell class="flex items-center gap-3">
                        <flux:link href="{{ route('profile.show', $user) }}">{{ $user->name }}</flux:link>
                    </flux:table.cell>

                    <flux:table.cell class="whitespace-nowrap">{{ $user->email }}</flux:table.cell>
                    <flux:table.cell class="whitespace-nowrap">{{ $user->membership_level->label() }}</flux:table.cell>
                    <flux:table.cell class="whitespace-nowrap">@if ($user->staff_department){{ $user->staff_department->label() }} @endif</flux:table.cell>
                    <flux:table.cell class="whitespace-nowrap">@if ($user->staff_rank != StaffRank::None)<flux:badge variant="pill" color="{{ $user->staff_rank->color() }}" size="sm">{{ $user->staff_rank->label() }}</flux:badge> @endif</flux:table.cell>
                    <flux:table.cell class="whitespace-nowrap">
                        @if($user->in_brig)
                            <flux:badge color="red" size="sm">In Brig</flux:badge>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell class="whitespace-nowrap">
                        @if($user->risk_score > 0)
                            <flux:badge color="{{ \App\Models\User::riskScoreColor((int) $user->risk_score) }}" size="sm">{{ (int) $user->risk_score }}</flux:badge>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell class="whitespace-nowrap">{{ $user->staff_title }}</flux:table.cell>
                    <flux:table.cell class="whitespace-nowrap">
                        @if($user->isAdmin())
                            <flux:badge size="xs" color="red" icon="shield-check" variant="pill">Admin</flux:badge>
                        @endif
                    </flux:table.cell>

                    <flux:table.cell>
                        @can('update', $user)
                            <flux:modal.trigger name="edit-user-modal" wire:click="openEditModal({{ $user->id }})">
                                <flux:button size="xs" icon="pencil-square"></flux:button>
                            </flux:modal.trigger>
                        @endcan
                    </flux:table.cell>
                </flux:table.row>

            @endforeach
        </flux:table.rows>
    </flux:table>

    <!-- Edit User Modal -->
    <flux:modal name="edit-user-modal" title="Edit User" variant="flyout">
        <div class="space-y-6">
            <flux:heading size="xl">Edit User</flux:heading>
                <form wire:submit.prevent="saveUser">
                    <div class="space-y-6">
                        <flux:input label="Name" wire:model.defer="editUserData.name" required />
                        <flux:input label="Email" type="email" wire:model.defer="editUserData.email" required />
                        <flux:input label="Date of Birth" type="date" wire:model.defer="editUserData.date_of_birth" />
                        <flux:input label="Parent Email" type="email" wire:model.defer="editUserData.parent_email" placeholder="Optional" />

                        <div class="flex">
                            <flux:spacer />
                            <flux:button type="submit" variant="primary">Save</flux:button>
                        </div>
                    </div>
                </form>
        </div>
    </flux:modal>
</div>