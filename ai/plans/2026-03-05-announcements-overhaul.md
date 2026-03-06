# Plan: Announcements System Overhaul

**Date**: 2026-03-05
**Planned by**: Claude Code
**Status**: PENDING APPROVAL

## Context

The announcements system has accumulated dead code (Tags, Categories, Comments — none were ever implemented in the UI), uses dedicated pages where modals would be cleaner, cycles through all unacknowledged announcements on the dashboard banner instead of showing only the newest, and stores HTML from `flux:editor` instead of markdown (which the rest of the site uses for user-generated content, e.g. ticket messages). This overhaul simplifies the system, removes dead code, switches to markdown, adds an expiration feature, and adds user-configurable announcement notifications.

## Summary

1. **Remove dead code**: Tags, Categories, Comments models/tables/components
2. **Add `expired_at`**: Auto-expire announcements after a date/time
3. **Admin CRUD → flyout modals**: Replace dedicated create/edit pages with inline modals in ACP
4. **Switch to markdown**: Replace `flux:editor` with `flux:textarea`, render with `Str::markdown()`
5. **Dashboard banner**: Show only newest unacknowledged announcement; disappears after acknowledging
6. **Dashboard widget**: Use modal for viewing instead of linking to dedicated show page
7. **Remove show page**: No more dedicated announcement detail page
8. **Add notifications**: New `announcements` category in notification preferences + `NewAnnouncementNotification` + background Job for sending

## Files to Read (for implementing agent context)

- `CLAUDE.md`
- `ai/CONVENTIONS.md`
- `ai/ARCHITECTURE.md`
- `app/Models/Announcement.php`
- `app/Actions/AcknowledgeAnnouncement.php`
- `app/Services/TicketNotificationService.php`
- `app/Notifications/NewTicketNotification.php` (notification pattern)
- `resources/views/livewire/admin-manage-staff-positions-page.blade.php` (flyout modal pattern)
- `resources/views/livewire/settings/notifications.blade.php` (notification prefs UI pattern)
- `resources/views/livewire/ready-room/tickets/view-ticket.blade.php` line 614-616 (markdown rendering pattern)

## Authorization Rules

No changes needed. Existing `AnnouncementPolicy` already covers all CRUD + acknowledge operations. The `before()` hook grants admin/command bypass.

## Database Changes

| Migration file | Table | Change |
|---|---|---|
| `YYYY_MM_DD_HHmmss_add_expired_at_and_cleanup_announcements.php` | `announcements` | Add `expired_at` column |
| Same migration | `comments` | Drop table |
| Same migration | `tags` | Drop table |
| Same migration | `categories` | Drop table |
| Same migration | `announcement_tag` | Drop table |
| Same migration | `announcement_category` | Drop table |

Column details:
- `expired_at` — nullable timestamp. When set and in the past, the announcement is treated as unpublished by `scopePublished()`.
- `notifications_sent_at` — nullable timestamp. Tracks whether notifications have been dispatched for this announcement. Set when the `SendAnnouncementNotifications` job is dispatched. Used to detect newly-live announcements that need notification.

**Note**: Existing announcement content is HTML from `flux:editor`. After this migration, old announcements will display raw HTML when rendered as markdown. These should be manually re-edited by an admin — there are likely very few announcements in the database.

---

## Implementation Steps

### Step 1: Migration — Add `expired_at`, drop dead tables
**File**: `database/migrations/YYYY_MM_DD_HHmmss_add_expired_at_and_cleanup_announcements.php`
**Action**: Create

```php
public function up(): void
{
    Schema::table('announcements', function (Blueprint $table) {
        $table->timestamp('expired_at')->nullable()->after('published_at');
        $table->timestamp('notifications_sent_at')->nullable()->after('expired_at');
    });

    Schema::dropIfExists('announcement_tag');
    Schema::dropIfExists('announcement_category');
    Schema::dropIfExists('comments');
    Schema::dropIfExists('tags');
    Schema::dropIfExists('categories');
}

public function down(): void
{
    Schema::table('announcements', function (Blueprint $table) {
        $table->dropColumn(['expired_at', 'notifications_sent_at']);
    });

    // Recreate dropped tables in down() for rollback
    Schema::create('tags', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('description')->nullable();
        $table->string('color')->nullable();
        $table->foreignId('created_by')->nullable();
        $table->boolean('is_active')->default(true);
        $table->timestamps();
    });

    Schema::create('categories', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('description')->nullable();
        $table->string('color')->nullable();
        $table->foreignId('created_by')->nullable();
        $table->boolean('is_active')->default(true);
        $table->foreignId('parent_id')->nullable()->constrained('categories')->onDelete('set null');
        $table->timestamps();
    });

    Schema::create('comments', function (Blueprint $table) {
        $table->id();
        $table->text('content');
        $table->foreignId('announcement_id')->constrained()->onDelete('cascade');
        $table->foreignId('user_id')->constrained()->onDelete('cascade');
        $table->string('status')->default('approved');
        $table->foreignId('parent_id')->nullable();
        $table->timestamp('edited_at')->nullable();
        $table->timestamps();
    });

    Schema::create('announcement_tag', function (Blueprint $table) {
        $table->id();
        $table->foreignId('announcement_id')->constrained()->onDelete('cascade');
        $table->foreignId('tag_id')->constrained()->onDelete('cascade');
        $table->timestamps();
    });

    Schema::create('announcement_category', function (Blueprint $table) {
        $table->id();
        $table->foreignId('announcement_id')->constrained()->onDelete('cascade');
        $table->foreignId('category_id')->constrained()->onDelete('cascade');
        $table->timestamps();
    });
}
```

