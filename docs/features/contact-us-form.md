# Contact Us Form — Technical Reference

**Date:** 2026-04-02  
**Feature branch:** `fix-rules-formatting` (migrations deployed 2026-04-01)

---

## 1. Overview

The Contact Us Form allows unauthenticated visitors (guests) to submit inquiries to the
Lighthouse community staff without creating an account. Each submission creates a
`ThreadType::ContactInquiry` thread with guest-specific columns (`guest_name`,
`guest_email`, `conversation_token`). Staff with the **Contact - Receive Submissions**
role manage these threads through a dedicated staff inbox. Guests receive a confirmation
email with a unique, token-based link that lets them view the conversation and reply in
the browser.

Key design decisions:

- No account required to submit or follow up.
- The `conversation_token` (UUID) functions as a shareable secret that grants read/write
  access to the thread for the guest.
- Internal notes written by staff are never shown on the guest-facing thread view.
- If a guest later registers an account with the same email address, all prior contact
  threads are linked to the new user account automatically.
- Spam is mitigated by a honeypot field, IP-based rate limiting (5 requests per hour),
  and optional hCaptcha verification.

---

## 2. Database Schema

### 2.1 `threads` table

Original columns (from `2026_02_12_214853_create_threads_table.php`):

| Column | Type | Nullable | Notes |
|---|---|---|---|
| `id` | bigint unsigned | no | Primary key |
| `type` | string | no | `ThreadType` enum value; indexed |
| `subtype` | string | no | `ThreadSubtype` enum value; indexed |
| `department` | string | yes | `StaffDepartment` enum value; indexed |
| `subject` | string | no | Prefixed as `[Category] Subject` for contact inquiries |
| `status` | string | no | `ThreadStatus` enum value; indexed |
| `created_by_user_id` | bigint unsigned (FK) | **yes** (since migration 4) | NULL for guest-submitted threads |
| `assigned_to_user_id` | bigint unsigned (FK) | yes | FK → `users` |
| `is_flagged` | boolean | no | Default false; indexed |
| `has_open_flags` | boolean | no | Default false; indexed |
| `last_message_at` | timestamp | yes | Indexed |
| `topicable_type` | string | yes | Morph type |
| `topicable_id` | bigint unsigned | yes | Morph id |
| `is_locked` | boolean | no | Default false |
| `closed_at` | timestamp | yes | |
| `locked_at` | timestamp | yes | |
| `escalated_at` | timestamp | yes | |
| `created_at` / `updated_at` | timestamps | no | |

Columns added by contact-us-form migrations (`2026_04_01_000001`):

| Column | Type | Nullable | Unique | Notes |
|---|---|---|---|---|
| `guest_name` | string | yes | no | Submitter's name; NULL when name was left blank |
| `guest_email` | string | yes | no | Submitter's email address |
| `conversation_token` | string | yes | **unique** | UUID; URL token for guest thread access |

Migration `2026_04_01_000004` relaxed the `created_by_user_id` FK to be nullable so that
guest-submitted threads do not require a user row.

### 2.2 `messages` table

Original columns (from `2026_02_12_214857_create_messages_table.php`):

| Column | Type | Nullable | Notes |
|---|---|---|---|
| `id` | bigint unsigned | no | Primary key |
| `thread_id` | bigint unsigned (FK) | no | FK → `threads`; cascade delete |
| `user_id` | bigint unsigned (FK) | **yes** (since migration 5) | NULL for guest messages |
| `body` | text | no | Markdown-safe content |
| `kind` | string | no | `MessageKind` enum value; default `message` |
| `image_path` | string | yes | |
| `image_was_purged` | boolean | yes | |
| `is_pending_moderation` | boolean | yes | |
| `deleted_by` | bigint unsigned | yes | FK → `users` |
| `deleted_at` | timestamp | yes | Soft delete |
| `created_at` / `updated_at` | timestamps | no | |

Column added by `2026_04_01_000002`:

| Column | Type | Nullable | Notes |
|---|---|---|---|
| `guest_email_sent` | boolean | yes | `true` once `SendGuestConversationEmail` has dispatched for this message |

Migration `2026_04_01_000005` relaxed `user_id` to nullable so that guest-authored
messages (no `user_id`) can be stored.

A guest-authored message is identified by `user_id IS NULL` on the message row. A staff
reply is identified by `user_id IS NOT NULL`.

