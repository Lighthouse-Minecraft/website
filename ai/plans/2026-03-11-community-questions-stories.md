# Plan: Community Questions & Stories

**Date**: 2026-03-11
**Planned by**: Claude Code
**Status**: COMPLETE

## Summary

The Community Questions & Stories feature allows Lighthouse members to respond to
rotating community questions, creating a living archive of community experiences.
Staff publish questions on a schedule, members submit responses, staff moderate them,
and approved responses become public — appearing in a story feed and on user profiles.
The system includes emoji reactions, rank-based participation tiers, and citizen
question suggestions. The experience is designed to feel like a community scrapbook,
not a social media feed.

## Files to Read (for implementing agent context)

- `CLAUDE.md`
- `ai/CONVENTIONS.md`
- `ai/ARCHITECTURE.md`
- `app/Providers/AuthServiceProvider.php`
- `app/Models/User.php`
- `app/Enums/MembershipLevel.php`
- `resources/views/livewire/settings/staff-bio.blade.php` (image upload pattern)
- `resources/views/users/show.blade.php` (profile page layout)
- `tests/Pest.php` and `tests/Support/Users.php` (test helpers)

---

## Authorization Rules

### Gates (add to `AuthServiceProvider::boot()`)

```php
// Any Traveler+ who is not in brig can view community stories
Gate::define('view-community-stories', function ($user) {
    return ! $user->in_brig
        && $user->isAtLeastLevel(MembershipLevel::Traveler);
});

// Any Traveler+ who is not in brig can submit responses
Gate::define('submit-community-response', function ($user) {
    return ! $user->in_brig
        && $user->isAtLeastLevel(MembershipLevel::Traveler);
});

// Citizens can suggest questions
Gate::define('suggest-community-question', function ($user) {
    return ! $user->in_brig
        && $user->isAtLeastLevel(MembershipLevel::Citizen);
});

// Primary: Chaplain dept (Jr Crew+). Backup: Command Officers and Admins.
Gate::define('manage-community-stories', function ($user) {
    return $user->hasRole('Admin')
        || ($user->isAtLeastRank(StaffRank::Officer) && $user->isInDepartment(StaffDepartment::Command))
        || ($user->isAtLeastRank(StaffRank::JrCrew) && $user->isInDepartment(StaffDepartment::Chaplain));
});
```

### Policies

**`CommunityResponsePolicy`** (new file):
- `view(User $user, CommunityResponse $response)` — response is approved, OR user owns it, OR user can manage
- `update(User $user, CommunityResponse $response)` — user owns it AND response is not yet approved, OR user can manage AND response is not yet approved
- `delete(User $user, CommunityResponse $response)` — user owns it AND response is not yet approved, OR user can manage
- `before(User $user, string $ability)` — admin/command officer bypass

**`CommunityQuestionPolicy`** (new file):
- `viewAny(User $user)` — can view-community-stories
- `create(User $user)` — can manage-community-stories
- `update(User $user, CommunityQuestion $question)` — can manage-community-stories
- `delete(User $user, CommunityQuestion $question)` — can manage-community-stories AND question has no approved responses
- `before(User $user, string $ability)` — admin/command officer bypass

---

## Database Changes

### Migration 1: `create_community_questions_table`

| Column | Type | Details |
|---|---|---|
| `id` | bigIncrements | PK |
| `question_text` | text | The question prompt |
| `description` | text, nullable | Optional expanded context |
| `status` | string | Enum: draft, scheduled, active, archived |
| `start_date` | datetime, nullable | When the question becomes active |
| `end_date` | datetime, nullable | When the question archives |
| `created_by` | foreignId → users | Staff who created it |
| `suggested_by` | foreignId → users, nullable | Citizen who suggested it (if from suggestion) |
| `suggestion_id` | foreignId → question_suggestions, nullable | Link to original suggestion |
| `timestamps` | | |

Indexes: `status`, `start_date`, `end_date`

### Migration 2: `create_community_responses_table`

| Column | Type | Details |
|---|---|---|
| `id` | bigIncrements | PK |
| `community_question_id` | foreignId → community_questions | FK, cascade delete |
| `user_id` | foreignId → users | FK, cascade delete |
| `body` | text | Response text content |
| `image_path` | string, nullable | Optional uploaded image |
| `status` | string | Enum: submitted, under_review, approved, rejected, archived |
| `reviewed_by` | foreignId → users, nullable | Staff who moderated |
| `reviewed_at` | datetime, nullable | When moderation happened |
| `approved_at` | datetime, nullable | When approved (for ordering) |
| `featured_in_blog_url` | string, nullable | Reference to blog post if featured |
| `timestamps` | | |

Indexes: `status`, composite `[community_question_id, user_id]` (unique — one response per user per question)

### Migration 3: `create_community_reactions_table`

| Column | Type | Details |
|---|---|---|
| `id` | bigIncrements | PK |
| `community_response_id` | foreignId → community_responses | FK, cascade delete |
| `user_id` | foreignId → users | FK, cascade delete |
| `emoji` | string(32) | The emoji character(s) |
| `timestamps` | | |

Unique index: `[community_response_id, user_id, emoji]` (one reaction type per user per response)