Run: `php artisan migrate`

---

### Step 2: Delete dead model files
**Action**: Delete these files:
- `app/Models/Tag.php`
- `app/Models/Category.php`
- `app/Models/Comment.php`

Also delete any factories if they exist:
- `database/factories/TagFactory.php`
- `database/factories/CategoryFactory.php`
- `database/factories/CommentFactory.php`

---

### Step 3: Clean up Announcement model
**File**: `app/Models/Announcement.php`
**Action**: Modify — rewrite to this:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Announcement extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'content',
        'author_id',
        'is_published',
        'published_at',
        'expired_at',
        'notifications_sent_at',
    ];

    protected $table = 'announcements';

    protected $with = ['author'];

    protected $casts = [
        'is_published' => 'boolean',
        'published_at' => 'datetime',
        'expired_at' => 'datetime',
        'notifications_sent_at' => 'datetime',
    ];

    // -------------------- Relationships --------------------

    public function author()
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function acknowledgers()
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }

    // -------------------- Scopes --------------------

    /**
     * Published, not future-scheduled, and not expired.
     */
    public function scopePublished($query)
    {
        return $query->where('is_published', true)
            ->where(function ($q) {
                $q->whereNull('published_at')
                    ->orWhere('published_at', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('expired_at')
                    ->orWhere('expired_at', '>', now());
            });
    }

    /**
     * Expired: is_published but expired_at is in the past.
     */
    public function scopeExpired($query)
    {
        return $query->where('is_published', true)
            ->whereNotNull('expired_at')
            ->where('expired_at', '<=', now());
    }

    // -------------------- Helpers --------------------

    public function isExpired(): bool
    {
        return $this->expired_at !== null && $this->expired_at->isPast();
    }

    public function isAuthoredBy(User $user): bool
    {
        return $this->author_id === $user->id;
    }

    public function authorName(): string
    {
        return $this->author ? $this->author->name : 'Unknown Author';
    }
}
```

**What changed**:
- Removed `$with` entries for comments, tags, categories
- Removed relationships: `comments()`, `tags()`, `categories()`
- Removed scopes: `scopePublishedAt`, `scopeByAuthor`, `scopeWithCategory`, `scopeWithTag`
- Removed methods: `excerpt()`, `route()`, `tagsAsString()`, `categoriesAsString()`, `commentsCount()`, `categoriesCount()`, `tagsCount()`, `publicationDate()`
- Added: `expired_at` to fillable/casts, `scopeExpired()`, `isExpired()`
- Updated: `scopePublished()` now respects `published_at` (scheduled) and `expired_at` (expiration)

---

### Step 4: Update AnnouncementFactory
**File**: `database/factories/AnnouncementFactory.php`
**Action**: Modify

Remove references to tags/categories in any states. Add `expired_at` support:

```php
// Add to definition() return array:
'expired_at' => null,

// Add new state:
public function expiredAt(\DateTimeInterface $date): static
{
    return $this->state(fn (array $attributes) => [
        'expired_at' => $date,
    ]);
}

public function expired(): static
{
    return $this->state(fn (array $attributes) => [
        'is_published' => true,
        'published_at' => now()->subDays(7),
        'expired_at' => now()->subDay(),
    ]);
}
```

---

### Step 5: Delete dead Livewire sub-components
**Action**: Delete these files:
- `resources/views/livewire/announcements/categories.blade.php`
- `resources/views/livewire/announcements/tags.blade.php`
- `resources/views/livewire/announcements/comments.blade.php`

---

### Step 6: Delete dedicated announcement pages and controller
**Action**: Delete these files:
- `resources/views/livewire/announcements/show.blade.php`
- `resources/views/livewire/announcements/create.blade.php`
- `resources/views/livewire/announcements/edit.blade.php`
- `resources/views/livewire/announcements/author-info.blade.php`
- `resources/views/livewire/announcements/backup-show.blade.php` (if exists)
- `resources/views/announcements/show.blade.php` (wrapper blade)
- `resources/views/announcements/create.blade.php` (wrapper blade)
- `resources/views/announcements/edit.blade.php` (wrapper blade)
- `app/Http/Controllers/AnnouncementController.php`

---

### Step 7: Remove announcement routes from web.php
**File**: `routes/web.php`
**Action**: Modify — remove the following route groups:

Remove the ACP announcement routes (prefix `acp/announcements`) and the public announcement routes (prefix `announcements`). These are lines ~67-78 and ~97-104 in the current file. Keep all other routes intact.

---

### Step 8: Rewrite admin manage announcements page with flyout modals
**File**: `resources/views/livewire/admin-manage-announcements-page.blade.php`
**Action**: Modify — full rewrite

Follow the exact pattern from `admin-manage-staff-positions-page.blade.php` (create/edit flyout modals).

```php
<?php

use App\Models\Announcement;
use Flux\Flux;
use Illuminate\Support\Str;
use Livewire\WithPagination;
use Livewire\Volt\Component;