### 2.3 `thread_participants` table

Unchanged by this feature. Staff are added as participants when `CreateContactInquiry`
runs. If the guest registers later, `LinkContactThreadsOnRegistration` adds their new
user record as a participant.

| Column | Type | Nullable | Notes |
|---|---|---|---|
| `id` | bigint unsigned | no | Primary key |
| `thread_id` | bigint unsigned (FK) | no | FK → `threads` |
| `user_id` | bigint unsigned (FK) | no | FK → `users` |
| `last_read_at` | timestamp | yes | NULL = never read |
| `is_viewer` | boolean | no | `true` = viewer-only (read-only participant) |
| `created_at` / `updated_at` | timestamps | no | |

### 2.4 `roles` table (seed)

Migration `2026_04_01_000003` inserts a single role if it does not already exist:

| `name` | `description` | `color` | `icon` |
|---|---|---|---|
| `Contact - Receive Submissions` | Receive notifications for new public contact inquiries and manage contact threads | `cyan` | `envelope` |

---

## 3. Models & Relationships

### `App\Models\Thread`

File: `app/Models/Thread.php`

New fillable columns added for this feature: `guest_name`, `guest_email`,
`conversation_token`.

New casts: none (all three new columns are plain strings).

Relevant existing relationships used by contact inquiries:

| Method | Return type | Notes |
|---|---|---|
| `messages()` | `HasMany<Message>` | All messages in the thread |
| `participants()` | `HasMany<ThreadParticipant>` | Staff participants (user records) |
| `createdBy()` | `BelongsTo<User>` | NULL for guest threads |
| `addParticipant(User, bool)` | void | Idempotent; used to add staff and linked users |
| `isUnreadFor(User)` | bool | Used by the inquiry list to show "New" badge |

### `App\Models\Message`

File: `app/Models/Message.php`

New fillable column: `guest_email_sent`.

New cast: `'guest_email_sent' => 'boolean'`.

A guest message has `user_id = NULL`. A staff message has `user_id` set. An internal
note has `kind = MessageKind::InternalNote` and is filtered out of the guest view.

### `App\Models\ThreadParticipant`

File: `app/Models/ThreadParticipant.php`

No changes for this feature. Used to track which staff users are watching a contact
inquiry thread and when they last read it.

---

## 4. Enums Reference

### `App\Enums\ThreadType`

File: `app/Enums/ThreadType.php`

| Case | Value | Label |
|---|---|---|
| `ContactInquiry` | `contact_inquiry` | Contact Inquiry |
| `Ticket` | `ticket` | Ticket |
| `DirectMessage` | `dm` | Direct Message |
| `Forum` | `forum` | Forum |
| `Topic` | `topic` | Topic |
| `BlogComment` | `blog_comment` | Blog Comment |

All contact inquiry threads use `ThreadType::ContactInquiry`.

### `App\Enums\ThreadStatus`

File: `app/Enums/ThreadStatus.php`

| Case | Value | Label | Notes |
|---|---|---|---|
| `Open` | `open` | Open | Default on creation; guest and staff can reply |
| `Pending` | `pending` | Pending | Guest can still reply; staff can reply |
| `Resolved` | `resolved` | Resolved | Guest reply form hidden (`canReply` = false) |
| `Closed` | `closed` | Closed | Sets `closed_at`; reply forms hidden for both staff and guest |

The `view-inquiry` component's `canReply` computed property returns `true` only when
status is not `Closed` and not `Resolved`. The `guest-thread` component's `canReply`
returns `true` only when status is `Open` or `Pending`.

### `App\Enums\MessageKind`

File: `app/Enums/MessageKind.php`

| Case | Value | Label | Guest-visible? |
|---|---|---|---|
| `Message` | `message` | Message | Yes |
| `System` | `system` | System | Yes |
| `InternalNote` | `internal_note` | Internal Note | **No** — filtered out of guest view |

---

## 5. Authorization & Permissions

### Gate: `view-contact-inquiries`

Defined in `app/Providers/AuthServiceProvider.php` at line 237:

```php
Gate::define('view-contact-inquiries', function ($user) {
    return $user->hasRole('Contact - Receive Submissions');
});
```

`hasRole` checks are supplemented by the standard admin/super-admin bypass in
`AuthServiceProvider` (admins with `admin_granted_at` and users with
`has_all_roles_at` pass all gates automatically).