### Migration 4: `create_question_suggestions_table`

| Column | Type | Details |
|---|---|---|
| `id` | bigIncrements | PK |
| `user_id` | foreignId → users | FK, cascade delete |
| `question_text` | text | Suggested question text |
| `status` | string | Enum: suggested, approved, rejected |
| `reviewed_by` | foreignId → users, nullable | Staff who reviewed |
| `reviewed_at` | datetime, nullable | |
| `timestamps` | | |

Index: `status`

---

## Enums

### `CommunityQuestionStatus` (string enum)

```php
namespace App\Enums;

enum CommunityQuestionStatus: string
{
    case Draft = 'draft';
    case Scheduled = 'scheduled';
    case Active = 'active';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Scheduled => 'Scheduled',
            self::Active => 'Active',
            self::Archived => 'Archived',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'zinc',
            self::Scheduled => 'sky',
            self::Active => 'emerald',
            self::Archived => 'amber',
        };
    }
}
```

### `CommunityResponseStatus` (string enum)

```php
namespace App\Enums;

enum CommunityResponseStatus: string
{
    case Submitted = 'submitted';
    case UnderReview = 'under_review';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Submitted => 'Submitted',
            self::UnderReview => 'Under Review',
            self::Approved => 'Approved',
            self::Rejected => 'Rejected',
            self::Archived => 'Archived',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Submitted => 'sky',
            self::UnderReview => 'amber',
            self::Approved => 'emerald',
            self::Rejected => 'red',
            self::Archived => 'zinc',
        };
    }
}
```

### `QuestionSuggestionStatus` (string enum)

```php
namespace App\Enums;

enum QuestionSuggestionStatus: string
{
    case Suggested = 'suggested';
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Suggested => 'Suggested',
            self::Approved => 'Approved',
            self::Rejected => 'Rejected',
        };
    }
}
```

---

## Models

### `CommunityQuestion`

```php
namespace App\Models;

use App\Enums\CommunityQuestionStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CommunityQuestion extends Model
{
    use HasFactory;

    protected $fillable = [
        'question_text',
        'description',
        'status',
        'start_date',
        'end_date',
        'created_by',
        'suggested_by',
        'suggestion_id',
    ];

    protected function casts(): array
    {
        return [
            'status' => CommunityQuestionStatus::class,
            'start_date' => 'datetime',
            'end_date' => 'datetime',
        ];
    }

    // Relationships
    public function responses() { return $this->hasMany(CommunityResponse::class); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
    public function suggester() { return $this->belongsTo(User::class, 'suggested_by'); }
    public function suggestion() { return $this->belongsTo(QuestionSuggestion::class); }

    // Scopes
    public function scopeActive($query) { return $query->where('status', CommunityQuestionStatus::Active); }
    public function scopeArchived($query) { return $query->where('status', CommunityQuestionStatus::Archived); }
    public function scopeScheduled($query) { return $query->where('status', CommunityQuestionStatus::Scheduled); }

    // Helpers
    public function approvedResponses() { return $this->responses()->where('status', CommunityResponseStatus::Approved); }
    public function isActive(): bool { return $this->status === CommunityQuestionStatus::Active; }
    public function isArchived(): bool { return $this->status === CommunityQuestionStatus::Archived; }
}
```

### `CommunityResponse`

```php
namespace App\Models;

use App\Enums\CommunityResponseStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CommunityResponse extends Model
{
    use HasFactory;

    protected $fillable = [
        'community_question_id',
        'user_id',
        'body',
        'image_path',
        'status',
        'reviewed_by',
        'reviewed_at',
        'approved_at',
        'featured_in_blog_url',
    ];

    protected function casts(): array
    {
        return [
            'status' => CommunityResponseStatus::class,
            'reviewed_at' => 'datetime',
            'approved_at' => 'datetime',
        ];
    }

    // Relationships
    public function question() { return $this->belongsTo(CommunityQuestion::class, 'community_question_id'); }
    public function user() { return $this->belongsTo(User::class); }
    public function reviewer() { return $this->belongsTo(User::class, 'reviewed_by'); }
    public function reactions() { return $this->hasMany(CommunityReaction::class); }

    // Scopes
    public function scopeApproved($query) { return $query->where('status', CommunityResponseStatus::Approved); }
    public function scopePendingReview($query) {
        return $query->whereIn('status', [CommunityResponseStatus::Submitted, CommunityResponseStatus::UnderReview]);
    }

    // Helpers
    public function isApproved(): bool { return $this->status === CommunityResponseStatus::Approved; }
    public function isEditable(): bool {
        return in_array($this->status, [CommunityResponseStatus::Submitted, CommunityResponseStatus::UnderReview]);
    }
    public function imageUrl(): ?string {
        return $this->image_path ? asset('storage/' . $this->image_path) : null;
    }
}
```

### `CommunityReaction`

```php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CommunityReaction extends Model
{
    protected $fillable = ['community_response_id', 'user_id', 'emoji'];

    public function response() { return $this->belongsTo(CommunityResponse::class, 'community_response_id'); }
    public function user() { return $this->belongsTo(User::class); }
}
```

### `QuestionSuggestion`