new class extends Component {
    use WithPagination;

    public string $sortBy = 'created_at';
    public string $sortDirection = 'desc';

    // Create form
    public string $newTitle = '';
    public string $newContent = '';
    public bool $newIsPublished = false;
    public ?string $newPublishedAt = null;
    public ?string $newExpiredAt = null;

    // Edit form
    public ?int $editId = null;
    public string $editTitle = '';
    public string $editContent = '';
    public bool $editIsPublished = false;
    public ?string $editPublishedAt = null;
    public ?string $editExpiredAt = null;
    // Preview
    public bool $showCreatePreview = false;
    public bool $showEditPreview = false;

    public function sort(string $column): void
    {
        if ($this->sortBy === $column) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $column;
            $this->sortDirection = 'asc';
        }
    }

    public function getAnnouncementsProperty()
    {
        return Announcement::orderBy($this->sortBy, $this->sortDirection)
            ->with(['author.roles', 'author.minecraftAccounts', 'author.discordAccounts'])
            ->paginate(10);
    }

    public function createAnnouncement(): void
    {
        $this->authorize('create', Announcement::class);

        $this->validate([
            'newTitle' => 'required|string|max:255',
            'newContent' => 'required|string|max:10000',
            'newIsPublished' => 'boolean',
            'newPublishedAt' => 'nullable|date',
            'newExpiredAt' => 'nullable|date|after:newPublishedAt',
        ]);

        $announcement = Announcement::create([
            'title' => $this->newTitle,
            'content' => $this->newContent,
            'author_id' => auth()->id(),
            'is_published' => $this->newIsPublished,
            'published_at' => $this->newIsPublished ? ($this->newPublishedAt ?? now()) : $this->newPublishedAt,
            'expired_at' => $this->newExpiredAt,
        ]);

        Flux::modal('create-announcement-modal')->close();
        Flux::toast('Announcement created.', 'Created', variant: 'success');
        $this->reset(['newTitle', 'newContent', 'newIsPublished', 'newPublishedAt', 'newExpiredAt', 'showCreatePreview']);
    }

    public function openEditModal(int $id): void
    {
        $announcement = Announcement::findOrFail($id);
        $this->authorize('update', $announcement);

        $this->editId = $id;
        $this->editTitle = $announcement->title;
        $this->editContent = $announcement->content;
        $this->editIsPublished = $announcement->is_published;
        $this->editPublishedAt = $announcement->published_at?->format('Y-m-d\TH:i');
        $this->editExpiredAt = $announcement->expired_at?->format('Y-m-d\TH:i');
        $this->showEditPreview = false;
    }

    public function updateAnnouncement(): void
    {
        $announcement = Announcement::findOrFail($this->editId);
        $this->authorize('update', $announcement);

        $this->validate([
            'editTitle' => 'required|string|max:255',
            'editContent' => 'required|string|max:10000',
            'editIsPublished' => 'boolean',
            'editPublishedAt' => 'nullable|date',
            'editExpiredAt' => 'nullable|date|after:editPublishedAt',
        ]);

        $announcement->update([
            'title' => $this->editTitle,
            'content' => $this->editContent,
            'is_published' => $this->editIsPublished,
            'published_at' => $this->editIsPublished ? ($this->editPublishedAt ?? $announcement->published_at ?? now()) : $this->editPublishedAt,
            'expired_at' => $this->editExpiredAt,
        ]);

        Flux::modal('edit-announcement-modal')->close();
        Flux::toast('Announcement updated.', 'Updated', variant: 'success');
        $this->reset(['editId', 'editTitle', 'editContent', 'editIsPublished', 'editPublishedAt', 'editExpiredAt', 'showEditPreview']);
    }

    public function deleteAnnouncement(int $id): void
    {
        $announcement = Announcement::findOrFail($id);
        $this->authorize('delete', $announcement);

        $announcement->delete();
        Flux::toast('Announcement deleted.', 'Deleted', variant: 'success');
    }

}; ?>