### Permissions Matrix

| Actor | Action | Mechanism |
|---|---|---|
| Guest (unauthenticated) | Submit contact form | Public route, no auth required |
| Guest | View own conversation thread | Token URL (`/contact/thread/{token}`); no auth required |
| Guest | Reply to own thread (open/pending only) | Token URL; no auth required |
| Guest | See internal staff notes | **Denied** — `guest-thread` component filters `InternalNote` kind |
| Authenticated user without role | Access `/contact-inquiries/*` | 403 Forbidden |
| User with `Contact - Receive Submissions` role | View inquiry list | `view-contact-inquiries` gate |
| User with `Contact - Receive Submissions` role | View individual inquiry | `view-contact-inquiries` gate |
| User with `Contact - Receive Submissions` role | Reply to inquiry | `view-contact-inquiries` gate (re-checked in `sendReply`) |
| User with `Contact - Receive Submissions` role | Add internal note | `view-contact-inquiries` gate |
| User with `Contact - Receive Submissions` role | Change thread status | `view-contact-inquiries` gate (re-checked in `changeStatus`) |
| Admin | All staff actions above | Admin bypass in `AuthServiceProvider` |

### Sidebar Navigation

The sidebar (`resources/views/components/layouts/app/sidebar.blade.php`) renders a
"Contact Inquiries" nav item inside the Management group, wrapped in
`@can('view-contact-inquiries')`. It links to `route('contact-inquiries.index')` and is
marked as current when `request()->routeIs('contact-inquiries.*')`.

---

## 6. Routes

Defined in `routes/web.php`:

| Method | URI | Name | Component | Auth required? |
|---|---|---|---|---|
| GET | `/contact` | `contact.index` | `contact.contact-form` | No |
| GET | `/contact/thread/{token}` | `contact.thread` | `contact.guest-thread` | No |
| GET | `/contact-inquiries` | `contact-inquiries.index` | `contact.inquiry-list` | Yes + `view-contact-inquiries` |
| GET | `/contact-inquiries/{thread}` | `contact-inquiries.show` | `contact.view-inquiry` | Yes + `view-contact-inquiries` |

The staff routes are grouped under `Route::prefix('contact-inquiries')->name('contact-inquiries.')->middleware(['auth', 'can:view-contact-inquiries'])`.

The guest routes are individually registered with `Volt::route(...)` without middleware.

---

## 7. UI Components

### 7.1 `contact.contact-form`

File: `resources/views/livewire/contact/contact-form.blade.php`  
Route: `GET /contact`  
Layout: `components.layouts.app.sidebar`

**Public component — no authentication required.**

Public properties:

| Property | Type | Default | Notes |
|---|---|---|---|
| `$name` | string | `''` | Optional |
| `$email` | string | `''` | Required |
| `$category` | string | `''` | Required; must be from `categories()` list |
| `$subject` | string | `''` | Required; max 255 |
| `$message` | string | `''` | Required; min 10 characters |
| `$honeypot` | string | `''` | Hidden input; non-empty = bot discard |
| `$hcaptchaToken` | string | `''` | Set via JS callback; required only when keys configured |
| `$submitted` | bool | `false` | When true, form is replaced by success card |

Available categories (from `categories()` method):

- General Inquiry
- Membership / Joining
- Parent / Guardian Question
- Report a Concern
- Donation / Support
- Technical Issue

Validation rules:

| Field | Rules |
|---|---|
| `name` | `nullable, string, max:255` |
| `email` | `required, string, email, max:255` |
| `category` | `required, string, in:<category list>` |
| `subject` | `required, string, max:255` |
| `message` | `required, string, min:10` |

Spam protection (applied in order before validation):

1. **Honeypot:** If `$honeypot !== ''`, sets `$submitted = true` and returns immediately
   without creating a thread. The bot sees a success state but nothing is persisted.
2. **IP rate limit:** Key `contact-form:{ip}`, max 5 attempts, 1-hour window
   (`RateLimiter::hit($key, 3600)`). Adds an error on `email` field and returns.
3. **hCaptcha:** Only runs when both `HCAPTCHA_SITE_KEY` and `HCAPTCHA_SECRET_KEY` are
   set. Verifies token against `https://hcaptcha.com/siteverify`. Adds error on
   `hcaptchaToken` field on failure.