```php
namespace App\Models;

use App\Enums\QuestionSuggestionStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QuestionSuggestion extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'question_text', 'status', 'reviewed_by', 'reviewed_at'];

    protected function casts(): array
    {
        return [
            'status' => QuestionSuggestionStatus::class,
            'reviewed_at' => 'datetime',
        ];
    }

    public function user() { return $this->belongsTo(User::class); }
    public function reviewer() { return $this->belongsTo(User::class, 'reviewed_by'); }
}
```

### User Model Changes

Add to `User.php`:

```php
// Relationships
public function communityResponses()
{
    return $this->hasMany(CommunityResponse::class);
}

public function questionSuggestions()
{
    return $this->hasMany(QuestionSuggestion::class);
}
```

---

## Action Classes

### `CreateCommunityQuestion`

**File**: `app/Actions/CreateCommunityQuestion.php`
**Parameters**: `handle(User $staff, string $questionText, ?string $description, CommunityQuestionStatus $status, ?Carbon $startDate, ?Carbon $endDate, ?int $suggestionId): CommunityQuestion`
**Side effects**:
- Create CommunityQuestion record
- RecordActivity: `'community_question_created'`, `"Community question created by {$staff->name}."`
- If created from suggestion, link `suggestion_id` and `suggested_by`

### `UpdateCommunityQuestion`

**File**: `app/Actions/UpdateCommunityQuestion.php`
**Parameters**: `handle(CommunityQuestion $question, User $staff, array $data): CommunityQuestion`
**Side effects**:
- Update question fields (text, description, status, dates)
- RecordActivity: `'community_question_updated'`, `"Community question updated by {$staff->name}. Status: {$status}."`

### `SubmitCommunityResponse`

**File**: `app/Actions/SubmitCommunityResponse.php`
**Parameters**: `handle(CommunityQuestion $question, User $user, string $body, ?UploadedFile $image): CommunityResponse`
**Side effects**:
- Validate user hasn't already responded to this question (throw if duplicate)
- Validate user rank permits responding to this question (active vs archived)
- Store image if provided (to `community-stories/` disk path)
- Create CommunityResponse with status `Submitted`
- RecordActivity: `'community_response_submitted'`, `"Response submitted by {$user->name} to question #{$question->id}."`

**Rank validation logic** (enforced here, not just UI):
```php
// Active question: Traveler+ can respond
// Archived question: Resident+ can respond, AND must have already answered the current active question,
//   AND must not have already responded to another archived question during this active period
```

### `EditCommunityResponse`

**File**: `app/Actions/EditCommunityResponse.php`
**Parameters**: `handle(CommunityResponse $response, User $editor, string $body, ?UploadedFile $newImage, bool $removeImage): CommunityResponse`
**Side effects**:
- Validate response is still editable (not approved)
- Update body text
- Handle image change/removal if applicable
- RecordActivity: `'community_response_edited'`, `"Response #{$response->id} edited by {$editor->name}."`

### `ModerateResponses`

**File**: `app/Actions/ModerateResponses.php`
**Parameters**: `handle(Collection $responses, User $staff, CommunityResponseStatus $outcome): int`
**Side effects**:
- `$outcome` must be either `Approved` or `Rejected`
- Bulk update status, set `reviewed_by`, `reviewed_at`
- If approving: also set `approved_at` to now
- RecordActivity (one per response): `'community_response_approved'` or `'community_response_rejected'`
- Return count of moderated responses
- No notifications sent for rejections (per spec)

### `ToggleCommunityReaction`

**File**: `app/Actions/ToggleCommunityReaction.php`
**Parameters**: `handle(CommunityResponse $response, User $user, string $emoji): bool`
**Side effects**:
- If reaction exists → remove it, return `false`
- If reaction doesn't exist → create it, return `true`
- No activity logging (too noisy)

### `SubmitQuestionSuggestion`

**File**: `app/Actions/SubmitQuestionSuggestion.php`
**Parameters**: `handle(User $user, string $questionText): QuestionSuggestion`
**Side effects**:
- Create QuestionSuggestion with status `Suggested`
- RecordActivity: `'question_suggestion_submitted'`, `"Question suggested by {$user->name}."`

### `ReviewQuestionSuggestion`

**File**: `app/Actions/ReviewQuestionSuggestion.php`
**Parameters**: `handle(QuestionSuggestion $suggestion, User $staff, QuestionSuggestionStatus $outcome): QuestionSuggestion`
**Side effects**:
- Update suggestion status, set `reviewed_by`, `reviewed_at`
- RecordActivity: `'question_suggestion_reviewed'`, `"Suggestion #{$suggestion->id} {$outcome->value} by {$staff->name}."`

### `ProcessQuestionSchedule`

**File**: `app/Actions/ProcessQuestionSchedule.php`
**Parameters**: `handle(): void`
**Side effects**:
- Find scheduled questions where `start_date <= now()` → set status to `Active`
- Find active questions where `end_date <= now()` → set status to `Archived`
- RecordActivity for each transition
- Called by a scheduled Job (see below)

---

## Job Class

### `ProcessCommunityQuestionScheduleJob`