<div class="space-y-6">
    <flux:heading size="xl">Manage Announcements</flux:heading>

    <flux:table :paginate="$this->announcements">
        <flux:table.columns>
            <flux:table.column sortable :sorted="$sortBy === 'title'" :direction="$sortDirection" wire:click="sort('title')">Title</flux:table.column>
            <flux:table.column sortable :sorted="$sortBy === 'author_id'" :direction="$sortDirection" wire:click="sort('author_id')">Author</flux:table.column>
            <flux:table.column sortable :sorted="$sortBy === 'created_at'" :direction="$sortDirection" wire:click="sort('created_at')">Created</flux:table.column>
            <flux:table.column sortable :sorted="$sortBy === 'is_published'" :direction="$sortDirection" wire:click="sort('is_published')">Status</flux:table.column>
            <flux:table.column>Actions</flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
            @forelse($this->announcements as $announcement)
                <flux:table.row wire:key="ann-row-{{ $announcement->id }}">
                    <flux:table.cell class="font-medium">{{ $announcement->title }}</flux:table.cell>
                    <flux:table.cell class="flex items-center gap-2">
                        @if($announcement->author)
                            @if($announcement->author->avatarUrl())
                                <flux:avatar size="xs" src="{{ $announcement->author->avatarUrl() }}" />
                            @endif
                            {{ $announcement->author->name }}
                        @else
                            <flux:text variant="subtle">Unknown</flux:text>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell>{{ $announcement->created_at->diffForHumans() }}</flux:table.cell>
                    <flux:table.cell>
                        @if($announcement->isExpired())
                            <flux:badge size="sm" color="zinc">Expired</flux:badge>
                        @elseif($announcement->is_published)
                            <flux:badge size="sm" color="green">Published</flux:badge>
                        @else
                            <flux:badge size="sm" color="yellow">Draft</flux:badge>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell>
                        <div class="flex gap-1">
                            @can('update', $announcement)
                                <flux:modal.trigger wire:click="openEditModal({{ $announcement->id }})" name="edit-announcement-modal">
                                    <flux:button size="sm" icon="pencil-square">Edit</flux:button>
                                </flux:modal.trigger>
                            @endcan
                            @can('delete', $announcement)
                                <flux:button size="sm" icon="trash" variant="ghost" wire:click="deleteAnnouncement({{ $announcement->id }})" wire:confirm="Delete this announcement? This cannot be undone.">Delete</flux:button>
                            @endcan
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="5" class="text-center text-gray-500">No announcements found</flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    <div class="w-full text-right">
        @can('create', Announcement::class)
            <flux:modal.trigger name="create-announcement-modal">
                <flux:button variant="primary">Create Announcement</flux:button>
            </flux:modal.trigger>
        @endcan
    </div>

    {{-- Create Modal --}}
    <flux:modal name="create-announcement-modal" variant="flyout" class="space-y-6">
        <flux:heading size="xl">Create Announcement</flux:heading>
        <form wire:submit.prevent="createAnnouncement">
            <div class="space-y-6">
                <flux:input label="Title" wire:model="newTitle" required placeholder="Announcement title" />

                <flux:field>
                    <div class="flex items-center justify-between">
                        <flux:label>Content (Markdown)</flux:label>
                        <flux:button size="xs" variant="ghost" type="button" wire:click="$toggle('showCreatePreview')">
                            {{ $showCreatePreview ? 'Edit' : 'Preview' }}
                        </flux:button>
                    </div>
                    @if($showCreatePreview)
                        <div class="prose prose-sm dark:prose-invert max-w-none border border-zinc-200 dark:border-zinc-700 rounded-lg p-4 min-h-[150px]">
                            {!! Str::markdown($newContent, ['html_input' => 'strip', 'allow_unsafe_links' => false]) !!}
                        </div>
                    @else
                        <flux:textarea wire:model="newContent" rows="10" placeholder="Write your announcement using markdown..." />
                    @endif
                    <flux:error name="newContent" />
                </flux:field>

                <flux:switch wire:model="newIsPublished" label="Publish immediately" />

                <flux:input label="Publish At (optional)" wire:model="newPublishedAt" type="datetime-local" />
                <flux:description>Schedule for later. Leave blank to publish now (if published).</flux:description>

                <flux:input label="Expires At (optional)" wire:model="newExpiredAt" type="datetime-local" />
                <flux:description>Auto-unpublish after this date. Leave blank for no expiration.</flux:description>

                <flux:button type="submit" variant="primary">Create</flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- Edit Modal --}}
    <flux:modal name="edit-announcement-modal" variant="flyout" class="space-y-6">
        <flux:heading size="xl">Edit Announcement</flux:heading>
        <form wire:submit.prevent="updateAnnouncement">
            <div class="space-y-6">
                <flux:input label="Title" wire:model="editTitle" required />

                <flux:field>
                    <div class="flex items-center justify-between">
                        <flux:label>Content (Markdown)</flux:label>
                        <flux:button size="xs" variant="ghost" type="button" wire:click="$toggle('showEditPreview')">
                            {{ $showEditPreview ? 'Edit' : 'Preview' }}
                        </flux:button>
                    </div>
                    @if($showEditPreview)
                        <div class="prose prose-sm dark:prose-invert max-w-none border border-zinc-200 dark:border-zinc-700 rounded-lg p-4 min-h-[150px]">
                            {!! Str::markdown($editContent, ['html_input' => 'strip', 'allow_unsafe_links' => false]) !!}
                        </div>
                    @else
                        <flux:textarea wire:model="editContent" rows="10" />
                    @endif
                    <flux:error name="editContent" />
                </flux:field>

                <flux:switch wire:model="editIsPublished" label="Published" />

                <flux:input label="Publish At" wire:model="editPublishedAt" type="datetime-local" />
                <flux:input label="Expires At (optional)" wire:model="editExpiredAt" type="datetime-local" />

                <flux:button type="submit" variant="primary">Save Changes</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
```

---

### Step 9: Rewrite dashboard announcement banner — single newest only
**File**: `resources/views/livewire/dashboard/view-announcements.blade.php`
**Action**: Modify — full rewrite

Show ONLY the single most recent unacknowledged published announcement. After acknowledging, the banner disappears entirely (no cycling to older ones).

```php
<?php

use App\Actions\AcknowledgeAnnouncement;
use App\Jobs\SendAnnouncementNotifications;
use App\Models\Announcement;
use Flux\Flux;
use Illuminate\Support\Str;
use Livewire\Volt\Component;

new class extends Component {
    public ?Announcement $latestAnnouncement = null;

    public function mount(): void
    {
        $this->dispatchPendingNotifications();
        $this->loadLatest();
    }

    /**
     * Lazy notification dispatch: find published announcements that haven't
     * had notifications sent yet, mark them, and queue the job.
     */
    protected function dispatchPendingNotifications(): void
    {
        $pending = Announcement::published()
            ->whereNull('notifications_sent_at')
            ->get();

        foreach ($pending as $announcement) {
            // Mark first to prevent duplicate dispatches from concurrent loads
            $announcement->update(['notifications_sent_at' => now()]);
            SendAnnouncementNotifications::dispatch($announcement);
        }
    }

    public function loadLatest(): void
    {
        $userId = auth()->id();

        $this->latestAnnouncement = Announcement::published()
            ->whereDoesntHave('acknowledgers', fn ($q) => $q->where('user_id', $userId))
            ->orderBy('published_at', 'desc')
            ->first();
    }

    public function acknowledgeAnnouncement(): void
    {
        if (!$this->latestAnnouncement) {
            return;
        }

        $this->authorize('acknowledge', $this->latestAnnouncement);

        AcknowledgeAnnouncement::run($this->latestAnnouncement, auth()->user());

        Flux::modal('view-latest-announcement')->close();
        Flux::toast('Announcement acknowledged.', 'Done', variant: 'success');

        $this->latestAnnouncement = null;
    }
}; ?>

