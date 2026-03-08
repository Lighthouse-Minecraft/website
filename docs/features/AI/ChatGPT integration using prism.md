# AI/ChatGPT Integration (PrismPHP) -- Technical Documentation

> **Audience:** Project owner, developers, AI agents
> **Generated:** 2026-03-08
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

The AI/ChatGPT integration uses the **PrismPHP** package (`prism-php/prism`) to format raw staff meeting notes into clean, community-facing updates via a large language model. When a staff meeting ends, raw notes from all departments are compiled, then sent to an LLM (default: OpenAI's GPT-4o) with a detailed system prompt tailored to the Lighthouse community context. The AI reformats internal staff discussions into organized, public-friendly community updates.

The feature is used exclusively by **staff members** (Officers, Command department officers, Admins, and Meeting Secretaries) who manage meetings. It is tightly integrated into the meeting lifecycle — AI formatting triggers automatically when a meeting transitions from "In Progress" to "Finalizing", and staff can manually re-run formatting with a customized system prompt.

Community members see the final AI-formatted output on the public **Community Updates** page (`/community-updates`), rendered as markdown. The AI integration is designed to gracefully degrade — if no API key is configured, the system falls back to displaying raw notes without error.

---

## 2. Database Schema

The AI feature does not introduce its own tables. It operates on existing meeting-related tables.

### `meetings` table

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| `id` | bigint (PK) | No | auto | |
| `title` | string | No | — | Meeting title |
| `type` | string | No | — | Cast to `MeetingType` enum |
| `day` | string | No | — | Date string (Y-m-d) |
| `scheduled_time` | timestamp | No | — | UTC scheduled time |
| `start_time` | timestamp | Yes | null | When meeting started |
| `end_time` | timestamp | Yes | null | When meeting ended |
| `is_public` | boolean | No | false | |
| `status` | string | No | 'pending' | Cast to `MeetingStatus` enum |
| `summary` | string | Yes | null | |
| `agenda` | text | Yes | null | Locked copy of agenda |
| `minutes` | text | Yes | null | Raw compiled notes (AI input) |
| `community_minutes` | text | Yes | null | Final community-facing content (AI output stored here on completion) |
| `show_community_updates` | boolean | No | true | Whether to display on Community Updates page |
| `created_at` | timestamp | No | auto | |
| `updated_at` | timestamp | No | auto | |

**Migration(s):**
- `database/migrations/2025_08_08_034207_create_meetings_table.php`
- `database/migrations/2025_08_12_234133_update_meetings_add_status_field.php`
- `database/migrations/2025_08_15_000816_update_meetings_add_minutes_fields.php`
- `database/migrations/2026_03_05_150956_add_type_to_meetings_table.php`
- `database/migrations/2026_03_05_160632_add_show_community_updates_to_meetings_table.php`

### `meeting_notes` table

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| `id` | bigint (PK) | No | auto | |
| `created_by` | foreignId | No | — | FK to `users.id` |
| `meeting_id` | foreignId | No | — | FK to `meetings.id` |
| `section_key` | string | No | — | e.g. 'general', 'community', department values |
| `content` | text | Yes | null | Note content (AI-formatted for 'community' section) |
| `locked_by` | foreignId | Yes | null | FK to `users.id` |
| `locked_at` | timestamp | Yes | null | |
| `lock_updated_at` | timestamp | Yes | null | |
| `created_at` | timestamp | No | auto | |
| `updated_at` | timestamp | No | auto | |

**Foreign Keys:** `created_by` -> `users.id`, `meeting_id` -> `meetings.id`, `locked_by` -> `users.id`
**Migration(s):** `database/migrations/2025_08_11_021754_create_meeting_notes_table.php`

---

## 3. Models & Relationships

### Meeting (`app/Models/Meeting.php`)

**Relationships:**
| Method | Type | Related Model | Notes |
|--------|------|---------------|-------|
| `notes()` | hasMany | MeetingNote | All note sections including 'community' |
| `attendees()` | belongsToMany | User | Pivot: `meeting_user` with `added_at` |
| `questions()` | hasMany | MeetingQuestion | Ordered by `sort_order` |
| `reports()` | hasMany | MeetingReport | Staff department reports |

**Key Methods:**
- `startMeeting(): void` -- Transitions from Pending to InProgress, sets start_time
- `endMeeting(): void` -- Transitions from InProgress to Finalizing, sets end_time
- `completeMeeting(): void` -- Transitions from Finalizing to Completed
- `isStaffMeeting(): bool` -- Returns true if type is `MeetingType::StaffMeeting`

**Casts:**
- `type` => `MeetingType`
- `status` => `MeetingStatus`
- `scheduled_time`, `start_time`, `end_time` => `datetime`
- `is_public`, `show_community_updates` => `boolean`

### MeetingNote (`app/Models/MeetingNote.php`)

**Relationships:**
| Method | Type | Related Model | Notes |
|--------|------|---------------|-------|
| `createdBy()` | belongsTo | User | via `created_by` |
| `meeting()` | belongsTo | Meeting | |
| `lockedBy()` | belongsTo | User | via `locked_by` |

**Eager Loads:** `$with = ['lockedBy', 'createdBy']`

**AI Relevance:** The `section_key = 'community'` note stores the AI-formatted output during the Finalizing stage. When the meeting is completed, its content is copied to `meetings.community_minutes`.

---

## 4. Enums Reference

### MeetingStatus (`app/Enums/MeetingStatus.php`)

| Case | Value | Label | AI Relevance |
|------|-------|-------|--------------|
| `Pending` | `pending` | Pending | — |
| `InProgress` | `in_progress` | In Progress | Notes being taken |
| `Finalizing` | `finalizing` | Finalizing | AI formatting runs here |
| `Completed` | `completed` | Completed | Final AI output stored in `community_minutes` |
| `Cancelled` | `cancelled` | Cancelled | — |
| `Archived` | `archived` | Archived | — |

### MeetingType (`app/Enums/MeetingType.php`)

| Case | Value | Label |
|------|-------|-------|
| `StaffMeeting` | `staff_meeting` | Staff Meeting |
| `BoardMeeting` | `board_meeting` | Board Meeting |
| `CommunityMeeting` | `community_meeting` | Community Meeting |
| `Other` | `other` | Other |

---

## 5. Authorization & Permissions

### Gates (from `AuthServiceProvider`)

| Gate Name | Who Can Pass | Logic Summary |
|-----------|-------------|---------------|
| `view-all-community-updates` | Traveler+ or Admin | Users at least Traveler membership level, or Admins, can view all past community updates |

### Policies

#### MeetingPolicy (`app/Policies/MeetingPolicy.php`)

**`before()` hook:** Admins and Command department Officers always pass all checks.

| Ability | Who Can | Conditions |
|---------|---------|------------|
| `viewAny` | CrewMember+ or Meeting Secretary | |
| `view` | CrewMember+ or Meeting Secretary | |
| `attend` | CrewMember+ or Meeting Secretary | |
| `create` | Officer+ or Meeting Secretary | |
| `update` | Officer+ or Meeting Secretary | **This gates all AI formatting operations** |
| `delete` | Nobody | Always returns false |

### Permissions Matrix

| User Type | View Meeting | Trigger AI Formatting | Reformat with AI | View Community Updates |
|-----------|-------------|----------------------|-------------------|----------------------|
| Guest | No | No | No | Latest only |
| Regular Member (below Traveler) | No | No | No | Latest only |
| Traveler+ Member | No | No | No | All updates |
| Staff CrewMember | Yes (view) | No | No | All updates |
| Staff Officer | Yes | Yes | Yes | All updates |
| Meeting Secretary (role) | Yes | Yes | Yes | All updates |
| Command Dept Officer | Yes | Yes | Yes | All updates |
| Admin | Yes | Yes | Yes | All updates |

---

## 6. Routes

| Method | URL | Middleware | Handler | Route Name |
|--------|-----|-----------|---------|------------|
| GET | `/meetings` | auth | `MeetingController@index` | `meeting.index` |
| GET | `/meetings/{meeting}/manage` | auth | `MeetingController@edit` | `meeting.edit` |
| GET | `/community-updates` | (none) | `CommunityUpdatesController@index` | `community-updates.index` |

---

## 7. User Interface Components

### Manage Meeting Page
**File:** `resources/views/livewire/meetings/manage-meeting.blade.php`
**Route:** `/meetings/{meeting}/manage` (route name: `meeting.edit`)

**Purpose:** Full meeting management interface including AI formatting controls.

**Authorization:** `$this->authorize('update', $this->meeting)` on all AI-related methods.

**AI-Specific User Actions:**
- **End Meeting** -> `EndMeetingConfirmed()` compiles raw notes into `minutes`, creates a `community` section MeetingNote, transitions to Finalizing, then triggers `processAiFormatting()` via JavaScript setTimeout
- **Reformat with AI** button (sparkles icon) -> `showAiPromptEditor()` opens a modal with the system prompt textarea
- **Run AI Formatting** button in modal -> `reformatWithAi()` sends notes to AI with custom/default prompt, updates the community note

**AI-Specific UI Elements:**
- Loading spinner overlay with "Formatting notes with AI..." text (shown during `processAiFormatting`)
- "Reformat with AI" ghost button with sparkles icon (visible during Finalizing stage to authorized users)
- `ai-prompt-editor` modal with:
  - Textarea for system prompt (pre-filled with config default)
  - Warning callout: "Running AI formatting will overwrite the current community notes"
  - "Run AI Formatting" button with loading state

### Community Updates List
**File:** `resources/views/livewire/community-updates/list.blade.php`
**Route:** `/community-updates` (route name: `community-updates.index`)

**Purpose:** Public-facing page displaying AI-formatted community updates from completed meetings.

**Authorization:** Gate `view-all-community-updates` determines pagination (all updates) vs. limit 1 (latest only).

**Display:** Renders `community_minutes` as parsed markdown via `Str::markdown()` inside a `prose dark:prose-invert` div within a Flux accordion.

---

## 8. Actions (Business Logic)

### FormatMeetingNotesWithAi (`app/Actions/FormatMeetingNotesWithAi.php`)

**Signature:** `handle(string $notes, ?string $systemPrompt = null): array{success: bool, text: string, error: ?string}`

**Step-by-step logic:**
1. Reads provider and model from `config('lighthouse.ai.meeting_notes_provider')` and `config('lighthouse.ai.meeting_notes_model')`
2. Checks API key from `config("prism.providers.{$provider}.api_key")` — returns raw notes with error if empty
3. Checks if input notes are empty — returns raw notes with error if so
4. Calls `Prism::text()->using($provider, $model)->withSystemPrompt(...)->withPrompt($notes)->asText()`
5. If AI returns empty text, logs warning and returns raw notes with error
6. On success, logs info with token usage metrics (prompt + completion tokens) and returns formatted text
7. Catches `PrismException` and generic `\Throwable` — logs error, returns raw notes with error message

**Called by:**
- `manage-meeting.blade.php` -> `processAiFormatting()` (automatic, after ending meeting)
- `manage-meeting.blade.php` -> `reformatWithAi()` (manual, with optional custom prompt)

---

## 9. Notifications

Not applicable for this feature.

---

## 10. Background Jobs

Not applicable for this feature. AI formatting runs synchronously within the Livewire request cycle, triggered via a JavaScript setTimeout to avoid blocking the meeting-end transition.

---

## 11. Console Commands & Scheduled Tasks

Not applicable for this feature.

---

## 12. Services

Not applicable for this feature. The integration uses the PrismPHP facade directly within the Action class.

---

## 13. Activity Log Entries

The AI formatting itself does not create activity log entries. Related meeting lifecycle entries:

| Action String | Logged By | Subject Model | Description |
|---------------|-----------|---------------|-------------|
| `update_meeting` | manage-meeting component | Meeting | "Updated meeting metadata." |
| `toggle_community_updates` | manage-meeting component | Meeting | "Toggled community updates visibility." |

---

## 14. Data Flow Diagrams

### Automatic AI Formatting (Meeting End)

```
Staff clicks "End Meeting" button on manage-meeting page
  -> Flux modal "end-meeting-confirmation" shown
  -> Staff clicks "End Meeting" (confirm)
    -> EndMeetingConfirmed()
      -> $this->authorize('update', $this->meeting)
      -> Compiles general + department notes into $meeting->minutes
      -> MeetingNote::updateOrCreate(section_key: 'community', content: raw minutes)
      -> $meeting->endMeeting() -> status = Finalizing
      -> $meeting->save()
      -> Flux modal closed
      -> JavaScript: setTimeout(() => $wire.processAiFormatting(), 100)
        -> processAiFormatting()
          -> $this->authorize('update', $this->meeting)
          -> Loads community MeetingNote
          -> FormatMeetingNotesWithAi::run($meeting->minutes)
            -> Prism::text()->using(provider, model)->withSystemPrompt(default)->withPrompt(notes)
            -> Returns {success: true, text: formatted, error: null}
          -> Updates community note content with AI output
          -> Clears locks (locked_by, locked_at, lock_updated_at)
          -> Dispatches $refresh
```

### Manual AI Reformat (Custom Prompt)

```
Staff clicks "Reformat with AI" (sparkles button) during Finalizing
  -> showAiPromptEditor()
    -> $this->authorize('update', $this->meeting)
    -> Pre-fills aiPrompt with config default system prompt
    -> Flux::modal('ai-prompt-editor')->show()
  -> Staff edits prompt in textarea, clicks "Run AI Formatting"
    -> reformatWithAi()
      -> $this->authorize('update', $this->meeting)
      -> Loads community MeetingNote (error if missing)
      -> Uses $meeting->minutes as source (not already-formatted note)
      -> FormatMeetingNotesWithAi::run($sourceNotes, $this->aiPrompt)
      -> Updates community note content with AI output
      -> Clears locks
      -> Flux::toast('Community notes reformatted with AI.', variant: 'success')
      -> Modal closed, $refresh dispatched
```

### Community Updates Display

```
User visits /community-updates
  -> CommunityUpdatesController@index -> community-updates.index view
    -> <livewire:community-updates.list />
      -> Queries meetings: status=Completed, show_community_updates=true, ordered by day desc
      -> Gate check 'view-all-community-updates':
        -> Traveler+ or Admin: paginated (10 per page)
        -> Others: limit 1 (latest only)
      -> Renders community_minutes via Str::markdown() in accordion
```

### Meeting Completion (AI Output Persisted)

```
Staff clicks "Complete Meeting" during Finalizing
  -> CompleteMeetingConfirmed()
    -> $this->authorize('update', $this->meeting)
    -> Copies community MeetingNote content -> $meeting->community_minutes
    -> $meeting->completeMeeting() -> status = Completed
    -> $meeting->save()
    -> community_minutes now persisted on meeting record for public display
```

---

## 15. Configuration

### Environment Variables

| Key | Default | Purpose |
|-----|---------|---------|
| `OPENAI_API_KEY` | `''` (empty) | API key for OpenAI provider |
| `AI_MEETING_NOTES_PROVIDER` | `openai` | Which LLM provider to use (openai, anthropic, ollama, etc.) |
| `AI_MEETING_NOTES_MODEL` | `gpt-4o` | Which model to use for formatting |
| `AI_MEETING_NOTES_PROMPT` | (long default) | System prompt for the AI — see below |
| `PRISM_REQUEST_TIMEOUT` | `30` | Request timeout in seconds |

### Config Files

**`config/lighthouse.php`** — `ai` section (lines 38-104):
- `ai.meeting_notes_system_prompt` — Comprehensive ~100-line system prompt covering:
  - Lighthouse community context (Christian Minecraft community)
  - Department descriptions (Command, Chaplaincy, Engineers, Quartermasters, Stewards)
  - Community rank hierarchy (Traveler, Resident, Citizen, Crew, Officer)
  - Content filtering rules (remove internal staff discussions, moderation details, operational procedures)
  - Markdown formatting guidelines with emoji section headings
  - Preferred output structure (General, Website, Server, Prayer, Community Life, Events, Finances)
- `ai.meeting_notes_provider` — Provider string matching a key in `config/prism.php` providers
- `ai.meeting_notes_model` — Model identifier string

**`config/prism.php`** — PrismPHP package config:
- `prism_server.enabled` — Disabled by default
- `request_timeout` — 30 seconds default
- `providers` — Configuration for: OpenAI, Anthropic, Ollama, Mistral, Groq, xAI, Gemini, DeepSeek, ElevenLabs, VoyageAI, OpenRouter

---

## 16. Test Coverage

### Test Files

| File | Tests | What It Covers |
|------|-------|----------------|
| `tests/Feature/Actions/FormatMeetingNotesWithAiTest.php` | 6 tests | Core AI action logic |
| `tests/Feature/Meeting/EndMeetingAiFormattingTest.php` | 10 tests | Integration with meeting lifecycle |

### Test Case Inventory

**FormatMeetingNotesWithAiTest.php:**
- `it('returns raw notes when no API key is configured')`
- `it('returns formatted text on successful AI call')`
- `it('falls back to raw notes on empty AI response')`
- `it('falls back to raw notes on empty input')`
- `it('uses custom system prompt when provided')`
- `it('reads provider and model from config')`

**EndMeetingAiFormattingTest.php:**
- `it('creates community note with raw compiled notes')`
- `it('transitions meeting to finalizing without blocking on AI')`
- `it('updates community note with AI-formatted content')`
- `it('keeps raw notes when AI is not configured')`
- `it('requires update authorization')`
- `it('shows the reformat with AI button during finalizing')`
- `it('does not show the reformat button when meeting is completed')`
- `it('updates community note content when reformatting')`
- `it('shows error when no community note exists')`
- `it('shows error when no meeting minutes are available')`
- `it('uses custom prompt from the AI prompt editor')`
- `it('requires update authorization for reformatting')`

### Coverage Gaps

- No test for `PrismException` or generic `\Throwable` catch branches in `FormatMeetingNotesWithAi`
- No test for the `showAiPromptEditor()` method (verifying the modal opens and prompt is pre-filled)
- No test verifying the JavaScript `setTimeout` trigger in `EndMeetingConfirmed`
- No test for the loading spinner UI during AI processing

---

## 17. File Map

**Models:**
- `app/Models/Meeting.php`
- `app/Models/MeetingNote.php`

**Enums:**
- `app/Enums/MeetingStatus.php`
- `app/Enums/MeetingType.php`

**Actions:**
- `app/Actions/FormatMeetingNotesWithAi.php`

**Policies:**
- `app/Policies/MeetingPolicy.php`

**Gates:** `app/Providers/AuthServiceProvider.php` -- gates: `view-all-community-updates`

**Notifications:** (none)

**Jobs:** (none)

**Services:** (none)

**Controllers:**
- `app/Http/Controllers/MeetingController.php`
- `app/Http/Controllers/CommunityUpdatesController.php`

**Volt Components:**
- `resources/views/livewire/meetings/manage-meeting.blade.php`
- `resources/views/livewire/community-updates/list.blade.php`

**Views:**
- `resources/views/community-updates/index.blade.php`

**Routes:**
- `meeting.index` — `GET /meetings`
- `meeting.edit` — `GET /meetings/{meeting}/manage`
- `community-updates.index` — `GET /community-updates`

**Migrations:**
- `database/migrations/2025_08_08_034207_create_meetings_table.php`
- `database/migrations/2025_08_11_021754_create_meeting_notes_table.php`
- `database/migrations/2025_08_12_234133_update_meetings_add_status_field.php`
- `database/migrations/2025_08_15_000816_update_meetings_add_minutes_fields.php`
- `database/migrations/2026_03_05_150956_add_type_to_meetings_table.php`
- `database/migrations/2026_03_05_160632_add_show_community_updates_to_meetings_table.php`

**Config:**
- `config/lighthouse.php` — `ai.*` keys
- `config/prism.php` — PrismPHP provider configuration

**Tests:**
- `tests/Feature/Actions/FormatMeetingNotesWithAiTest.php`
- `tests/Feature/Meeting/EndMeetingAiFormattingTest.php`

**Composer:**
- `prism-php/prism: ^0.99.21`

---

## 18. Known Issues & Improvement Opportunities

1. **Synchronous AI call in Livewire request:** The `processAiFormatting()` and `reformatWithAi()` methods call the LLM synchronously within the HTTP request. While the initial call is deferred via `setTimeout`, it still ties up the Livewire connection. A queued job would prevent potential timeouts for long AI responses, especially with larger meeting notes.

2. **No retry mechanism:** If the AI call fails (network error, rate limit, etc.), there is no automatic retry. The staff member must manually click "Reformat with AI" to try again.

3. **Custom prompt not persisted:** When a staff member edits the system prompt via the AI prompt editor modal, the custom prompt is stored only as a Livewire property (`$aiPrompt`). If the page is refreshed, the customization is lost. Consider storing successful custom prompts for reference.

4. **PrismException catch branch untested:** The `catch (PrismException $e)` and `catch (\Throwable $e)` branches in `FormatMeetingNotesWithAi` have no test coverage.

5. **Hardcoded 100ms setTimeout delay:** The `setTimeout(() => { $wire.processAiFormatting() }, 100)` in `EndMeetingConfirmed()` uses a hardcoded 100ms delay. This is a reasonable approach but could theoretically race with slow DOM updates.

6. **Raw minutes used as AI source on reformat:** The `reformatWithAi()` method correctly uses `$meeting->minutes` (raw compiled notes) rather than the already-formatted community note. This is good design — it prevents "telephone game" degradation from re-formatting formatted text.

7. **No token/cost tracking beyond logs:** Token usage is logged via `Log::info()` but not persisted to the database. For cost tracking, consider storing prompt and completion token counts on the meeting or a dedicated AI usage table.

8. **Community updates page renders markdown but meeting manage page does not:** The community updates list uses `Str::markdown()` for rendering, but the meeting manage page still uses `nl2br()` for displaying `minutes` and `community_minutes` during Finalizing and Completed states (lines 521, 553, 560). This means AI-generated markdown formatting won't render properly when viewed by staff on the meeting manage page.