**File**: `app/Jobs/ProcessCommunityQuestionScheduleJob.php`
**Schedule**: Runs hourly via Laravel scheduler
**Logic**: Calls `ProcessQuestionSchedule::run()`

Register in `app/Console/Kernel.php` (or `routes/console.php`):
```php
Schedule::job(new ProcessCommunityQuestionScheduleJob)->hourly();
```

---

## Notification Classes

None. The spec explicitly states:
- No rejection notifications
- No approval notifications mentioned

If we want to notify staff when new responses are submitted for review, that can be added later.

---

## Livewire Volt Components

### 1. Main Feature Page: `community-stories/index.blade.php`

**File**: `resources/views/livewire/community-stories/index.blade.php`
**Route**: `Volt::route('/community-stories', 'community-stories.index')->name('community-stories.index')`
**Middleware**: `['auth', 'verified', 'ensure-dob', 'can:view-community-stories']`

**Public properties**:
- `$activeTab` — 'stories' | 'past-questions' | 'manage' (manage only for staff)
- `$responseBody` — text input for submitting response
- `$responseImage` — file upload (WithFileUploads)
- `$selectedQuestionId` — for browsing past questions
- `$editingResponseId` — for editing own response
- `$editBody` — text for editing

**Staff properties** (conditional):
- `$moderationTab` — 'responses' | 'questions' | 'suggestions'
- `$selectedResponseIds` — array for bulk moderation
- `$viewingResponseId` — for modal detail view
- `$editModalBody` — for staff editing a response before approval
- `$newQuestionText`, `$newQuestionDescription`, `$newQuestionStatus`, `$newQuestionStartDate`, `$newQuestionEndDate` — question creation/editing
- `$editingQuestionId` — for editing existing question

**Methods**:
- `submitResponse()` — authorize, validate, call SubmitCommunityResponse::run()
- `editResponse()` — authorize, validate, call EditCommunityResponse::run()
- `deleteResponse(int $id)` — authorize, delete own unapproved response
- `toggleReaction(int $responseId, string $emoji)` — call ToggleCommunityReaction::run()
- `suggestQuestion()` — authorize (citizen), call SubmitQuestionSuggestion::run()
- `browseQuestion(int $questionId)` — set selectedQuestionId for past questions tab

**Staff methods**:
- `approveSelected()` — authorize, call ModerateResponses::run() with Approved
- `rejectSelected()` — authorize, call ModerateResponses::run() with Rejected
- `openResponseModal(int $id)` — open detail modal for review
- `editResponseAsStaff()` — edit response body before approval
- `saveQuestion()` — create or update question via actions
- `deleteQuestion(int $id)` — delete question (only if no approved responses)
- `reviewSuggestion(int $id, string $outcome)` — call ReviewQuestionSuggestion::run()

**Blade layout** (3 tabs for public, 4th for staff):

```blade
{{-- Tab: Current Question & Stories --}}
- Display active question text + description
- Response form (if user hasn't responded yet)
- Feed of approved responses (ordered by approved_at desc)
- Each response: user avatar, name, body text, optional image, reactions, timestamp

{{-- Tab: Past Questions --}}
- List of archived questions with response counts
- Click to expand/view approved responses for that question
- If Resident+: option to respond to one archived question

{{-- Tab: Manage (staff only, @can('manage-community-stories')) --}}
- Sub-tabs: Pending Responses | Questions | Suggestions

{{-- Pending Responses sub-tab --}}
- Table: checkbox, user, question, body preview, submitted date, actions
- Bulk approve/reject buttons
- Click row to open detail modal
- Modal: full response text, image, edit body field, approve/reject buttons

{{-- Questions sub-tab --}}
- Table: question text, status badge, start date, end date, response count, actions
- Create new question button → form modal
- Edit/delete actions per row

{{-- Suggestions sub-tab --}}
- Table: suggested by, question text, date, status, actions
- Approve (creates draft question) / Reject buttons
```

### 2. Dashboard Widget: `dashboard/community-question-widget.blade.php`

**File**: `resources/views/livewire/dashboard/community-question-widget.blade.php`

**Public properties**:
- `$responseBody` — text input
- `$responseImage` — file upload
- `$hasResponded` — computed, whether user already responded to active question
- `$randomApprovedResponse` — shown after user responds

**Methods**:
- `submitResponse()` — authorize, validate, call SubmitCommunityResponse::run(), then load random approved response
- `mount()` — check if active question exists, if user has already responded

**Blade layout**:
```blade
@can('view-community-stories')
<flux:card>
    <flux:heading size="md">Community Question</flux:heading>
    {{-- If active question exists --}}
    @if($activeQuestion)
        <flux:text>{{ $activeQuestion->question_text }}</flux:text>

        @if(!$hasResponded)
            {{-- Response form --}}
        @else
            {{-- Show random approved response from another member --}}
            <flux:text variant="subtle">You've shared your story! Here's one from the community:</flux:text>
            {{-- Display random response --}}
            <flux:link href="{{ route('community-stories.index') }}">View all stories →</flux:link>
        @endif
    @else
        <flux:text variant="subtle">No active community question right now.</flux:text>
    @endif
</flux:card>
@endcan
```