<div>
    @if($latestAnnouncement)
        <flux:callout icon="megaphone" color="fuchsia" class="mb-6">
            <flux:callout.heading>{{ $latestAnnouncement->title }}</flux:callout.heading>
            <flux:callout.text>
                New announcement from {{ $latestAnnouncement->authorName() }}
            </flux:callout.text>
            <x-slot:actions>
                <flux:modal.trigger name="view-latest-announcement">
                    <flux:button variant="primary" size="sm">Read Announcement</flux:button>
                </flux:modal.trigger>
            </x-slot:actions>
        </flux:callout>

        <flux:modal name="view-latest-announcement" class="w-full lg:w-2/3 xl:w-1/2">
            <div class="space-y-4">
                <flux:heading size="xl">{{ $latestAnnouncement->title }}</flux:heading>

                <div class="flex items-center gap-2 text-sm text-zinc-500">
                    @if($latestAnnouncement->author)
                        @if($latestAnnouncement->author->avatarUrl())
                            <flux:avatar size="xs" src="{{ $latestAnnouncement->author->avatarUrl() }}" />
                        @endif
                        <span>Published by {{ $latestAnnouncement->author->name }}</span>
                    @endif
                    @if($latestAnnouncement->published_at)
                        <span>&middot; {{ $latestAnnouncement->published_at->format('M j, Y g:i A') }}</span>
                    @endif
                </div>

                <div class="prose prose-sm dark:prose-invert max-w-none">
                    {!! Str::markdown($latestAnnouncement->content, ['html_input' => 'strip', 'allow_unsafe_links' => false]) !!}
                </div>

                <div class="flex justify-end pt-4 border-t border-zinc-200 dark:border-zinc-700">
                    @can('acknowledge', $latestAnnouncement)
                        <flux:button wire:click="acknowledgeAnnouncement" variant="primary">
                            Acknowledge
                        </flux:button>
                    @endcan
                </div>
            </div>
        </flux:modal>
    @endif
</div>
```

---

### Step 10: Update dashboard announcements widget — modal instead of page link
**File**: `resources/views/livewire/dashboard/announcements-widget.blade.php`
**Action**: Modify — full rewrite

Replace the link-to-show-page pattern with a single dynamic modal.

```php
<?php

use App\Models\Announcement;
use Illuminate\Support\Str;
use Livewire\WithPagination;
use Livewire\Volt\Component;

new class extends Component {
    use WithPagination;

    public ?Announcement $selectedAnnouncement = null;

    public function getAnnouncementsProperty()
    {
        return Announcement::published()
            ->orderBy('published_at', 'desc')
            ->paginate(5, pageName: 'ann_widget_page');
    }

    public function viewAnnouncement(int $id): void
    {
        $this->selectedAnnouncement = Announcement::with('author')->findOrFail($id);
        Flux::modal('view-announcement-detail')->show();
    }
}; ?>

<flux:card>
    <flux:heading size="lg" class="mb-4">Announcements</flux:heading>

    <flux:table :paginate="$this->announcements">
        <flux:table.columns>
            <flux:table.column>Title</flux:table.column>
            <flux:table.column>Date</flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
            @forelse($this->announcements as $announcement)
                <flux:table.row wire:key="ann-widget-{{ $announcement->id }}">
                    <flux:table.cell>
                        <button wire:click="viewAnnouncement({{ $announcement->id }})" class="text-blue-600 dark:text-blue-400 hover:underline text-left">
                            {{ $announcement->title }}
                        </button>
                    </flux:table.cell>
                    <flux:table.cell class="text-sm text-zinc-500">
                        {{ $announcement->published_at?->format('M j, Y') ?? $announcement->created_at->format('M j, Y') }}
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="2" class="text-center text-zinc-500">No announcements yet</flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    @if($selectedAnnouncement)
        <flux:modal name="view-announcement-detail" class="w-full lg:w-2/3 xl:w-1/2">
            <div class="space-y-4">
                <flux:heading size="xl">{{ $selectedAnnouncement->title }}</flux:heading>

                <div class="flex items-center gap-2 text-sm text-zinc-500">
                    @if($selectedAnnouncement->author)
                        @if($selectedAnnouncement->author->avatarUrl())
                            <flux:avatar size="xs" src="{{ $selectedAnnouncement->author->avatarUrl() }}" />
                        @endif
                        <span>{{ $selectedAnnouncement->author->name }}</span>
                    @endif
                    @if($selectedAnnouncement->published_at)
                        <span>&middot; {{ $selectedAnnouncement->published_at->format('M j, Y g:i A') }}</span>
                    @endif
                </div>

                <div class="prose prose-sm dark:prose-invert max-w-none">
                    {!! Str::markdown($selectedAnnouncement->content, ['html_input' => 'strip', 'allow_unsafe_links' => false]) !!}
                </div>
            </div>
        </flux:modal>
    @endif
</flux:card>
```

---

### Step 11: Create NewAnnouncementNotification
**File**: `app/Notifications/NewAnnouncementNotification.php`
**Action**: Create

Follow the exact pattern from `NewTicketNotification`.

```php
<?php

namespace App\Notifications;