On success, delegates to `CreateContactInquiry::run(...)` and sets `$submitted = true`.

### 7.2 `contact.inquiry-list`

File: `resources/views/livewire/contact/inquiry-list.blade.php`  
Route: `GET /contact-inquiries`  
Layout: inherits from the authenticated app shell.

**Staff-only component.** `mount()` calls `$this->authorize('view-contact-inquiries')`.

URL-bound filter property `$filter` (default `'open'`). Supports values:
- `'open'` — shows all threads where `status != 'closed'`
- `'closed'` — shows only closed threads

Computed property `inquiries()` queries `ThreadType::ContactInquiry` threads ordered by
`last_message_at DESC`, with the current user's participant record eager-loaded.

Helper methods:

| Method | Signature | Purpose |
|---|---|---|
| `isUnread(Thread)` | `bool` | Checks participant `last_read_at` vs `last_message_at` |
| `category(Thread)` | `string` | Extracts `[Category]` prefix from subject |
| `subject(Thread)` | `string` | Strips `[Category] ` prefix from subject |

Displays a "New" blue badge on unread threads. Shows guest name, guest email, category,
subject, relative time, and status badge (green=Open, amber=Pending, zinc=other).

### 7.3 `contact.view-inquiry`

File: `resources/views/livewire/contact/view-inquiry.blade.php`  
Route: `GET /contact-inquiries/{thread}`  
Layout: inherits from the authenticated app shell.

**Staff-only component.** `mount()` calls `$this->authorize('view-contact-inquiries')`,
aborts 404 if `$thread->type !== ThreadType::ContactInquiry`, and marks the current
user's participant record as read (`last_read_at = now()`).

Public properties:

| Property | Type | Notes |
|---|---|---|
| `$thread` | Thread | Route-model-bound |
| `$replyBody` | string | Textarea content |
| `$isInternalNote` | bool | Toggles between reply and internal note |
| `$emailGuest` | bool | Default true; controls whether guest is emailed on reply |
| `$newStatus` | string | Bound to status select; initialized from `$thread->status->value` |

Computed properties: `threadMessages()`, `category()`, `subjectText()`, `canReply()`.

`canReply()` returns `true` when status is not `Closed` and not `Resolved`.

**`sendReply()` method:**
1. Re-authorizes with `view-contact-inquiries`.
2. Validates `replyBody` (required, string, min:1).
3. Determines `MessageKind` (`InternalNote` or `Message`).
4. Creates a `Message` record with `guest_email_sent = false`, `user_id = auth()->id()`.
5. If not an internal note and `$emailGuest` is true, calls `SendGuestConversationEmail::run($thread, $message)`.
6. Updates `thread.last_message_at`.
7. Updates sender's `last_read_at`.
8. Notifies other non-viewer participants via `TicketNotificationService::send(..., 'staff_alerts')` using `NewContactInquiryNotification` (only for regular replies, not internal notes).
9. Calls `RecordActivity::run($thread, 'internal_note_added'|'reply_sent', ...)`.
10. Resets form state. Clears `threadMessages` computed cache. Shows success toast.

**`changeStatus()` method:**
1. Re-authorizes.
2. Resolves `ThreadStatus` from `$newStatus` value.
3. Updates `thread.status`. Sets `closed_at = now()` when closing; clears it when re-opening from closed.
4. Calls `RecordActivity::run($thread, 'status_changed', ...)`.
5. Shows success toast.

Message display in the thread view uses three visual styles:
- **Internal note** — amber border/background with "Internal Note" badge
- **Staff reply** — zinc border/background; shows "Emailed to guest" blue badge when `guest_email_sent = true`
- **Guest message** — blue border/background with "Guest" badge

### 7.4 `contact.guest-thread`

File: `resources/views/livewire/contact/guest-thread.blade.php`  
Route: `GET /contact/thread/{token}`  
Layout: `components.layouts.app.sidebar`

**Public component — no authentication required.**

`mount(string $token)` looks up the thread by `conversation_token` and
`type = ThreadType::ContactInquiry`. Aborts 404 if not found.

`threadMessages()` eager-loads messages filtered to `kind != MessageKind::InternalNote`,
ordered by `created_at ASC`. Internal notes are never returned to the guest.

`canReply()` returns `true` only when status is `Open` or `Pending`.

**`submitReply()` method:**
1. Validates `replyBody`.
2. Creates a `Message` with `user_id = NULL`, `kind = MessageKind::Message`,
   `guest_email_sent = false`.