### 3. Profile Integration: `users/community-stories-card.blade.php`

**File**: `resources/views/livewire/users/community-stories-card.blade.php`

**Public properties**:
- `$user` — the profile user

**Blade layout**:
```blade
{{-- Only show if user has approved responses --}}
@if($approvedResponses->count() > 0)
<flux:card>
    <flux:heading size="md">Community Stories</flux:heading>
    @foreach($approvedResponses as $response)
        <div wire:key="story-{{ $response->id }}">
            <flux:text variant="subtle">{{ $response->question->question_text }}</flux:text>
            <flux:text>{{ $response->body }}</flux:text>
            @if($response->imageUrl())
                <img src="{{ $response->imageUrl() }}" alt="Story image" class="rounded-lg max-h-48 mt-2" />
            @endif
        </div>
    @endforeach
</flux:card>
@endif
```

**Integration**: Add `<livewire:users.community-stories-card :user="$user" />` to `resources/views/users/show.blade.php`

---

## Routes

Add to `routes/web.php` inside the auth middleware group:

```php
Volt::route('/community-stories', 'community-stories.index')
    ->name('community-stories.index')
    ->middleware(['auth', 'verified', 'ensure-dob', 'can:view-community-stories']);
```

---

## Factories

### `CommunityQuestionFactory`

**File**: `database/factories/CommunityQuestionFactory.php`

```php
public function definition(): array
{
    return [
        'question_text' => fake()->sentence() . '?',
        'description' => fake()->optional()->paragraph(),
        'status' => CommunityQuestionStatus::Draft,
        'start_date' => null,
        'end_date' => null,
        'created_by' => User::factory(),
    ];
}

public function active(): static { ... }   // status Active, start_date in past
public function scheduled(): static { ... } // status Scheduled, start_date in future
public function archived(): static { ... }  // status Archived, dates in past
```

### `CommunityResponseFactory`

**File**: `database/factories/CommunityResponseFactory.php`

```php
public function definition(): array
{
    return [
        'community_question_id' => CommunityQuestion::factory(),
        'user_id' => User::factory(),
        'body' => fake()->paragraphs(2, true),
        'status' => CommunityResponseStatus::Submitted,
    ];
}

public function approved(): static { ... }  // status Approved, approved_at set
public function rejected(): static { ... }  // status Rejected
```

### `QuestionSuggestionFactory`

**File**: `database/factories/QuestionSuggestionFactory.php`

```php
public function definition(): array
{
    return [
        'user_id' => User::factory(),
        'question_text' => fake()->sentence() . '?',
        'status' => QuestionSuggestionStatus::Suggested,
    ];
}
```

---

## Implementation Steps (execute in this exact order)

---

### Step 1: Enums

**Files to create**:
- `app/Enums/CommunityQuestionStatus.php`
- `app/Enums/CommunityResponseStatus.php`
- `app/Enums/QuestionSuggestionStatus.php`

Use the enum definitions from the Enums section above.

---

### Step 2: Migrations

**Files to create** (in this order):
1. `database/migrations/2026_03_11_100000_create_community_questions_table.php`
2. `database/migrations/2026_03_11_100001_create_community_responses_table.php`
3. `database/migrations/2026_03_11_100002_create_community_reactions_table.php`
4. `database/migrations/2026_03_11_100003_create_question_suggestions_table.php`

Use the schema from the Database Changes section above. Include proper `down()` methods that drop the tables.

**community_questions**:
```php
Schema::create('community_questions', function (Blueprint $table) {
    $table->id();
    $table->text('question_text');
    $table->text('description')->nullable();
    $table->string('status')->default('draft');
    $table->dateTime('start_date')->nullable();
    $table->dateTime('end_date')->nullable();
    $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
    $table->foreignId('suggested_by')->nullable()->constrained('users')->nullOnDelete();
    $table->foreignId('suggestion_id')->nullable()->constrained('question_suggestions')->nullOnDelete();
    $table->timestamps();

    $table->index('status');
    $table->index('start_date');
    $table->index('end_date');
});
```

**Important**: `question_suggestions` table must be created BEFORE `community_questions` because of the `suggestion_id` FK. Reorder migrations:

1. `2026_03_11_100000_create_question_suggestions_table.php`
2. `2026_03_11_100001_create_community_questions_table.php`
3. `2026_03_11_100002_create_community_responses_table.php`
4. `2026_03_11_100003_create_community_reactions_table.php`

**community_responses**:
```php
Schema::create('community_responses', function (Blueprint $table) {
    $table->id();
    $table->foreignId('community_question_id')->constrained()->cascadeOnDelete();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->text('body');
    $table->string('image_path')->nullable();
    $table->string('status')->default('submitted');
    $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
    $table->dateTime('reviewed_at')->nullable();
    $table->dateTime('approved_at')->nullable();
    $table->string('featured_in_blog_url')->nullable();
    $table->timestamps();

    $table->unique(['community_question_id', 'user_id']);
    $table->index('status');
});
```

**community_reactions**:
```php
Schema::create('community_reactions', function (Blueprint $table) {
    $table->id();
    $table->foreignId('community_response_id')->constrained()->cascadeOnDelete();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->string('emoji', 32);
    $table->timestamps();

    $table->unique(['community_response_id', 'user_id', 'emoji']);
});
```

