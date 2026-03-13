# Community Questions & Stories — Technical Documentation

> **Audience:** Project owner, developers, AI agents
> **Generated:** 2026-03-11
> **Generator:** `/document-feature` skill

---

## Table of Contents

1. [Overview](#1-overview)
2. [Database Schema](#2-database-schema)
3. [Models & Relationships](#3-models--relationships)
4. [Enums Reference](#4-enums-reference)
5. [Authorization & Permissions](#5-authorization--permissions)
6. [Routes](#6-routes)
7. [User Interface Components](#7-user-interface-components)
8. [Actions (Business Logic)](#8-actions-business-logic)
9. [Notifications](#9-notifications)
10. [Background Jobs](#10-background-jobs)
11. [Console Commands & Scheduled Tasks](#11-console-commands--scheduled-tasks)
12. [Services](#12-services)
13. [Activity Log Entries](#13-activity-log-entries)
14. [Data Flow Diagrams](#14-data-flow-diagrams)
15. [Configuration](#15-configuration)
16. [Test Coverage](#16-test-coverage)
17. [File Map](#17-file-map)
18. [Known Issues & Improvement Opportunities](#18-known-issues--improvement-opportunities)

---

## 1. Overview

Community Questions & Stories is a moderated storytelling feature where staff publish rotating questions, members respond with personal stories, and approved responses become a public community archive. The feature builds community identity through shared experiences on the Lighthouse Minecraft server.

The feature is managed primarily by the **Chaplain department** (Jr Crew rank and above), with **Command Officers** and **Admins** as backup. Regular members participate according to their membership level: **Travelers** (and above) can view stories and respond to the active question, **Residents** (and above) can additionally respond to one archived question per active-question cycle, and **Citizens** (and above) can suggest new questions for staff to consider.

Access is via a dashboard widget and a dedicated `/community-stories` page (no main nav/sidebar link). The page has three public-facing tabs (Stories, Past Questions) plus a staff-only Manage tab with sub-sections for pending response moderation, question CRUD, and suggestion review. Approved responses display on the community stories page with emoji reactions (❤️, 😂, 🙏, 👏, 🔥, ⛵) and on user profile pages.

An hourly scheduled command (`community:process-schedule`) handles automatic lifecycle transitions — activating draft questions with a start date when that date arrives, and archiving active questions when their end date passes.

---

## 2. Database Schema

### `question_suggestions` table

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| id | bigIncrements | No | — | PK |
| user_id | foreignId | No | — | FK → users, cascade delete |
| question_text | text | No | — | |
| status | string | No | `'suggested'` | QuestionSuggestionStatus enum |
| reviewed_by | foreignId | Yes | null | FK → users, null on delete |
| reviewed_at | dateTime | Yes | null | |
| created_at | timestamp | No | — | |
| updated_at | timestamp | No | — | |

**Indexes:** `status`
**Foreign Keys:** `user_id` → `users.id` (cascade), `reviewed_by` → `users.id` (null on delete)
**Migration:** `database/migrations/2026_03_11_100000_create_question_suggestions_table.php`

### `community_questions` table

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| id | bigIncrements | No | — | PK |
| question_text | text | No | — | |
| description | text | Yes | null | Optional context for the question |
| status | string | No | `'draft'` | CommunityQuestionStatus enum |
| start_date | dateTime | Yes | null | When question becomes active |
| end_date | dateTime | Yes | null | When question auto-archives |
| created_by | foreignId | No | — | FK → users, cascade delete |
| suggested_by | foreignId | Yes | null | FK → users, null on delete |
| suggestion_id | foreignId | Yes | null | FK → question_suggestions, null on delete |
| created_at | timestamp | No | — | |
| updated_at | timestamp | No | — | |

**Indexes:** `status`, `start_date`, `end_date`
**Foreign Keys:** `created_by` → `users.id` (cascade), `suggested_by` → `users.id` (null on delete), `suggestion_id` → `question_suggestions.id` (null on delete)
**Migration:** `database/migrations/2026_03_11_100001_create_community_questions_table.php`

### `community_responses` table

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| id | bigIncrements | No | — | PK |
| community_question_id | foreignId | No | — | FK → community_questions, cascade delete |
| user_id | foreignId | No | — | FK → users, cascade delete |
| body | text | No | — | |
| image_path | string | Yes | null | Path in `public` disk |
| status | string | No | `'submitted'` | CommunityResponseStatus enum |
| reviewed_by | foreignId | Yes | null | FK → users, null on delete |
| reviewed_at | dateTime | Yes | null | |
| approved_at | dateTime | Yes | null | |
| featured_in_blog_url | string | Yes | null | |
| created_at | timestamp | No | — | |
| updated_at | timestamp | No | — | |

**Indexes:** `status`; unique constraint on `[community_question_id, user_id]`
**Foreign Keys:** `community_question_id` → `community_questions.id` (cascade), `user_id` → `users.id` (cascade), `reviewed_by` → `users.id` (null on delete)
**Migration:** `database/migrations/2026_03_11_100002_create_community_responses_table.php`

### `community_reactions` table

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| id | bigIncrements | No | — | PK |
| community_response_id | foreignId | No | — | FK → community_responses, cascade delete |
| user_id | foreignId | No | — | FK → users, cascade delete |
| emoji | string(32) | No | — | One of the 6 allowed emojis |
| created_at | timestamp | No | — | |
| updated_at | timestamp | No | — | |

**Indexes:** unique constraint on `[community_response_id, user_id, emoji]`
**Foreign Keys:** `community_response_id` → `community_responses.id` (cascade), `user_id` → `users.id` (cascade)
**Migration:** `database/migrations/2026_03_11_100003_create_community_reactions_table.php`

---

## 3. Models & Relationships

### CommunityQuestion (`app/Models/CommunityQuestion.php`)

**Relationships:**

| Method | Type | Related Model | Notes |
|--------|------|---------------|-------|
| `responses()` | hasMany | CommunityResponse | All responses to this question |
| `creator()` | belongsTo | User | FK: `created_by` |
| `suggester()` | belongsTo | User | FK: `suggested_by`, nullable |
| `suggestion()` | belongsTo | QuestionSuggestion | FK: `suggestion_id`, nullable |

**Scopes:**
- `scopeActive($query)` — filters to `status = Active`
- `scopeArchived($query)` — filters to `status = Archived`
- `scopePendingActivation($query)` — filters to `status = Draft` with a non-null `start_date`

**Key Methods:**
- `approvedResponses(): HasMany` — responses filtered to `status = Approved`
- `isActive(): bool` — checks if status is Active
- `isArchived(): bool` — checks if status is Archived
- `isDraft(): bool` — checks if status is Draft

**Casts:**
- `status` => `CommunityQuestionStatus`
- `start_date` => `datetime`
- `end_date` => `datetime`

### CommunityResponse (`app/Models/CommunityResponse.php`)

**Relationships:**

| Method | Type | Related Model | Notes |
|--------|------|---------------|-------|
| `question()` | belongsTo | CommunityQuestion | FK: `community_question_id` |
| `user()` | belongsTo | User | |
| `reviewer()` | belongsTo | User | FK: `reviewed_by`, nullable |
| `reactions()` | hasMany | CommunityReaction | |

**Scopes:**
- `scopeApproved($query)` — filters to `status = Approved`
- `scopePendingReview($query)` — filters to `status IN (Submitted, UnderReview)`

**Key Methods:**
- `isApproved(): bool` — checks if status is Approved
- `isEditable(): bool` — true when status is Submitted or UnderReview
- `imageUrl(): ?string` — returns full asset URL for stored image, or null

**Casts:**
- `status` => `CommunityResponseStatus`
- `reviewed_at` => `datetime`
- `approved_at` => `datetime`

### CommunityReaction (`app/Models/CommunityReaction.php`)

**Relationships:**

| Method | Type | Related Model | Notes |
|--------|------|---------------|-------|
| `response()` | belongsTo | CommunityResponse | FK: `community_response_id` |
| `user()` | belongsTo | User | |

**Scopes:** None
**Key Methods:** None
**Casts:** None (no HasFactory trait)

### QuestionSuggestion (`app/Models/QuestionSuggestion.php`)

**Relationships:**

| Method | Type | Related Model | Notes |
|--------|------|---------------|-------|
| `user()` | belongsTo | User | The member who suggested |
| `reviewer()` | belongsTo | User | FK: `reviewed_by`, nullable |

**Scopes:** None
**Key Methods:** None

**Casts:**
- `status` => `QuestionSuggestionStatus`
- `reviewed_at` => `datetime`

### User (`app/Models/User.php`) — Added Relationships

| Method | Type | Related Model | Notes |
|--------|------|---------------|-------|
| `communityResponses()` | hasMany | CommunityResponse | |
| `questionSuggestions()` | hasMany | QuestionSuggestion | |

---

## 4. Enums Reference

### CommunityQuestionStatus (`app/Enums/CommunityQuestionStatus.php`)

| Case | Value | Label | Color | Notes |
|------|-------|-------|-------|-------|
| Draft | `'draft'` | Draft | zinc | Default; not visible to members. If `start_date` is set, auto-activates when that date arrives. |
| Active | `'active'` | Active | emerald | Currently accepting responses |
| Archived | `'archived'` | Archived | amber | Past question; Resident+ can respond |

Helper methods: `label(): string`, `color(): string`

### CommunityResponseStatus (`app/Enums/CommunityResponseStatus.php`)

| Case | Value | Label | Color | Notes |
|------|-------|-------|-------|-------|
| Submitted | `'submitted'` | Submitted | sky | Default; awaiting review |
| UnderReview | `'under_review'` | Under Review | amber | Staff is reviewing |
| Approved | `'approved'` | Approved | emerald | Visible publicly |
| Rejected | `'rejected'` | Rejected | red | Not shown publicly |
| Archived | `'archived'` | Archived | zinc | |

Helper methods: `label(): string`, `color(): string`

### QuestionSuggestionStatus (`app/Enums/QuestionSuggestionStatus.php`)

| Case | Value | Label | Notes |
|------|-------|-------|-------|
| Suggested | `'suggested'` | Suggested | Default; pending review |
| Approved | `'approved'` | Approved | Auto-creates a draft question |
| Rejected | `'rejected'` | Rejected | No question created |

Helper methods: `label(): string`

---

## 5. Authorization & Permissions

### Gates (from `AuthServiceProvider`)

| Gate Name | Who Can Pass | Logic Summary |
|-----------|-------------|---------------|
| `view-community-stories` | Traveler+ (not in brig) | `!in_brig && isAtLeastLevel(Traveler)` |
| `submit-community-response` | Traveler+ (not in brig) | `!in_brig && isAtLeastLevel(Traveler)` |
| `suggest-community-question` | Citizen+ (not in brig) | `!in_brig && isAtLeastLevel(Citizen)` |
| `manage-community-stories` | Admin, Command Officers, Chaplain Jr Crew+ | `hasRole('Admin') \|\| (Officer+ in Command) \|\| (JrCrew+ in Chaplain)` |

### Policies

#### CommunityQuestionPolicy (`app/Policies/CommunityQuestionPolicy.php`)

**`before()` hook:** Admins and Command Officers (Officer rank+ in Command department) bypass all checks and return `true`.

| Ability | Who Can | Conditions |
|---------|---------|------------|
| `viewAny` | Anyone who passes `view-community-stories` gate | Delegates to gate |
| `create` | Anyone who passes `manage-community-stories` gate | Delegates to gate |
| `update` | Anyone who passes `manage-community-stories` gate | Delegates to gate |
| `delete` | Manage gate + no approved responses | `can('manage-community-stories') && approvedResponses()->doesntExist()` |

#### CommunityResponsePolicy (`app/Policies/CommunityResponsePolicy.php`)

**`before()` hook:** Admins and Command Officers (Officer rank+ in Command department) bypass all checks and return `true`.

| Ability | Who Can | Conditions |
|---------|---------|------------|
| `view` | Owner or anyone (if approved) | `response->isApproved() \|\| response->user_id === user->id` |
| `update` | Owner only (if editable) | `response->isEditable() && response->user_id === user->id` |
| `delete` | Owner only (if editable) | `response->isEditable() && response->user_id === user->id` |

### Permissions Matrix

| User Type | View Stories | Submit Response (Active) | Submit Response (Archived) | Suggest Question | Manage (Moderate/CRUD) |
|-----------|:-----------:|:-----------------------:|:-------------------------:|:----------------:|:---------------------:|
| Stowaway | No | No | No | No | No |
| Drifter | No | No | No | No | No |
| Traveler | Yes | Yes | No | No | No |
| Resident | Yes | Yes | Yes (1 per cycle) | No | No |
| Citizen | Yes | Yes | Yes (1 per cycle) | Yes | No |
| Chaplain Jr Crew+ | Yes | Yes | Yes | Yes | Yes |
| Command Officer | Yes | Yes | Yes | Yes | Yes |
| Admin | Yes | Yes | Yes | Yes | Yes |
| Any user in brig | No | No | No | No | Depends on role |

---

## 6. Routes

| Method | URL | Middleware | Handler | Route Name |
|--------|-----|-----------|---------|------------|
| GET | `/community-stories` | `auth`, `verified`, `ensure-dob`, `can:view-community-stories` | Volt component `community-stories.index` | `community-stories.index` |

---

## 7. User Interface Components

### Community Stories Index Page
**File:** `resources/views/livewire/community-stories/index.blade.php`
**Route:** `/community-stories` (route name: `community-stories.index`)

**Purpose:** Main feature page with tabbed interface for viewing stories, past questions, and staff management.

**Authorization:** Route-level `can:view-community-stories` gate; staff tabs gated by `@can('manage-community-stories')`.

**User Actions Available:**
- Submit response to active question → calls `SubmitCommunityResponse::run()`
- Submit response to archived question → calls `SubmitCommunityResponse::run()` (Resident+ only)
- Edit own unapproved response → calls `EditCommunityResponse::run()`
- Delete own unapproved response → deletes directly with policy check
- Toggle emoji reaction on approved response → calls `ToggleCommunityReaction::run()`
- Suggest a question (Citizen+) → calls `SubmitQuestionSuggestion::run()`
- Staff: Approve/reject individual or bulk responses → calls `ModerateResponses::run()`
- Staff: Create/edit/delete questions → calls `CreateCommunityQuestion::run()` / `UpdateCommunityQuestion::run()`
- Staff: Approve/reject suggestions → calls `ReviewQuestionSuggestion::run()`

**UI Elements:**
- Three tabs: "Stories" (default), "Past Questions", "Manage" (staff only)
- Stories tab: active question display, response form (textarea + image upload), paginated approved response feed with emoji reaction buttons
- Past Questions tab: collapsible list of archived questions with response form for eligible users
- Manage tab with sub-tabs: Pending Responses (table with checkboxes, bulk approve/reject), Questions (CRUD table with create/edit modals), Suggestions (review table with approve/reject)
- Modals: response detail/review, question create/edit, user response edit

### Dashboard Widget
**File:** `resources/views/livewire/dashboard/community-question-widget.blade.php`
**Route:** Embedded in `resources/views/dashboard.blade.php` inside `@can('view-community-content')` grid

**Purpose:** Shows current active question and quick response form on the dashboard.

**Authorization:** Parent grid gated by `@can('view-community-content')`

**User Actions Available:**
- Submit response to active question → calls `SubmitCommunityResponse::run()`

**UI Elements:**
- Active question text display
- Response form (textarea + image upload) if user hasn't answered
- Random approved response display after user has answered
- Link to full community stories page

### Profile Stories Card
**File:** `resources/views/livewire/users/community-stories-card.blade.php`
**Route:** Embedded in `resources/views/users/show.blade.php`

**Purpose:** Displays a user's approved community responses on their profile page.

**Authorization:** Renders only if the viewed user has approved responses.

**UI Elements:**
- List of approved responses with question text, body, optional image, and date
- Only visible when user has at least one approved response

---

## 8. Actions (Business Logic)

### CreateCommunityQuestion (`app/Actions/CreateCommunityQuestion.php`)

**Signature:** `handle(User $staff, string $questionText, ?string $description, CommunityQuestionStatus $status, ?Carbon $startDate, ?Carbon $endDate, ?int $suggestionId, ?int $suggestedBy): CommunityQuestion`

**Step-by-step logic:**
1. Creates `CommunityQuestion` with provided fields
2. Logs activity: `RecordActivity::run($question, 'community_question_created', "Community question created by {$staff->name}.")`
3. Returns created question

**Called by:** Volt component (manage tab), `ReviewQuestionSuggestion::run()` (on approval)

### UpdateCommunityQuestion (`app/Actions/UpdateCommunityQuestion.php`)

**Signature:** `handle(CommunityQuestion $question, User $staff, array $data): CommunityQuestion`

**Step-by-step logic:**
1. Updates question with `$data` array
2. Logs activity: `RecordActivity::run($question, 'community_question_updated', "Community question updated by {$staff->name}. Status: {$question->status->value}.")`
3. Returns refreshed question

**Called by:** Volt component (manage tab, question edit modal)

### SubmitCommunityResponse (`app/Actions/SubmitCommunityResponse.php`)

**Signature:** `handle(CommunityQuestion $question, User $user, string $body, ?UploadedFile $image): CommunityResponse`

**Step-by-step logic:**
1. Checks for duplicate response (same user + question) → throws `RuntimeException`
2. If question is **active**: requires Traveler+ membership level
3. If question is **archived**:
   - Requires Resident+ membership level
   - Requires user has answered the current active question
   - Enforces per-cycle limit: max 1 archived-question response since the active question's `start_date`
4. If question is draft: throws `RuntimeException`
5. Stores uploaded image to `community-stories` directory on `public` disk (if provided)
6. Creates `CommunityResponse` with status `Submitted`
7. Logs activity: `RecordActivity::run($response, 'community_response_submitted', ...)`
8. Returns created response

**Called by:** Volt component (stories tab, past questions tab), dashboard widget

### EditCommunityResponse (`app/Actions/EditCommunityResponse.php`)

**Signature:** `handle(CommunityResponse $response, User $editor, string $body, ?UploadedFile $newImage, bool $removeImage): CommunityResponse`

**Step-by-step logic:**
1. Validates response is editable (Submitted or UnderReview) → throws `RuntimeException` if not
2. Updates body text
3. Handles image: removes old image if `$removeImage`, replaces with new image if `$newImage` provided
4. Saves response
5. Logs activity: `RecordActivity::run($response, 'community_response_edited', ...)`
6. Returns updated response

**Called by:** Volt component (user edit modal)

### ModerateResponses (`app/Actions/ModerateResponses.php`)

**Signature:** `handle(Collection $responses, User $staff, CommunityResponseStatus $outcome): int`

**Step-by-step logic:**
1. Validates outcome is Approved or Rejected → throws `InvalidArgumentException`
2. Iterates through responses, skipping non-editable ones (already approved/rejected)
3. For each editable response: sets status, `reviewed_by`, `reviewed_at`; sets `approved_at` if approving
4. Logs activity per response: `community_response_approved` or `community_response_rejected`
5. Returns count of moderated responses

**Called by:** Volt component (manage tab, pending responses sub-tab)

### ToggleCommunityReaction (`app/Actions/ToggleCommunityReaction.php`)

**Signature:** `handle(CommunityResponse $response, User $user, string $emoji): bool`

**Allowed emojis:** `['❤️', '😂', '🙏', '👏', '🔥', '⛵']` (defined as `ALLOWED_EMOJIS` constant)

**Step-by-step logic:**
1. Validates emoji is in allowed set → throws `InvalidArgumentException`
2. Checks for existing reaction (same response + user + emoji)
3. If exists: deletes it, returns `false`
4. If not exists: creates it, returns `true`

**Called by:** Volt component (emoji reaction buttons on approved responses)

### SubmitQuestionSuggestion (`app/Actions/SubmitQuestionSuggestion.php`)

**Signature:** `handle(User $user, string $questionText): QuestionSuggestion`

**Step-by-step logic:**
1. Creates `QuestionSuggestion` with status `Suggested`
2. Logs activity: `RecordActivity::run($suggestion, 'question_suggestion_submitted', ...)`
3. Returns created suggestion

**Called by:** Volt component (suggest question form)

### ReviewQuestionSuggestion (`app/Actions/ReviewQuestionSuggestion.php`)

**Signature:** `handle(QuestionSuggestion $suggestion, User $staff, QuestionSuggestionStatus $outcome): QuestionSuggestion`

**Step-by-step logic:**
1. Updates suggestion: status, `reviewed_by`, `reviewed_at`
2. Logs activity: `RecordActivity::run($suggestion, 'question_suggestion_reviewed', ...)`
3. If approved: calls `CreateCommunityQuestion::run()` with suggestion text, status=Draft, links `suggestion_id` and `suggested_by`
4. Returns refreshed suggestion

**Called by:** Volt component (manage tab, suggestions sub-tab)

### ProcessQuestionSchedule (`app/Actions/ProcessQuestionSchedule.php`)

**Signature:** `handle(): array`

**Step-by-step logic:**
1. Finds active questions with `end_date <= now()` → archives them, logs `community_question_archived`
2. Finds draft questions with a `start_date <= now()` (pending activation):
   - Archives any currently active questions first (logs `community_question_archived`)
   - Activates the draft question (logs `community_question_activated`)
   - Archives any other stale draft questions with overdue start dates
3. Returns `['activated' => int, 'archived' => int]`

**Called by:** `ProcessCommunityQuestionSchedule` artisan command

---

## 9. Notifications

Not applicable for this feature.

---

## 10. Background Jobs

Not applicable for this feature.

---

## 11. Console Commands & Scheduled Tasks

### `community:process-schedule`
**File:** `app/Console/Commands/ProcessCommunityQuestionSchedule.php`
**Scheduled:** Yes — hourly, runs in background (`routes/console.php`)
**What it does:** Calls `ProcessQuestionSchedule::run()` to activate draft questions (with a `start_date`) past their start date and archive active questions past their end date. Outputs summary of changes or "no changes needed" message.

---

## 12. Services

Not applicable for this feature.

---

## 13. Activity Log Entries

| Action String | Logged By | Subject Model | Description Pattern |
|---------------|-----------|---------------|---------------------|
| `community_question_created` | CreateCommunityQuestion | CommunityQuestion | "Community question created by {name}." |
| `community_question_updated` | UpdateCommunityQuestion | CommunityQuestion | "Community question updated by {name}. Status: {status}." |
| `community_question_archived` | ProcessQuestionSchedule | CommunityQuestion | "Question #{id} auto-archived (end date passed)." or "...auto-archived (replaced by question #{id})." |
| `community_question_activated` | ProcessQuestionSchedule | CommunityQuestion | "Question #{id} auto-activated (start date reached)." |
| `community_response_submitted` | SubmitCommunityResponse | CommunityResponse | "Response submitted by {name} to question #{id}." |
| `community_response_edited` | EditCommunityResponse | CommunityResponse | "Response #{id} edited by {name}." |
| `community_response_approved` | ModerateResponses | CommunityResponse | "Response #{id} approved by {name}." |
| `community_response_rejected` | ModerateResponses | CommunityResponse | "Response #{id} rejected by {name}." |
| `question_suggestion_submitted` | SubmitQuestionSuggestion | QuestionSuggestion | "Question suggested by {name}." |
| `question_suggestion_reviewed` | ReviewQuestionSuggestion | QuestionSuggestion | "Suggestion #{id} {status} by {name}." |

---

## 14. Data Flow Diagrams

### Submitting a Response to the Active Question

```
User clicks "Submit" on Stories tab (or dashboard widget)
  -> POST /community-stories (Livewire action)
    -> VoltComponent::submitResponse()
      -> $this->authorize('submit-community-response')
      -> $this->validate(['body' => 'required|string|min:20', 'image' => 'nullable|image|max:2048'])
      -> SubmitCommunityResponse::run($question, $user, $body, $image)
        -> Duplicate check (unique constraint: question + user)
        -> Rank check (Traveler+ for active)
        -> Image stored to 'community-stories' on public disk
        -> CommunityResponse created with status Submitted
        -> RecordActivity::run(...)
      -> Flux::toast('Response submitted!', variant: 'success')
```

### Moderating Responses (Staff)

```
Staff selects responses + clicks "Approve" or "Reject" on Manage > Pending tab
  -> POST /community-stories (Livewire action)
    -> VoltComponent::moderateSelected($outcome)
      -> $this->authorize('manage-community-stories')
      -> ModerateResponses::run($selectedResponses, $staff, $outcome)
        -> For each editable response:
          -> Set status, reviewed_by, reviewed_at (+ approved_at if approving)
          -> RecordActivity::run(...) per response
        -> Returns count of moderated responses
      -> Flux::toast("{$count} responses {$outcome}!", variant: 'success')
```

### Suggesting a Question (Citizen+)

```
Citizen clicks "Suggest Question" and submits form
  -> POST /community-stories (Livewire action)
    -> VoltComponent::suggestQuestion()
      -> $this->authorize('suggest-community-question')
      -> $this->validate(['suggestionText' => 'required|string|min:10'])
      -> SubmitQuestionSuggestion::run($user, $suggestionText)
        -> QuestionSuggestion created with status Suggested
        -> RecordActivity::run(...)
      -> Flux::toast('Suggestion submitted!', variant: 'success')
```

### Reviewing a Suggestion (Staff)

```
Staff clicks "Approve" or "Reject" on a suggestion
  -> POST /community-stories (Livewire action)
    -> VoltComponent::reviewSuggestion($suggestion, $outcome)
      -> $this->authorize('manage-community-stories')
      -> ReviewQuestionSuggestion::run($suggestion, $staff, $outcome)
        -> Suggestion updated: status, reviewed_by, reviewed_at
        -> RecordActivity::run(...)
        -> If approved: CreateCommunityQuestion::run(...)
          -> CommunityQuestion created (Draft, linked to suggestion)
          -> RecordActivity::run(...)
      -> Flux::toast('Suggestion reviewed!', variant: 'success')
```

### Creating a Question (Staff)

```
Staff fills out question form on Manage > Questions tab
  -> POST /community-stories (Livewire action)
    -> VoltComponent::createQuestion()
      -> $this->authorize('manage-community-stories')
      -> $this->validate([...])
      -> CreateCommunityQuestion::run($staff, $text, $description, $status, $startDate, $endDate)
        -> CommunityQuestion created
        -> RecordActivity::run(...)
      -> Flux::toast('Question created!', variant: 'success')
```

### Automatic Question Lifecycle

```
Hourly cron triggers community:process-schedule
  -> ProcessCommunityQuestionSchedule::handle()
    -> ProcessQuestionSchedule::run()
      -> Find active questions with end_date <= now()
        -> Archive each, log activity
      -> Find draft questions with start_date <= now() (pending activation)
        -> Archive any currently active question first, log activity
        -> Activate the draft question, log activity
        -> Archive any other stale drafts with overdue start dates
    -> Output summary to console
```

### Toggling Emoji Reaction

```
User clicks emoji button on an approved response
  -> POST /community-stories (Livewire action)
    -> VoltComponent::toggleReaction($responseId, $emoji)
      -> ToggleCommunityReaction::run($response, $user, $emoji)
        -> Validate emoji in allowed set
        -> If existing reaction: delete (toggle off) → return false
        -> If no reaction: create (toggle on) → return true
      -> UI updates reaction counts
```

---

## 15. Configuration

Not applicable for this feature. No custom env variables or config values are used. The allowed emoji set is hardcoded as a constant in `ToggleCommunityReaction::ALLOWED_EMOJIS`.

---

## 16. Test Coverage

### Test Files

| File | Tests | What It Covers |
|------|-------|----------------|
| `tests/Feature/Actions/CommunityStories/SubmitCommunityResponseTest.php` | 11 | Response submission, rank access, duplicate prevention, archived question rules, activity logging |
| `tests/Feature/Actions/CommunityStories/ModerateResponsesTest.php` | 8 | Approve/reject single and bulk, metadata fields, activity logging, skip already-approved |
| `tests/Feature/Actions/CommunityStories/CreateCommunityQuestionTest.php` | 4 | Draft creation (with/without dates), suggestion linking, activity logging |
| `tests/Feature/Actions/CommunityStories/ToggleCommunityReactionTest.php` | 4 | Add/remove toggle, emoji replacement, invalid emoji rejection |
| `tests/Feature/Actions/CommunityStories/SubmitQuestionSuggestionTest.php` | 2 | Suggestion creation, activity logging |
| `tests/Feature/Actions/CommunityStories/ReviewQuestionSuggestionTest.php` | 4 | Approve (auto-creates draft), reject, metadata, activity logging |
| `tests/Feature/Actions/CommunityStories/ProcessQuestionScheduleTest.php` | 5 | Activation, archival, replacement, draft immunity, future immunity |
| `tests/Feature/Policies/CommunityStoriesAuthorizationTest.php` | 15 | All gate combinations, policy checks (view, update, delete) |

**Total: 53 tests**

### Test Case Inventory

**SubmitCommunityResponseTest:**
- `it('allows a traveler to submit a response to the active question')`
- `it('prevents duplicate responses to the same question')`
- `it('prevents a drifter from submitting a response')`
- `it('prevents a stowaway from submitting a response')`
- `it('prevents response to a draft question')`
- `it('prevents response to a draft question with scheduled dates')`
- `it('allows a resident to respond to one archived question after answering the active question')`
- `it('prevents a traveler from responding to an archived question')`
- `it('prevents responding to a second archived question in the same cycle')`
- `it('requires answering the active question before an archived question')`
- `it('records activity when response is submitted')`

**ModerateResponsesTest:**
- `it('approves a single response')`
- `it('bulk approves multiple responses')`
- `it('sets reviewed_by and reviewed_at on approval')`
- `it('sets approved_at on approval')`
- `it('rejects a single response')`
- `it('bulk rejects multiple responses')`
- `it('records activity for each moderated response')`
- `it('skips already approved responses')`

**CreateCommunityQuestionTest:**
- `it('creates a question in draft status')`
- `it('creates a draft question with scheduled dates')`
- `it('links suggestion when created from a suggestion')`
- `it('records activity')`

**ToggleCommunityReactionTest:**
- `it('adds a reaction')`
- `it('removes an existing reaction')`
- `it('replaces existing reaction when switching to a different emoji')`
- `it('rejects emoji not in allowed set')`

**SubmitQuestionSuggestionTest:**
- `it('creates a suggestion with suggested status')`
- `it('records activity')`

**ReviewQuestionSuggestionTest:**
- `it('approves a suggestion and auto-creates a draft question')`
- `it('rejects a suggestion without creating a question')`
- `it('sets reviewed_by and reviewed_at')`
- `it('records activity')`

**ProcessQuestionScheduleTest:**
- `it('activates a draft question with start_date that has passed')`
- `it('archives an active question whose end_date has passed')`
- `it('archives old active question when new one activates')`
- `it('does not change draft questions without a start_date')`
- `it('does not activate a draft question whose start_date is in the future')`

**CommunityStoriesAuthorizationTest:**
- `it('allows traveler to view community stories')`
- `it('denies drifter from viewing community stories')`
- `it('denies stowaway from viewing community stories')`
- `it('denies user in brig from viewing community stories')`
- `it('allows citizen to suggest a question')`
- `it('denies traveler from suggesting a question')`
- `it('allows chaplain jr crew to manage community stories')`
- `it('denies non-chaplain crew from managing community stories')`
- `it('allows command officer to manage community stories')`
- `it('denies non-command non-chaplain officer from managing community stories')`
- `it('allows admin to manage community stories')`
- `it('allows user to edit own unapproved response')`
- `it('denies user from editing approved response')`
- `it('denies user from editing another users response')`
- `it('allows admin to edit any unapproved response')`

### Coverage Gaps

- **EditCommunityResponse** action has no dedicated test file. Edit logic is only partially tested through the policy test (`update` ability) but the action's image replacement/removal logic is untested.
- **UpdateCommunityQuestion** action has no dedicated test file.
- **Volt component integration tests** are missing — no Livewire test assertions for the component methods, form validation, or UI interactions.
- **Image upload** in `SubmitCommunityResponse` is not tested (the test only covers text responses).
- **Dashboard widget** and **profile card** components have no tests.
- **Per-cycle limit reset** when a new active question starts is not explicitly tested (the archived limit test uses a fixed scenario but doesn't test the reset behavior across multiple cycles).

---

## 17. File Map

**Models:**
- `app/Models/CommunityQuestion.php`
- `app/Models/CommunityResponse.php`
- `app/Models/CommunityReaction.php`
- `app/Models/QuestionSuggestion.php`
- `app/Models/User.php` (modified — added `communityResponses()` and `questionSuggestions()` relationships)

**Enums:**
- `app/Enums/CommunityQuestionStatus.php`
- `app/Enums/CommunityResponseStatus.php`
- `app/Enums/QuestionSuggestionStatus.php`

**Actions:**
- `app/Actions/CreateCommunityQuestion.php`
- `app/Actions/UpdateCommunityQuestion.php`
- `app/Actions/SubmitCommunityResponse.php`
- `app/Actions/EditCommunityResponse.php`
- `app/Actions/ModerateResponses.php`
- `app/Actions/ToggleCommunityReaction.php`
- `app/Actions/SubmitQuestionSuggestion.php`
- `app/Actions/ReviewQuestionSuggestion.php`
- `app/Actions/ProcessQuestionSchedule.php`

**Policies:**
- `app/Policies/CommunityQuestionPolicy.php`
- `app/Policies/CommunityResponsePolicy.php`

**Gates:** `app/Providers/AuthServiceProvider.php` — gates: `view-community-stories`, `submit-community-response`, `suggest-community-question`, `manage-community-stories`

**Notifications:** None

**Jobs:** None

**Services:** None

**Controllers:** None

**Volt Components:**
- `resources/views/livewire/community-stories/index.blade.php`
- `resources/views/livewire/dashboard/community-question-widget.blade.php`
- `resources/views/livewire/users/community-stories-card.blade.php`

**Routes:**
- `community-stories.index` — `GET /community-stories`

**Migrations:**
- `database/migrations/2026_03_11_100000_create_question_suggestions_table.php`
- `database/migrations/2026_03_11_100001_create_community_questions_table.php`
- `database/migrations/2026_03_11_100002_create_community_responses_table.php`
- `database/migrations/2026_03_11_100003_create_community_reactions_table.php`

**Factories:**
- `database/factories/CommunityQuestionFactory.php`
- `database/factories/CommunityResponseFactory.php`
- `database/factories/QuestionSuggestionFactory.php`

**Console Commands:**
- `app/Console/Commands/ProcessCommunityQuestionSchedule.php`

**Scheduled Tasks:**
- `routes/console.php` — `community:process-schedule` (hourly)

**Tests:**
- `tests/Feature/Actions/CommunityStories/SubmitCommunityResponseTest.php`
- `tests/Feature/Actions/CommunityStories/ModerateResponsesTest.php`
- `tests/Feature/Actions/CommunityStories/CreateCommunityQuestionTest.php`
- `tests/Feature/Actions/CommunityStories/ToggleCommunityReactionTest.php`
- `tests/Feature/Actions/CommunityStories/SubmitQuestionSuggestionTest.php`
- `tests/Feature/Actions/CommunityStories/ReviewQuestionSuggestionTest.php`
- `tests/Feature/Actions/CommunityStories/ProcessQuestionScheduleTest.php`
- `tests/Feature/Policies/CommunityStoriesAuthorizationTest.php`

**Config:** None

**Other:**
- `resources/views/dashboard.blade.php` (modified — added widget)
- `resources/views/users/show.blade.php` (modified — added profile card)

---

## 18. Known Issues & Improvement Opportunities

1. **Missing test coverage for EditCommunityResponse and UpdateCommunityQuestion actions.** These two actions have no dedicated test files. Image replacement/removal logic in `EditCommunityResponse` and status transition validation in `UpdateCommunityQuestion` are untested.

2. **No Livewire component integration tests.** The Volt components contain significant logic (form validation, authorization checks, computed properties, pagination) that is only tested indirectly through action-level tests.

3. **Image upload untested in SubmitCommunityResponse.** The test suite only covers text-based responses. The image storage path and file handling logic are not verified.

4. **Potential N+1 query in emoji reactions display.** The Volt component loads reactions for each response in the approved response feed. If not eager-loaded, this could cause N+1 queries on pages with many responses.

5. **`featured_in_blog_url` column is unused.** The `community_responses` table has a `featured_in_blog_url` column that is in `$fillable` but is not set by any action, displayed in any component, or referenced in any test. This appears to be a placeholder for future functionality.

6. **No notification system for moderation outcomes.** When a response is approved or rejected, the submitting user is not notified. This could be a useful addition via `TicketNotificationService`.

7. **No notification for staff when new responses are submitted.** Staff must manually check the pending responses tab. A notification or badge count could improve the moderation workflow.

8. **`CommunityReaction` model lacks `HasFactory` trait.** Unlike the other three models in this feature, `CommunityReaction` does not use `HasFactory` and has no factory class, making it harder to test in isolation.

9. **Per-cycle archived limit edge case.** If there is no active question (all are archived or draft), the archived response check requires an active question to exist. A Resident trying to respond to an archived question when no active question exists would hit a null `$activeQuestion` — the code handles this by only enforcing the limit when an active question exists, but the intent (should they be allowed to respond at all?) is ambiguous.

10. **Hardcoded allowed emojis.** The emoji allowlist is a constant in `ToggleCommunityReaction`. If this needs to change, it requires a code deployment. Consider moving to a config value if flexibility is desired.

11. **`CommunityResponseStatus::Archived` enum case exists but is never set by any action.** No action or command transitions a response to the `Archived` status. It may be intended for future use when archiving responses along with their questions.