3. Updates `thread.last_message_at`.
4. Notifies all non-viewer staff participants via `TicketNotificationService::send(..., 'staff_alerts')`.
5. Resets form. Shows success toast.

When the thread is closed or resolved, a banner is shown: "This conversation has been
closed. If you need further assistance, please submit a new inquiry." (links to `/contact`).

---

## 8. Actions

### 8.1 `CreateContactInquiry`

File: `app/Actions/CreateContactInquiry.php`

Signature:

```php
public function handle(
    string $name,
    string $email,
    string $category,
    string $subject,
    string $body
): Thread
```

Steps:

1. Generates a UUID `$token` via `Str::uuid()`.
2. Creates a `Thread` with `type = ContactInquiry`, `status = Open`,
   `subject = "[{$category}] {$subject}"`, `guest_name = $name ?: null`,
   `guest_email = $email`, `conversation_token = $token`, `last_message_at = now()`.
   Note: `created_by_user_id` is not set (NULL).
3. Creates a `Message` with `kind = MessageKind::Message`, `guest_email_sent = false`,
   and no `user_id`.
4. Sends `ContactSubmissionConfirmationNotification` to `$email` via
   `AnonymousNotifiable` (queued, mail only).
5. Queries staff recipients: users who have `admin_granted_at` set, or have
   `has_all_roles_at` set on their staff position, or have a staff position role named
   `Contact - Receive Submissions`.
6. For each staff recipient: calls `$thread->addParticipant($staffUser)` and sends
   `NewContactInquiryNotification` via `TicketNotificationService::send(..., 'staff_alerts')`.
7. Calls `RecordActivity::run($thread, 'contact_inquiry_received', "Contact inquiry received from {$email}.")`.
8. Returns the `Thread`.

### 8.2 `SendGuestConversationEmail`

File: `app/Actions/SendGuestConversationEmail.php`

Signature:

```php
public function handle(Thread $thread, Message $message): void
```

Steps:

1. Sends `ContactGuestReplyNotification` to `$thread->guest_email` via
   `AnonymousNotifiable` (queued, mail only).
2. Updates `$message->guest_email_sent = true`.

Called from `view-inquiry`'s `sendReply()` when the staff reply is not an internal note
and the "Email guest" toggle is enabled.

### 8.3 `LinkContactThreadsOnRegistration`

File: `app/Actions/LinkContactThreadsOnRegistration.php`

Signature:

```php
public function handle(User $newUser): void
```

Steps:

1. Queries all `ThreadType::ContactInquiry` threads where
   `LOWER(guest_email) = LOWER($newUser->email)` (case-insensitive match).
2. For each matched thread, calls `$thread->addParticipant($newUser)`.
   `addParticipant` is idempotent — it uses `firstOrCreate` so re-running is safe.

Called from the registration Livewire component
(`resources/views/livewire/auth/register.blade.php`) immediately after the new `User`
record is created, alongside `AutoLinkParentOnRegistration` and `LinkParentByEmail`.

---

## 9. Notifications

All three notifications implement `ShouldQueue` and use `Queueable`.

### 9.1 `ContactSubmissionConfirmationNotification`

File: `app/Notifications/ContactSubmissionConfirmationNotification.php`

| Property | Type | Notes |
|---|---|---|
| `$guestName` | string | Fallback to `'Guest'` when name was blank |
| `$subject` | string | The raw subject (without category prefix) |
| `$conversationToken` | string | UUID token for guest thread URL |

Channels: `['mail']`  
Mail template: `mail.contact-submission-confirmation`  
Subject line: `We received your message: {$subject}`

### 9.2 `NewContactInquiryNotification`

File: `app/Notifications/NewContactInquiryNotification.php`

| Property | Type | Notes |
|---|---|---|
| `$thread` | Thread | The contact inquiry thread |

Default channels: `['mail']`. Can be expanded via `setChannels(array, ?string)`.
Supports `mail`, `pushover` (via `PushoverChannel`), and `discord` (via `DiscordChannel`).

`TicketNotificationService::send()` calls `setChannels()` internally based on user
notification preferences and the `'staff_alerts'` preference category.

Mail template: `mail.new-contact-inquiry`  
Subject line: `New Contact Inquiry: {$thread->subject}`