**question_suggestions**:
```php
Schema::create('question_suggestions', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->text('question_text');
    $table->string('status')->default('suggested');
    $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
    $table->dateTime('reviewed_at')->nullable();
    $table->timestamps();

    $table->index('status');
});
```

---

### Step 3: Models

**Files to create**:
- `app/Models/CommunityQuestion.php`
- `app/Models/CommunityResponse.php`
- `app/Models/CommunityReaction.php`
- `app/Models/QuestionSuggestion.php`

**File to modify**: `app/Models/User.php`
- Add `communityResponses()` and `questionSuggestions()` relationships

Use the model definitions from the Models section above.

---

### Step 4: Factories

**Files to create**:
- `database/factories/CommunityQuestionFactory.php`
- `database/factories/CommunityResponseFactory.php`
- `database/factories/QuestionSuggestionFactory.php`

Use the factory definitions from the Factories section above.

---

### Step 5: Gates & Policies

**File to modify**: `app/Providers/AuthServiceProvider.php`
- Add the 4 gates from Authorization Rules section
- Register CommunityResponsePolicy and CommunityQuestionPolicy in `$policies`

**Files to create**:
- `app/Policies/CommunityResponsePolicy.php`
- `app/Policies/CommunityQuestionPolicy.php`

**CommunityResponsePolicy**:
```php
namespace App\Policies;

use App\Models\CommunityResponse;
use App\Models\User;
use App\Enums\StaffRank;

class CommunityResponsePolicy
{
    public function before(User $user, string $ability): ?bool
    {
        if ($user->isAdmin() || $user->isAtLeastRank(StaffRank::Officer)) {
            return true;
        }
        return null;
    }

    public function view(User $user, CommunityResponse $response): bool
    {
        return $response->isApproved() || $response->user_id === $user->id;
    }

    public function update(User $user, CommunityResponse $response): bool
    {
        return $response->isEditable() && $response->user_id === $user->id;
    }

    public function delete(User $user, CommunityResponse $response): bool
    {
        return $response->isEditable() && $response->user_id === $user->id;
    }
}
```

**CommunityQuestionPolicy**:
```php
namespace App\Policies;

use App\Models\CommunityQuestion;
use App\Models\User;
use App\Enums\StaffRank;

class CommunityQuestionPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        if ($user->isAdmin() || $user->isAtLeastRank(StaffRank::Officer)) {
            return true;
        }
        return null;
    }

    public function viewAny(User $user): bool
    {
        return $user->can('view-community-stories');
    }

    public function create(User $user): bool
    {
        return false; // Only via before() bypass
    }

    public function update(User $user, CommunityQuestion $question): bool
    {
        return false;
    }

    public function delete(User $user, CommunityQuestion $question): bool
    {
        return false;
    }
}
```

---

### Step 6: Action Classes

**Files to create** (in this order):
1. `app/Actions/CreateCommunityQuestion.php`
2. `app/Actions/UpdateCommunityQuestion.php`
3. `app/Actions/SubmitCommunityResponse.php`
4. `app/Actions/EditCommunityResponse.php`
5. `app/Actions/ModerateResponses.php`
6. `app/Actions/ToggleCommunityReaction.php`
7. `app/Actions/SubmitQuestionSuggestion.php`
8. `app/Actions/ReviewQuestionSuggestion.php`
9. `app/Actions/ProcessQuestionSchedule.php`

Use the action definitions from the Action Classes section above.

**Key implementation detail for `SubmitCommunityResponse`** — rank-based access to archived questions:

```php
public function handle(CommunityQuestion $question, User $user, string $body, ?UploadedFile $image = null): CommunityResponse
{
    // Prevent duplicate responses
    $existing = CommunityResponse::where('community_question_id', $question->id)
        ->where('user_id', $user->id)
        ->exists();

    if ($existing) {
        throw new \RuntimeException('You have already responded to this question.');
    }

    // Rank-based access check
    if ($question->isActive()) {
        // Any Traveler+ can respond to the active question
        if (! $user->isAtLeastLevel(MembershipLevel::Traveler)) {
            throw new \RuntimeException('You must be at least a Traveler to respond.');
        }
    } elseif ($question->isArchived()) {
        // Only Resident+ can respond to archived questions
        if (! $user->isAtLeastLevel(MembershipLevel::Resident)) {
            throw new \RuntimeException('You must be at least a Resident to respond to past questions.');
        }

        // Must have answered the current active question first
        $activeQuestion = CommunityQuestion::active()->first();
        if ($activeQuestion) {
            $hasAnsweredActive = CommunityResponse::where('community_question_id', $activeQuestion->id)
                ->where('user_id', $user->id)
                ->exists();
            if (! $hasAnsweredActive) {
                throw new \RuntimeException('You must answer the current question before responding to a past question.');
            }
        }

        // Can only respond to ONE archived question total (beyond the active one)
        $archivedResponseCount = CommunityResponse::where('user_id', $user->id)
            ->whereHas('question', fn ($q) => $q->archived())
            ->count();
        if ($archivedResponseCount >= 1) {
            throw new \RuntimeException('You may only respond to one past question.');
        }
    } else {
        throw new \RuntimeException('This question is not accepting responses.');
    }

    // Store image if provided
    $imagePath = null;
    if ($image) {
        $imagePath = $image->store('community-stories', 'public');
    }

    // Create response
    $response = CommunityResponse::create([
        'community_question_id' => $question->id,
        'user_id' => $user->id,
        'body' => $body,
        'image_path' => $imagePath,
        'status' => CommunityResponseStatus::Submitted,
    ]);

    RecordActivity::run($response, 'community_response_submitted',
        "Response submitted by {$user->name} to question #{$question->id}.");

    return $response;
}
```