use App\Models\Announcement;
use App\Notifications\Channels\DiscordChannel;
use App\Notifications\Channels\PushoverChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewAnnouncementNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected array $allowedChannels = ['mail'];

    protected ?string $pushoverKey = null;

    public function __construct(
        public Announcement $announcement
    ) {}

    public function setChannels(array $channels, ?string $pushoverKey = null): self
    {
        $this->allowedChannels = $channels;
        $this->pushoverKey = $pushoverKey;

        return $this;
    }

    public function via(object $notifiable): array
    {
        $channels = [];

        if (in_array('mail', $this->allowedChannels)) {
            $channels[] = 'mail';
        }

        if (in_array('pushover', $this->allowedChannels) && $this->pushoverKey) {
            $channels[] = PushoverChannel::class;
        }

        if (in_array('discord', $this->allowedChannels)) {
            $channels[] = DiscordChannel::class;
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('New Announcement: ' . $this->announcement->title)
            ->line('A new announcement has been posted:')
            ->line('**' . $this->announcement->title . '**')
            ->line('By ' . $this->announcement->authorName())
            ->action('View on Dashboard', route('dashboard'));
    }

    public function toPushover(object $notifiable): array
    {
        return [
            'title' => 'New Announcement',
            'message' => $this->announcement->title,
            'url' => route('dashboard'),
        ];
    }

    public function toDiscord(object $notifiable): string
    {
        return "**New Announcement:** {$this->announcement->title}\n**By:** {$this->announcement->authorName()}\n" . route('dashboard');
    }
}
```

---

### Step 12: Create SendAnnouncementNotifications Job
**File**: `app/Jobs/SendAnnouncementNotifications.php`
**Action**: Create

This Job runs in the background so the admin gets instant feedback when publishing. It iterates through all eligible users and sends the notification via `TicketNotificationService`.

```php
<?php

namespace App\Jobs;

use App\Enums\MembershipLevel;
use App\Models\Announcement;
use App\Models\User;
use App\Notifications\NewAnnouncementNotification;
use App\Services\TicketNotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendAnnouncementNotifications implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Announcement $announcement
    ) {}

    public function handle(): void
    {
        $service = app(TicketNotificationService::class);

        $users = User::where('membership_level', '>=', MembershipLevel::Traveler->value)
            ->where('id', '!=', $this->announcement->author_id)
            ->get();

        $service->sendToMany(
            $users,
            new NewAnnouncementNotification($this->announcement),
            'announcements'
        );
    }
}
```

---

### Step 13: Add 'announcements' to TicketNotificationService defaults
**File**: `app/Services/TicketNotificationService.php`
**Action**: Modify — update `defaultPreferences()`:

```php
protected function defaultPreferences(string $category): array
{
    return match ($category) {
        'account' => ['email' => true, 'pushover' => false, 'discord' => false],
        'staff_alerts' => ['email' => true, 'pushover' => false, 'discord' => false],
        'announcements' => ['email' => true, 'pushover' => false, 'discord' => false],
        default => ['email' => true, 'pushover' => false, 'discord' => false],
    };
}
```

Also update the docblock on `send()` to list 'announcements' as a valid category.

---

### Step 14: Add announcements toggle to notification settings UI
**File**: `resources/views/livewire/settings/notifications.blade.php`
**Action**: Modify

Add properties:
```php
// Notification preferences — Announcements
public bool $notify_announcements_email = true;
public bool $notify_announcements_pushover = false;
public bool $notify_announcements_discord = false;
```

In `mount()`, load preferences:
```php
$this->notify_announcements_email = $preferences['announcements']['email'] ?? true;
$this->notify_announcements_pushover = $preferences['announcements']['pushover'] ?? false;
$this->notify_announcements_discord = $preferences['announcements']['discord'] ?? false;
```

In `updateNotificationSettings()`, add validation rules:
```php
'notify_announcements_email' => ['boolean'],
'notify_announcements_pushover' => ['boolean'],
'notify_announcements_discord' => ['boolean'],
```

Save preferences:
```php
$preferences['announcements'] = [
    'email' => $validated['notify_announcements_email'],
    'pushover' => $validated['notify_announcements_pushover'],
    'discord' => $validated['notify_announcements_discord'],
];
```

In the blade template, add a new card section (after Account Updates, before Staff Alerts):
```blade
<div class="border border-zinc-200 dark:border-zinc-700 rounded-lg p-4">
    <div class="flex items-center justify-between mb-3">
        <div>
            <div class="font-medium text-sm text-zinc-900 dark:text-white">Announcements</div>
            <div class="text-xs text-zinc-600 dark:text-zinc-400">New community announcements</div>
        </div>
    </div>
    <div class="flex gap-6">
        <flux:switch wire:model="notify_announcements_email" label="Email" />
        @if($pushover_key)
            <flux:switch wire:model="notify_announcements_pushover" label="Pushover" />
        @else
            <flux:tooltip content="Add your Pushover key above to enable">
                <flux:switch wire:model="notify_announcements_pushover" label="Pushover" disabled />
            </flux:tooltip>
        @endif
        @if(auth()->user()->hasDiscordLinked())
            <flux:switch wire:model="notify_announcements_discord" label="Discord DM" />
        @else
            <flux:tooltip content="Link a Discord account in Settings to enable">
                <flux:switch wire:model="notify_announcements_discord" label="Discord DM" disabled />
            </flux:tooltip>
        @endif
    </div>