Discord message format:
```
**New Contact Inquiry:** {subject}
**From:** {guest_name ?? guest_email ?? 'Unknown'}
```

Pushover payload: `['title' => 'New Contact Inquiry', 'message' => $thread->subject]`

Also sent (reusing this class) to other staff participants when the guest replies via the
`guest-thread` component.

### 9.3 `ContactGuestReplyNotification`

File: `app/Notifications/ContactGuestReplyNotification.php`

| Property | Type | Notes |
|---|---|---|
| `$thread` | Thread | The contact inquiry thread |
| `$message` | Message | The staff reply message |

Channels: `['mail']`  
Mail template: `mail.contact-guest-reply`  
Subject line: `Re: {$thread->subject}`  
Includes `conversationUrl = url('/contact/thread/'.$thread->conversation_token)` in the
template data so the guest can click through to the thread.

---

## 10. Background Jobs

None. All notifications are queued Laravel notifications (implementing `ShouldQueue`)
dispatched inline. There are no dedicated Job classes for this feature.

---

## 11. Console Commands

None.

---

## 12. Services

### `TicketNotificationService`

File: `app/Services/TicketNotificationService.php`

Used by `CreateContactInquiry` and the `view-inquiry` and `guest-thread` components to
dispatch staff notifications. The relevant call signature is:

```php
$notificationService->send($user, $notification, 'staff_alerts');
```

The `'staff_alerts'` category controls which delivery channels are active based on each
user's notification preferences. If the notification class supports `setChannels()` (as
`NewContactInquiryNotification` does), the service configures it before dispatching.

---

## 13. Activity Log Entries

All logged via `RecordActivity::run($model, $action, $description)`.

| `action` | Logged by | `description` |
|---|---|---|
| `contact_inquiry_received` | `CreateContactInquiry` | `"Contact inquiry received from {email}."` |
| `reply_sent` | `view-inquiry::sendReply()` | `"Staff reply sent."` |
| `internal_note_added` | `view-inquiry::sendReply()` | `"Internal note added."` |
| `status_changed` | `view-inquiry::changeStatus()` | `"Status changed: {OldLabel} → {NewLabel}"` |

The `subject_type` is `App\Models\Thread` and `subject_id` is the thread's `id`.

---

## 14. Data Flow Diagrams

### 14.1 Guest submits the contact form

```
Guest browser  →  POST (Livewire wire:submit)
  contact-form component
    ├── Honeypot check — non-empty = silent discard
    ├── IP rate limit check (5/hr per IP)
    ├── Laravel validation
    ├── hCaptcha verification (if keys configured)
    └── CreateContactInquiry::run(name, email, category, subject, body)
          ├── Thread::create(type=ContactInquiry, guest_name, guest_email, conversation_token=UUID, ...)
          ├── Message::create(thread_id, body, kind=Message, guest_email_sent=false, user_id=NULL)
          ├── AnonymousNotifiable → ContactSubmissionConfirmationNotification (queued, mail)
          │     └── Email: "We received your message: {subject}"
          │           + link to /contact/thread/{token}
          ├── foreach staffRecipient:
          │     ├── thread->addParticipant(staffUser)
          │     └── TicketNotificationService::send(staffUser, NewContactInquiryNotification, 'staff_alerts')
          └── RecordActivity(thread, 'contact_inquiry_received', ...)

  component sets $submitted = true  →  success card displayed
```

### 14.2 Staff views and replies

```
Staff browser  →  GET /contact-inquiries (inquiry-list)
  ├── authorize('view-contact-inquiries')
  └── Displays list of ContactInquiry threads (non-closed by default)
        unread badge if last_read_at < last_message_at

Staff clicks thread  →  GET /contact-inquiries/{id} (view-inquiry)
  ├── authorize('view-contact-inquiries')
  ├── abort(404) if thread.type != ContactInquiry
  ├── participant.last_read_at = now()
  └── Displays all messages (guest=blue, staff=zinc, note=amber)

Staff submits reply (isInternalNote=false, emailGuest=true)
  ├── authorize('view-contact-inquiries')
  ├── Message::create(thread_id, user_id=auth()->id(), body, kind=Message, guest_email_sent=false)
  ├── SendGuestConversationEmail::run(thread, message)
  │     ├── AnonymousNotifiable → ContactGuestReplyNotification (queued, mail)
  │     │     └── Email: "Re: {subject}" + conversation URL
  │     └── message.guest_email_sent = true
  ├── thread.last_message_at = now()
  ├── participant.last_read_at = now() (sender)
  ├── foreach other non-viewer participants:
  │     └── TicketNotificationService::send(participant.user, NewContactInquiryNotification, 'staff_alerts')
  └── RecordActivity(thread, 'reply_sent', 'Staff reply sent.')

Staff changes status
  ├── ThreadStatus::tryFrom(newStatus)
  ├── thread.update(status, closed_at if closing)
  └── RecordActivity(thread, 'status_changed', ...)
```