---

### Step 7: Job Class

**File to create**: `app/Jobs/ProcessCommunityQuestionScheduleJob.php`

```php
namespace App\Jobs;

use App\Actions\ProcessQuestionSchedule;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessCommunityQuestionScheduleJob implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        ProcessQuestionSchedule::run();
    }
}
```

**File to modify**: `routes/console.php` (or `app/Console/Kernel.php` — check which pattern the project uses)

Add schedule:
```php
Schedule::job(new ProcessCommunityQuestionScheduleJob)->hourly();
```

---

### Step 8: Route

**File to modify**: `routes/web.php`

Add inside the appropriate auth middleware group:

```php
Volt::route('/community-stories', 'community-stories.index')
    ->name('community-stories.index')
    ->middleware(['auth', 'verified', 'ensure-dob', 'can:view-community-stories']);
```

---

### Step 9: Main Feature Page Volt Component

**File to create**: `resources/views/livewire/community-stories/index.blade.php`

This is the largest component. It contains:

**PHP class**:
- Uses `WithPagination`, `WithFileUploads`
- Public properties for all tabs (stories, past questions, manage)
- Staff management properties (conditional on authorization)
- All public and staff methods listed in the Livewire Volt Components section above

**Blade template structure**:

```blade
<div>
    {{-- Page header --}}
    <flux:heading size="lg">Community Logbook</flux:heading>
    <flux:text variant="subtle">Stories from the Lighthouse community</flux:text>

    {{-- Tab navigation --}}
    <div class="flex gap-4 my-6 border-b">
        <button wire:click="$set('activeTab', 'stories')" ...>Current Stories</button>
        <button wire:click="$set('activeTab', 'past-questions')" ...>Past Questions</button>
        @can('manage-community-stories')
            <button wire:click="$set('activeTab', 'manage')" ...>Manage</button>
        @endcan
    </div>

    {{-- Tab: Stories (default) --}}
    @if($activeTab === 'stories')
        {{-- Active question display --}}
        {{-- Response form (if user hasn't responded) --}}
        {{-- Approved response feed --}}
        {{-- Each response card: avatar, name, body, image, reactions --}}
    @endif

    {{-- Tab: Past Questions --}}
    @if($activeTab === 'past-questions')
        {{-- List of archived questions --}}
        {{-- Click to expand responses --}}
        {{-- Option to respond if Resident+ and eligible --}}
    @endif

    {{-- Tab: Manage --}}
    @can('manage-community-stories')
    @if($activeTab === 'manage')
        {{-- Sub-tabs: Pending Responses | Questions | Suggestions --}}

        {{-- Moderation table with bulk actions --}}
        {{-- Question management table + create form --}}
        {{-- Suggestions review table --}}
    @endif
    @endcan

    {{-- Modals --}}
    {{-- Response detail modal (for moderation) --}}
    {{-- Question create/edit modal --}}
    {{-- Confirm reject modal --}}
</div>
```

**Emoji reactions implementation**:
- Use a fixed set of allowed emojis (e.g., ❤️, 😂, 🙏, 👏, 🔥, ⛵)
- Display reaction counts grouped by emoji under each response
- Toggle on click via `wire:click="toggleReaction({{ $response->id }}, '❤️')"`

**Suggest question** (Citizens):
```blade
@can('suggest-community-question')
    {{-- Simple form: textarea + submit button --}}
    {{-- Placed on the Past Questions tab or as a section on Stories tab --}}
@endcan
```

---

### Step 10: Dashboard Widget

**File to create**: `resources/views/livewire/dashboard/community-question-widget.blade.php`

**File to modify**: The dashboard page that includes widgets — find the dashboard Blade file and add:
```blade
<livewire:dashboard.community-question-widget />
```

Implementation follows the Dashboard Widget section above.

---

### Step 11: Profile Integration

**File to create**: `resources/views/livewire/users/community-stories-card.blade.php`

**File to modify**: `resources/views/users/show.blade.php`

Add after the registration answer card:
```blade
<div class="my-6">
    <livewire:users.community-stories-card :user="$user" />
</div>
```

---

### Step 12: Tests — Actions

**Files to create**:

#### `tests/Feature/Actions/CommunityStories/SubmitCommunityResponseTest.php`
```
uses()->group('community-stories', 'actions');

it('allows a traveler to submit a response to the active question')
it('prevents duplicate responses to the same question')
it('prevents a drifter from submitting a response')
it('prevents a stowaway from submitting a response')
it('stores an uploaded image')
it('prevents response to a draft question')
it('prevents response to a scheduled question')
it('allows a resident to respond to one archived question after answering the active question')
it('prevents a traveler from responding to an archived question')
it('prevents responding to a second archived question')
it('requires answering the active question before an archived question')
it('records activity when response is submitted')
```

#### `tests/Feature/Actions/CommunityStories/ModerateResponsesTest.php`
```
uses()->group('community-stories', 'actions');

it('approves a single response')
it('bulk approves multiple responses')
it('sets reviewed_by and reviewed_at on approval')
it('sets approved_at on approval')
it('rejects a single response')
it('bulk rejects multiple responses')
it('records activity for each moderated response')
```

#### `tests/Feature/Actions/CommunityStories/CreateCommunityQuestionTest.php`
```
uses()->group('community-stories', 'actions');

it('creates a question in draft status')
it('creates a question with scheduled status and dates')
it('links suggestion when created from a suggestion')
it('records activity')
```

#### `tests/Feature/Actions/CommunityStories/ToggleCommunityReactionTest.php`
```
uses()->group('community-stories', 'actions');

it('adds a reaction')
it('removes an existing reaction')
it('allows multiple different emoji reactions from same user')
it('prevents duplicate same-emoji reactions')
```

#### `tests/Feature/Actions/CommunityStories/SubmitQuestionSuggestionTest.php`
```
uses()->group('community-stories', 'actions');

it('creates a suggestion with suggested status')
it('records activity')
```

#### `tests/Feature/Actions/CommunityStories/ReviewQuestionSuggestionTest.php`
```
uses()->group('community-stories', 'actions');

it('approves a suggestion')
it('rejects a suggestion')
it('sets reviewed_by and reviewed_at')
it('records activity')
```

#### `tests/Feature/Actions/CommunityStories/ProcessQuestionScheduleTest.php`
```
uses()->group('community-stories', 'actions');

it('activates a scheduled question whose start_date has passed')
it('archives an active question whose end_date has passed')
it('does not change draft questions')
it('does not activate a question whose start_date is in the future')
```

---

### Step 13: Tests — Authorization

**File to create**: `tests/Feature/Policies/CommunityStoriesAuthorizationTest.php`
```
uses()->group('community-stories', 'policies');

it('allows traveler to view community stories')
it('denies drifter from viewing community stories')
it('denies stowaway from viewing community stories')
it('denies user in brig from viewing community stories')
it('allows citizen to suggest a question')
it('denies traveler from suggesting a question')
it('allows officer to manage community stories')
it('denies crew member from managing community stories')
it('allows admin to manage community stories')
it('allows user to edit own unapproved response')
it('denies user from editing approved response')
it('denies user from editing another user response')
it('allows staff to edit unapproved response')
```

---

## Edge Cases

1. **No active question**: Dashboard widget and stories tab should gracefully show "No active question" message.
2. **User deletes account**: Cascade deletes handle response and suggestion cleanup.
3. **Staff deletes question with responses**: Only allow if no approved responses exist. Submitted/rejected responses cascade delete with the question.
4. **Image cleanup**: When a response is deleted or image is replaced, delete the old file from storage.
5. **Concurrent moderation**: Two staff moderating the same response — the unique constraint on reviewed_by prevents issues; last write wins for status.
6. **Archived question response limit**: The "one archived question" limit is per user total (across all time), not per active question cycle. This keeps the logic simple and prevents gaming.
7. **Question schedule overlap**: The ProcessQuestionSchedule action should handle the edge case where a new question is activated while another is still active — archive the old one first.
8. **Empty response body**: Validate minimum length (e.g., 20 characters) to encourage meaningful responses.
9. **Large images**: Validate max file size (2MB) and image type. Store in `community-stories/` subdirectory.
10. **Reaction emoji set**: Use a fixed allowlist of 6 emojis to prevent abuse. Validate in ToggleCommunityReaction.

## Known Risks

1. **Image storage growth**: Community images will accumulate over time. Consider a cleanup job for orphaned images in the future.
2. **Moderation backlog**: If many responses come in at once, bulk moderation is critical (implemented via ModerateResponses action).
3. **Schedule job failure**: If the hourly job fails, questions won't transition. Staff can manually change status as a fallback via the manage tab.

## Rollout Notes

- No feature flags needed — the gates control access.
- No data backfill needed — this is a new feature with empty tables.
- Can be deployed without downtime — just run migrations.
- After deployment, staff should create the first question via the Manage tab.
- Run `php artisan storage:link` if not already done (for image serving).

## Definition of Done

- [ ] `php artisan migrate:fresh` passes
- [ ] `./vendor/bin/pest` passes with zero failures
- [ ] All test cases from this plan are implemented
- [ ] No ad-hoc auth checks in Blade templates (gates/policies only)
- [ ] Dashboard widget displays correctly
- [ ] Profile integration shows approved stories
- [ ] Staff management tools work (CRUD questions, moderate responses, review suggestions)
- [ ] Image upload/display works
- [ ] Emoji reactions work
- [ ] Rank-based response permissions enforced
- [ ] Scheduled job transitions question states correctly