</div>
```

---

### Step 15: Update ACP tabs component
**File**: `resources/views/livewire/admin-control-panel-tabs.blade.php`
**Action**: Modify — remove Tag/Category model imports if present

Remove `use App\Models\Tag;`, `use App\Models\Category;`, `use App\Models\Comment;` from the imports at the top if they exist.

---

### Step 16: Tests

#### 15a: Update existing tests
**File**: `tests/Feature/Actions/AcknowledgeAnnouncementTest.php`
**Action**: Verify — should still pass since the AcknowledgeAnnouncement action and announcement_user pivot are unchanged.

**File**: `tests/Feature/Dashboard/DashboardAnnouncementTest.php`
**Action**: Modify — update assertions to match new component output (single banner, modal).

**File**: `tests/Feature/Announcements/DashboardAnnouncementViewTest.php`
**Action**: Modify — update to test single-newest behavior.

#### 15b: New tests
**File**: `tests/Feature/Announcements/AnnouncementExpirationTest.php`
**Action**: Create

```php
uses()->group('announcements');

it('scopePublished excludes expired announcements', function () {
    Announcement::factory()->published()->create(['expired_at' => now()->subDay()]);
    Announcement::factory()->published()->create(['expired_at' => null]);

    expect(Announcement::published()->count())->toBe(1);
});

it('scopePublished excludes future-scheduled announcements', function () {
    Announcement::factory()->create([
        'is_published' => true,
        'published_at' => now()->addDay(),
    ]);
    Announcement::factory()->published()->create();

    expect(Announcement::published()->count())->toBe(1);
});

it('scopePublished includes announcements with future expiry', function () {
    Announcement::factory()->published()->create(['expired_at' => now()->addWeek()]);

    expect(Announcement::published()->count())->toBe(1);
});

it('isExpired returns true for past expired_at', function () {
    $announcement = Announcement::factory()->published()->create(['expired_at' => now()->subHour()]);

    expect($announcement->isExpired())->toBeTrue();
});

it('isExpired returns false when expired_at is null', function () {
    $announcement = Announcement::factory()->published()->create(['expired_at' => null]);

    expect($announcement->isExpired())->toBeFalse();
});
```

**File**: `tests/Feature/Announcements/AnnouncementBannerTest.php`
**Action**: Create

```php
uses()->group('announcements', 'dashboard');

it('shows only the newest unacknowledged announcement on dashboard', function () {
    $user = User::factory()->create();
    loginAs($user);

    $older = Announcement::factory()->published()->create(['published_at' => now()->subDays(2)]);
    $newer = Announcement::factory()->published()->create(['published_at' => now()->subDay()]);

    $response = $this->get(route('dashboard'));

    $response->assertSee($newer->title);
    $response->assertDontSee($older->title);
});

it('hides banner after acknowledging the newest announcement', function () {
    $user = User::factory()->create();
    loginAs($user);

    $announcement = Announcement::factory()->published()->create();
    AcknowledgeAnnouncement::run($announcement, $user);

    Livewire::test('dashboard.view-announcements')
        ->assertDontSee($announcement->title);
});

it('does not show expired announcements in banner', function () {
    $user = User::factory()->create();
    loginAs($user);

    Announcement::factory()->published()->create(['expired_at' => now()->subHour()]);

    Livewire::test('dashboard.view-announcements')
        ->assertSet('latestAnnouncement', null);
});
```

**File**: `tests/Feature/Announcements/AnnouncementNotificationTest.php`
**Action**: Create

Tests verify that the dashboard `view-announcements` component dispatches the notification job lazily when it detects a published announcement without `notifications_sent_at`.

```php
use App\Jobs\SendAnnouncementNotifications;
use App\Models\Announcement;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

uses()->group('announcements', 'notifications');

it('dispatches notification job on dashboard load for newly published announcement', function () {
    Queue::fake();
    $user = User::factory()->create();
    loginAs($user);

    Announcement::factory()->published()->create(['notifications_sent_at' => null]);

    Livewire::test('dashboard.view-announcements');

    Queue::assertPushed(SendAnnouncementNotifications::class);
});

it('does not dispatch notification job for announcement already notified', function () {
    Queue::fake();
    $user = User::factory()->create();
    loginAs($user);

    Announcement::factory()->published()->create(['notifications_sent_at' => now()]);

    Livewire::test('dashboard.view-announcements');

    Queue::assertNotPushed(SendAnnouncementNotifications::class);
});

it('does not dispatch notification job for draft announcements', function () {
    Queue::fake();
    $user = User::factory()->create();
    loginAs($user);

    Announcement::factory()->unpublished()->create(['notifications_sent_at' => null]);

    Livewire::test('dashboard.view-announcements');

    Queue::assertNotPushed(SendAnnouncementNotifications::class);
});

it('sets notifications_sent_at when dispatching', function () {
    Queue::fake();
    $user = User::factory()->create();
    loginAs($user);

    $announcement = Announcement::factory()->published()->create(['notifications_sent_at' => null]);

    Livewire::test('dashboard.view-announcements');

    expect($announcement->fresh()->notifications_sent_at)->not->toBeNull();
});

it('does not dispatch for expired announcements', function () {
    Queue::fake();
    $user = User::factory()->create();
    loginAs($user);

    Announcement::factory()->published()->create([
        'notifications_sent_at' => null,
        'expired_at' => now()->subHour(),
    ]);

    Livewire::test('dashboard.view-announcements');

    Queue::assertNotPushed(SendAnnouncementNotifications::class);
});
```

**File**: `tests/Feature/Jobs/SendAnnouncementNotificationsTest.php`
**Action**: Create

```php
use App\Jobs\SendAnnouncementNotifications;
use App\Enums\MembershipLevel;
use App\Models\Announcement;
use App\Models\User;
use App\Notifications\NewAnnouncementNotification;
use Illuminate\Support\Facades\Notification;