### 14.3 Guest views thread and replies

```
Guest browser  →  GET /contact/thread/{token} (guest-thread)
  ├── Thread::where('conversation_token', token)->where('type', ContactInquiry)->first()
  ├── abort(404) if not found
  └── Displays messages filtered to kind != InternalNote
        guest messages = blue, staff messages = zinc
        canReply = (status == Open || status == Pending)

Guest submits reply
  ├── Message::create(thread_id, user_id=NULL, body, kind=Message, guest_email_sent=false)
  ├── thread.last_message_at = now()
  └── foreach non-viewer participants:
        └── TicketNotificationService::send(user, NewContactInquiryNotification, 'staff_alerts')
```

### 14.4 Guest registers an account

```
register.blade.php  →  User::create(...)
  └── LinkContactThreadsOnRegistration::run($newUser)
        ├── Thread::where(type=ContactInquiry)
        │     .whereRaw('LOWER(guest_email) = ?', [LOWER(user.email)])
        │     .get()
        └── foreach thread:
              thread->addParticipant($newUser)
                (idempotent — firstOrCreate)
```

---

## 15. Configuration

### hCaptcha

| Environment variable | Config key | Required? | Notes |
|---|---|---|---|
| `HCAPTCHA_SITE_KEY` | `services.hcaptcha.site_key` | No | If blank, captcha widget is not rendered |
| `HCAPTCHA_SECRET_KEY` | `services.hcaptcha.secret_key` | No | If blank, server-side verification is skipped |

Both keys must be set together for hCaptcha to be active. When either is absent the
captcha widget is hidden and no token validation is performed, which is the expected
development/testing behaviour.

The widget is rendered via the hCaptcha JavaScript API:
`https://js.hcaptcha.com/1/api.js`. The `onHcaptchaSuccess` JS callback calls
`@this.set('hcaptchaToken', token)` to populate the Livewire property.

### IP Rate Limiting

Rate limiter key: `contact-form:{request()->ip()}`  
Max attempts: `5`  
Decay: `3600` seconds (1 hour)  
Implemented inline in `contact-form` component using `Illuminate\Support\Facades\RateLimiter`.

### Honeypot

Hidden input `name="website"` bound to `wire:model="honeypot"`. The field has
`style="display:none"`, `aria-hidden="true"`, `tabindex="-1"`, and `autocomplete="off"`.
Any non-empty value triggers silent discard (sets `$submitted = true` without persisting
data). No error is shown to the submitter.

---

## 16. Test Coverage

All tests are written with Pest and live in `tests/Feature/`.

### Actions

| File | Tests | What is covered |
|---|---|---|
| `tests/Feature/Actions/Actions/CreateContactInquiryTest.php` | 8 | Thread creation, UUID token, null guest_name, message body/kind, confirmation notification, staff notification, staff participant added, activity log, admin notification |
| `tests/Feature/Actions/Actions/SendGuestConversationEmailTest.php` | 2 | Notification sent to guest email, `guest_email_sent` flipped to true |
| `tests/Feature/Actions/Actions/LinkContactThreadsOnRegistrationTest.php` | 6 | Participant added on email match, multiple threads linked, idempotency, no match = no link, non-contact-inquiry threads excluded, case-insensitive match |

### Livewire Components

