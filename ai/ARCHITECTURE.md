# Architecture Overview

## Stack

| Layer | Technology |
|---|---|
| Framework | Laravel (PHP) |
| Frontend | Livewire Volt (inline-class blade components) |
| UI library | Flux UI (`flux:*` components) |
| Testing | Pest |
| Notifications | Laravel Notifications (mail + Pushover custom channel) |
| Auth | Laravel Policies + Gates |
| Background work | Laravel Job classes in `app/Jobs/` |

---

## Domain Modules

### User & Membership
- `app/Models/User.php` — core model; has membership level, staff rank/department, brig state.
- `app/Enums/MembershipLevel.php` — ordered enum: Drifter → Stowaway → Traveler → Resident → Citizen.
- `app/Enums/StaffRank.php`, `StaffDepartment.php` — staff hierarchy.
- Actions: `PromoteUser`, `DemoteUser`, `PromoteUserToAdmin`.

### Brig System
- Users can be placed in the "Brig" (suspended access).
- Brig state stored as columns on `users` table (`in_brig`, `brig_reason`, `brig_expires_at`, etc.).
- Actions: `PutUserInBrig`, `ReleaseUserFromBrig`.
- Access restrictions enforced via `Gate::define('view-community-content', ...)` in `AuthServiceProvider`.
- **Rule**: brig enforcement ONLY via gates/policies — never via scattered `@if($user->in_brig)` in views.

### Minecraft Integration
- `app/Models/MinecraftAccount.php` — links users to Minecraft UUIDs.
- `app/Enums/MinecraftAccountStatus.php` — Pending/Verifying/Active/Banned/Revoked.
- Actions: `GenerateVerificationCode`, `CompleteVerification`, `SyncMinecraftRanks`,
  `SendMinecraftCommand`, `RevokeMinecraftAccount`, `UnlinkMinecraftAccount`.
- Verification webhook: `POST /api/minecraft/verify` (in `routes/web.php`).
- RCON service: `app/Services/MinecraftRconService.php` (must be mocked in tests).

### Support Tickets
- Models: `Thread`, `Message`, `MessageFlag`, `ThreadParticipant`.
- Policies: `ThreadPolicy`, `MessagePolicy`.
- Livewire components: `resources/views/livewire/ready-room/tickets/`.
- Notifications: smart delivery via `TicketNotificationService`.

### Meetings
- Models: `Meeting`, `MeetingNote`, `Task`.
- Controllers: `MeetingController`.
- Livewire components: `resources/views/livewire/meeting/`, `livewire/meetings/`.

### Announcements & Pages
- Models: `Announcement`, `Category`, `Tag`, `Page`.
- Controllers: `AnnouncementController`, `PageController`.

### Prayer Tracking
- Models: `PrayerCountry`, `PrayerCountryStat`.

### Activity Log
- Model: `ActivityLog`.
- Action: `RecordActivity` — log any significant action against any subject model.

---

## Data Flow for a Typical Feature Action

```
HTTP Request / Livewire Event
    → Livewire Volt Component (authorize, validate)
        → Action::run(...)
            → Mutate model(s) + save()
            → RecordActivity::run(...)
            → SyncMinecraftRanks::run(...) [if needed]
            → TicketNotificationService::send(...)
            → SomeJob::dispatch(...) [if deferred work needed]
        → Flux::toast(...) feedback to user
```

**UI routing rule**: Use Volt components for all interactive UI. Controllers only for
simple view-renders, redirects, and webhook endpoints.

---

## Authorization Architecture

- **Gates** (in `AuthServiceProvider::boot()`): for feature-level access (`view-community-content`,
  `manage-stowaway-users`, `view-ready-room`, etc.).
- **Policies** (in `app/Policies/`): for model-level CRUD (`UserPolicy`, `ThreadPolicy`, `MessagePolicy`).
- **`UserPolicy::before()`** grants admins and command officers a blanket bypass.
- In Livewire: `$this->authorize('gate-name')` or `$this->authorize('policy-ability', $model)`.
- In Blade: `@can` / `@cannot`.
- In routes: `->middleware('can:ability,Model')`.

---

## Notification Architecture

All notifications go through `TicketNotificationService::send($user, $notification)`:
- Determines channels (mail and/or Pushover) based on user preferences.
- Respects digest frequency (`EmailDigestFrequency` enum).
- Notifications implement `ShouldQueue` and `use Queueable`.
- Notifications have `setChannels(array, ?string)` method called by the service.
- Custom Pushover channel: `app/Notifications/Channels/PushoverChannel.php`.

---

## Routes Shape

- `routes/web.php` — main application routes.
- `routes/auth.php` — login/register/password flows.
- Mix of traditional controllers and `Volt::route('path', 'component.dot.name')`.
- Public: `/` (redirects to home page), `/donate`, named pages via `/{slug}`.
- Auth-protected: `/dashboard`, `/settings/*`, `/acp/*`, `/tickets/*`, `/meetings/*`.
- API-ish: `POST /api/minecraft/verify` (in web.php, throttled).

---

## Key File Locations

```
app/
  Actions/          # All synchronous business logic
  Jobs/             # Queued/deferred background work
  Enums/            # MembershipLevel, StaffRank, StaffDepartment, etc.
  Http/Controllers/ # Thin controllers (simple renders, redirects, webhooks only)
  Models/           # Eloquent models
  Notifications/    # Mail + Pushover notifications
  Policies/         # Authorization policies
  Providers/
    AuthServiceProvider.php   # Gates defined here
  Services/
    TicketNotificationService.php
    MinecraftRconService.php

resources/views/
  livewire/         # All Volt components
    dashboard/
    meeting/
    ready-room/tickets/
    settings/
    users/

tests/
  Feature/          # All feature tests (Pest)
    Actions/
    Brig/
    Minecraft/
    Policies/
    Tickets/
  Support/
    Users.php       # Test helper factories (loginAsAdmin, membershipTraveler, etc.)
  Pest.php          # Global test bootstrap
```