uses()->group('announcements', 'jobs', 'notifications');

it('sends notification to all traveler+ users except the author', function () {
    Notification::fake();
    $author = User::factory()->create(['membership_level' => MembershipLevel::Traveler->value]);
    $recipient = User::factory()->create(['membership_level' => MembershipLevel::Traveler->value]);
    $stowaway = User::factory()->create(['membership_level' => MembershipLevel::Stowaway->value]);

    $announcement = Announcement::factory()->published()->create(['author_id' => $author->id]);

    (new SendAnnouncementNotifications($announcement))->handle();

    Notification::assertSentTo($recipient, NewAnnouncementNotification::class);
    Notification::assertNotSentTo($author, NewAnnouncementNotification::class);
    Notification::assertNotSentTo($stowaway, NewAnnouncementNotification::class);
});
```

**File**: `tests/Feature/Announcements/AnnouncementAdminTest.php`
**Action**: Create

```php
uses()->group('announcements', 'admin');

it('admin can create announcement via modal', function () {
    loginAsAdmin();

    Livewire::test('admin-manage-announcements-page')
        ->set('newTitle', 'New Announcement')
        ->set('newContent', '# Hello World')
        ->set('newIsPublished', true)
        ->call('createAnnouncement');

    $this->assertDatabaseHas('announcements', ['title' => 'New Announcement']);
});

it('admin can update announcement via modal', function () {
    $admin = loginAsAdmin();
    $announcement = Announcement::factory()->published()->create();

    Livewire::test('admin-manage-announcements-page')
        ->call('openEditModal', $announcement->id)
        ->set('editTitle', 'Updated Title')
        ->call('updateAnnouncement');

    expect($announcement->fresh()->title)->toBe('Updated Title');
});

it('admin can delete announcement', function () {
    loginAsAdmin();
    $announcement = Announcement::factory()->published()->create();

    Livewire::test('admin-manage-announcements-page')
        ->call('deleteAnnouncement', $announcement->id);

    $this->assertDatabaseMissing('announcements', ['id' => $announcement->id]);
});

it('stores expired_at when creating announcement', function () {
    loginAsAdmin();
    $expiry = now()->addWeek()->format('Y-m-d\TH:i');

    Livewire::test('admin-manage-announcements-page')
        ->set('newTitle', 'Expiring Announcement')
        ->set('newContent', 'Content')
        ->set('newIsPublished', true)
        ->set('newExpiredAt', $expiry)
        ->call('createAnnouncement');

    $announcement = Announcement::where('title', 'Expiring Announcement')->first();
    expect($announcement->expired_at)->not->toBeNull();
});

it('unauthorized user cannot create announcement', function () {
    $user = User::factory()->create();
    loginAs($user);

    Livewire::test('admin-manage-announcements-page')
        ->set('newTitle', 'Attempt')
        ->set('newContent', 'Content')
        ->call('createAnnouncement')
        ->assertForbidden();
});
```

---

## Edge Cases

1. **Existing HTML content**: Old announcements stored as HTML from `flux:editor` will render as raw HTML when passed through `Str::markdown()` with `html_input => 'strip'`. Admin should re-edit these manually. There are likely very few.
2. **Expired + unacknowledged**: An expired announcement that was never acknowledged should NOT appear in the dashboard banner (the `published()` scope filters it out).
3. **Scheduled + published**: An announcement with `is_published=true` and `published_at` in the future should not appear until that time arrives.
4. **Notification timing**: Notifications are dispatched lazily — when any user loads the dashboard and the `view-announcements` component mounts, it checks for published announcements with `notifications_sent_at = null`. This handles both immediate publishes and scheduled ones that have become live. The `notifications_sent_at` flag is set before dispatching to prevent duplicate jobs from concurrent dashboard loads.
5. **Author deletes own announcement**: Existing `author_id` is set null on user delete. The `authorName()` method already handles this.
6. **Expired_at validation**: `expired_at` must be after `published_at` if both are set.

## Known Risks

1. **HTML → Markdown migration**: Existing announcement content will look wrong after the switch. Mitigated by manual admin re-editing (expected to be a small number of records).
2. **Notification volume**: Publishing an announcement sends notifications to all Traveler+ users. For large communities, this could be significant. Mitigated by using `ShouldQueue` on the notification class and dispatching via a background Job.
3. **Lazy notification race condition**: Two users loading the dashboard simultaneously could theoretically both dispatch notifications for the same announcement. Mitigated by setting `notifications_sent_at` before dispatching the job (mark-then-dispatch). In practice, even if a rare double-dispatch occurs, users receive duplicate notifications rather than missing them — acceptable trade-off for simplicity.

## Definition of Done

- [ ] `php artisan migrate:fresh` passes
- [ ] `./vendor/bin/pest` passes with zero failures
- [ ] All test cases from this plan are implemented
- [ ] No ad-hoc auth checks in Blade templates (all via `@can` / `$this->authorize()`)
- [ ] Tags, Categories, Comments models/tables/components fully removed
- [ ] AnnouncementController fully removed, no dedicated announcement routes
- [ ] Admin create/edit uses flyout modals
- [ ] Dashboard banner shows only newest unacknowledged, disappears after acknowledging
- [ ] Announcement content renders as markdown everywhere
- [ ] Notification settings UI includes "Announcements" category
- [ ] `NewAnnouncementNotification` sends on publish