| File | Tests | What is covered |
|---|---|---|
| `tests/Feature/Livewire/ContactFormTest.php` | 9 | Public access, form renders, validation errors, email format, message min length, invalid category, honeypot discard, success state, thread created, IP rate limiting |
| `tests/Feature/Livewire/ContactInquiryListTest.php` | 9 | Unauthenticated redirect, role check (deny/allow), admin access, heading renders, thread listing, non-contact-inquiry excluded, open filter default, closed filter, sort order, unread badge |
| `tests/Feature/Livewire/GuestThreadTest.php` | 12 | Public access, invalid token 404, missing token 404, subject/category display, message order, internal notes hidden, reply form for open/pending, closed banner + no reply form, resolved no reply form, guest reply creates message, guest reply notifies staff, guest name attribution, staff name attribution |
| `tests/Feature/Livewire/ViewInquiryTest.php` | 14 | Auth required, role deny/allow, 404 for non-contact-inquiry, header displays guest info, message order, emailed indicator, internal note styling, reply with email ON sends notification + sets flag, reply with email OFF no notification, internal note no email, email toggle hidden for note, email toggle visible for reply, reply form hidden when closed, status change to resolved/closed |

---

## 17. File Map

```
app/
  Actions/
    CreateContactInquiry.php
    SendGuestConversationEmail.php
    LinkContactThreadsOnRegistration.php
  Enums/
    ThreadType.php          (ContactInquiry case added here)
    ThreadStatus.php
    MessageKind.php
  Models/
    Thread.php              (guest_name, guest_email, conversation_token fillable/cast)
    Message.php             (guest_email_sent fillable/cast)
    ThreadParticipant.php
  Notifications/
    ContactSubmissionConfirmationNotification.php
    NewContactInquiryNotification.php
    ContactGuestReplyNotification.php
  Providers/
    AuthServiceProvider.php (view-contact-inquiries gate)
  Services/
    TicketNotificationService.php

resources/views/
  livewire/
    contact/
      contact-form.blade.php
      inquiry-list.blade.php
      view-inquiry.blade.php
      guest-thread.blade.php
    auth/
      register.blade.php    (LinkContactThreadsOnRegistration hook)
  components/layouts/app/
    sidebar.blade.php       (Contact Inquiries nav item)
  mail/
    contact-submission-confirmation.blade.php
    new-contact-inquiry.blade.php
    contact-guest-reply.blade.php

routes/
  web.php                   (4 contact routes)

database/migrations/
  2026_02_12_214853_create_threads_table.php   (original threads schema)
  2026_02_12_214857_create_messages_table.php  (original messages schema)
  2026_04_01_000001_add_guest_fields_to_threads_table.php
  2026_04_01_000002_add_guest_email_sent_to_messages_table.php
  2026_04_01_000003_seed_contact_receive_submissions_role.php
  2026_04_01_000004_make_threads_created_by_user_id_nullable.php
  2026_04_01_000005_make_messages_user_id_nullable.php

tests/Feature/
  Actions/Actions/
    CreateContactInquiryTest.php
    SendGuestConversationEmailTest.php
    LinkContactThreadsOnRegistrationTest.php
  Livewire/
    ContactFormTest.php
    ContactInquiryListTest.php
    GuestThreadTest.php
    ViewInquiryTest.php

config/
  services.php              (services.hcaptcha.site_key / secret_key)
```

---

## 18. Known Issues & Improvement Opportunities

1. **`view-inquiry` re-uses `NewContactInquiryNotification` for staff-to-staff
   notifications.** When a staff user replies, other staff participants receive a
   "New Contact Inquiry" notification rather than a "New Reply" notification. A dedicated
   `NewContactReplyNotification` would be more descriptive.

2. **No rate limiting on the guest thread reply (`guest-thread`).** A guest with a valid
   token can send unlimited replies. IP or session-based rate limiting on
   `submitReply()` would prevent abuse.

3. **Category is encoded in the subject string** (`[Category] Subject`). The
   `inquiry-list` and `view-inquiry` components extract it with a regex. A dedicated
   `category` column would be cleaner and queryable.

4. **`LinkContactThreadsOnRegistration` is called unconditionally** during every
   registration, even for users who never submitted a contact form. The query is cheap
   (indexed on `type`, filtered by email), but it is worth noting for performance
   profiling on high-volume registration periods.

5. **No pagination on the inquiry list.** `inquiry-list` loads all matching threads in a
   single query. If the volume of contact inquiries grows significantly, pagination or
   a cursor-based approach should be added.

6. **`conversation_token` is a UUID stored as a plain string.** There is no expiry
   mechanism. An old or shared token URL gives permanent read/write access to the thread.
   A future improvement could add token expiry or revocation.

7. **hCaptcha is skipped entirely in local/test environments** when keys are not set.
   This is by design but means captcha coverage is not tested end-to-end in CI.
