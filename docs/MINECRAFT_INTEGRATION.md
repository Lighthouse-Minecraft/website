# Minecraft Server Integration — Feature Specification

This document is the authoritative specification for the Minecraft server integration feature.
Use it to validate that the implementation meets business requirements and to avoid regressions when making changes in this area.

---

## Overview

Community members can link their Java Edition or Bedrock Edition Minecraft accounts to their
Lighthouse website profile. Once linked and verified, the system automatically manages their
server whitelist status, in-game rank, and staff permissions via RCON.

The integration handles:
- Account linking and verification (two-phase: code generation → in-game confirmation)
- Rank synchronization (membership level → Minecraft rank group)
- Staff group synchronization (staff department → Minecraft staff group)
- Brig enforcement (whitelist removal and rank reset; restoration on release)
- Audit logging of every server command sent

---

## 1. Eligibility Requirements

A user may link a Minecraft account only if ALL of the following are true:

- **Membership level is at least Traveler** — Drifter and Stowaway users cannot link accounts
- **Not in the brig** — brig'd users are blocked from initiating verification
- **Below the account limit** — default maximum is 2 linked accounts per user
- **Within rate limits** — no more than 10 verification attempts per hour

---

## 2. Account Linking — Phase 1 (Code Generation)

Triggered when the user submits a username on `/settings/minecraft-accounts`.

**Validation steps (in order):**

1. Eligibility checks (see Section 1)
2. Resolve username → UUID via the appropriate API:
   - Java Edition: Mojang API (`api.mojang.com/users/profiles/minecraft/{username}`)
   - Bedrock Edition: GeyserMC Global API, with mcprofile.io as fallback
3. Verify the resolved UUID is not already linked to any account (any status)

**On success:**

- The canonical username returned by the API is stored (preserves correct casing)
- Bedrock gamertags are always stored with a leading `.` (Floodgate convention)
- Bedrock UUID is the Floodgate UUID derived deterministically from the Xbox XUID
- A 6-character verification code is generated, excluding visually ambiguous characters: `0 O 1 I L 5 S`
- A `MinecraftAccount` record is created with `status = verifying`
- The player is **synchronously** added to the server whitelist:
  - Java: `whitelist add <username>`
  - Bedrock: `fwhitelist add <floodgate_uuid>`
  - If the RCON command fails, the account record is deleted and an error is returned to the user
- A `MinecraftVerification` record is created with `status = pending` and an expiry timestamp
- Code expires after the configured grace period (default: 30 minutes)

**Code delivery:**

The code is displayed on the website. The user joins the server and runs `/verify <CODE>` in-game.
Codes are not emailed or delivered any other way.

---

## 3. Account Linking — Phase 2 (Verification Completion)

Triggered by the Minecraft server plugin POSTing to `/api/minecraft/verify` after a player
runs `/verify <CODE>` in-game.

**Webhook security:** Shared `MINECRAFT_VERIFICATION_TOKEN`. No user authentication.
Rate-limited to 30 requests per minute per IP.

**Validation steps:**

1. Code exists and has `status = pending`
2. Code is not expired
3. Username matches case-insensitively
4. UUID matches with dashes ignored
5. The `MinecraftAccount` in `verifying` status belongs to the same user as the verification record

**On success (inside a database transaction):**

- `MinecraftAccount.status` → `active`; `verified_at` and `last_username_check_at` set
- `MinecraftVerification.status` → `completed`
- Activity log recorded

**After the transaction (async, queued):**

- If the user's membership level maps to a rank: dispatch `lh setmember <username> <rank>`
- If the user is staff: dispatch `lh setstaff <username> <department>`

Commands are dispatched outside the transaction so the queue worker sees committed data.

---

## 4. Membership Level → Minecraft Rank Mapping

| Membership Level | Minecraft Command |
|---|---|
| Drifter (0) | — (cannot link accounts) |
| Stowaway (1) | — (cannot link accounts) |
| Traveler (2) | `lh setmember <username> traveler` |
| Resident (3) | `lh setmember <username> resident` |
| Citizen (4) | `lh setmember <username> citizen` |

**Rank sync triggers:**

- User promoted → sync ranks and staff across all active accounts
- User demoted → sync ranks and staff across all active accounts
- Account verified → rank dispatched individually after transaction

The `SyncMinecraftPermissions` action handles both rank and staff together;
`SyncMinecraftRanks` handles rank only.

---

## 5. Staff Department → Minecraft Staff Group Mapping

| Staff Department | Minecraft Command |
|---|---|
| Command | `lh setstaff <username> command` |
| Chaplain | `lh setstaff <username> chaplain` |
| Engineer | `lh setstaff <username> engineer` |
| Quartermaster | `lh setstaff <username> quartermaster` |
| Steward | `lh setstaff <username> steward` |
| None (position removed) | `lh removestaff <username>` |

**Staff sync triggers:**

- Staff position assigned → `SyncMinecraftStaff::run($user, $department)` across all active accounts
- Staff position removed → `SyncMinecraftStaff::run($user, null)` sends `lh removestaff` for all active accounts
- Account verified while user is staff → dispatched individually after transaction
- User promoted/demoted → handled by `SyncMinecraftPermissions`

---

## 6. Account Unlinking (User-Initiated)

Applies to **`Active` accounts only**. Verifying accounts use the cancellation flow (Section 7).

**Sequence (all synchronous):**

1. `lh setmember <username> default` — reset rank to default
2. `lh removestaff <username>` — if user holds a staff position
3. Whitelist remove (type-appropriate command)
4. Delete the `MinecraftAccount` record
5. Record activity log

---

## 7. Verification Cancellation (User-Initiated)

Applies to **`Verifying` accounts only**.

**Sequence:**

1. Send whitelist remove command (removes the temporary whitelist entry added during Phase 1)
2. Mark or delete the `MinecraftAccount` and `MinecraftVerification` records

---

## 8. Account Revocation (Admin-Initiated)

**Authorization:** Admin role only.

**Sequence:**

1. `lh setmember <username> default` — reset rank
2. Whitelist remove (type-appropriate command)
   - If whitelist removal fails: **abort** — do NOT delete the account record
3. If whitelist removal succeeded: delete the `MinecraftAccount` record
4. Record activity log on the **affected user** (not the admin)

The abort-on-failure guard ensures the account record is not orphaned from the server state.

---

## 9. Brig Integration

### When a user is placed in the brig

For each account in `active` or `verifying` status:

1. Dispatch `lh setmember <username> default` — remove rank from server
2. Dispatch `lh removestaff <username>` — remove staff group (if applicable)
3. Dispatch whitelist remove command
4. Set `MinecraftAccount.status` → `banned`

Activity logged; notification sent to user.

### When a user is released from the brig

For each account in `banned` status:

1. Dispatch whitelist add command
2. Set `MinecraftAccount.status` → `active`

After restoring all accounts, call `SyncMinecraftPermissions::run($user)` to re-apply the
user's current rank and staff position across all restored accounts.

Activity logged; notification sent to user.

---

## 10. RCON Command Reference

| Category | Command | When Sent |
|---|---|---|
| whitelist | `whitelist add <username>` | Java account: Phase 1 (verification starts) |
| whitelist | `fwhitelist add <uuid>` | Bedrock account: Phase 1 (verification starts) |
| whitelist | `whitelist remove <username>` | Java account: unlink / revoke / brig / cancel |
| whitelist | `fwhitelist remove <uuid>` | Bedrock account: unlink / revoke / brig / cancel |
| rank | `lh setmember <username> <rank>` | Account verified; user promoted or demoted |
| rank | `lh setmember <username> default` | Account unlinked / revoked / user brigd |
| rank | `lh setstaff <username> <department>` | Staff assigned; account verified while staff |
| rank | `lh removestaff <username>` | Staff removed; account unlinked; user brigd |

---

## 11. RCON Dispatch Strategy

Commands run either synchronously (blocking, fails fast) or asynchronously (queued, retried).

**Synchronous:**

- Whitelist add during Phase 1 — must succeed to proceed; failure aborts the entire flow
- All commands during account unlinking (`UnlinkMinecraftAccount`)
- All commands during account revocation (`RevokeMinecraftAccount`)

**Asynchronous (queued):**

- Rank and staff sync after Phase 2 completion — dispatched outside the DB transaction
- Whitelist remove/add during brig and brig release
- All rank/staff syncs via `SyncMinecraftPermissions` or `SyncMinecraftRanks`

**Retry policy for async commands:** 3 attempts with exponential backoff — 60s, 5min, 15min.

**Local environment override:** All commands execute synchronously regardless of the async flag,
so development does not require a running queue worker.

---

## 12. Audit Logging

Every RCON command is logged to the `minecraft_command_logs` table with:

- Command text, command type, and target player
- Status (success / failed), server response text, and error message
- The user who initiated the command, their IP address, and execution time in ms
- JSON metadata (action class, membership level, etc.)

Every account state change is also written to the activity log via `RecordActivity`.

---

## 13. Authorization Rules

| Action | Who Can Perform It |
|---|---|
| Link a Minecraft account | Authenticated user; membership level ≥ Traveler; not in brig |
| View own linked accounts | Account owner |
| Cancel own pending verification | Account owner |
| Unlink own active account | Account owner |
| View all accounts (admin panel) | Admin only |
| Revoke any account | Admin only |
| View RCON command log | Admin only |

Authorization for account operations uses `MinecraftAccountPolicy`. Membership-level gating
is enforced inside `GenerateVerificationCode` (the action checks eligibility). Authorization
checks must never be duplicated in Blade views — use `@can` / policies only.

---

## 14. Configuration Reference

| Key | Default | Description |
|---|---|---|
| `lighthouse.max_minecraft_accounts` | `2` | Maximum linked accounts per user |
| `lighthouse.minecraft_verification_grace_period_minutes` | `30` | Minutes before a pending code expires |
| `lighthouse.minecraft_verification_rate_limit_per_hour` | `10` | Verification attempts allowed per hour |
| `lighthouse.minecraft.server_name` | `Lighthouse MC` | Display name shown in UI |
| `lighthouse.minecraft.server_host` | `play.lighthousemc.net` | Join address shown to users |
| `lighthouse.minecraft.server_port_java` | `25565` | Java Edition port |
| `lighthouse.minecraft.server_port_bedrock` | `19132` | Bedrock Edition port |
| `services.minecraft.rcon_host` | `localhost` | RCON server host |
| `services.minecraft.rcon_port` | `25575` | RCON server port |
| `services.minecraft.rcon_password` | — | RCON password (required) |
| `services.minecraft.verification_token` | — | Shared secret for the webhook (required) |

---

## 15. External API Dependencies

| Service | Used For | Fallback |
|---|---|---|
| `api.mojang.com` | Java: username → UUID | None |
| `sessionserver.mojang.com` | Java: UUID → username (reverse lookup) | None |
| `api.geysermc.org` | Bedrock: gamertag → XUID | mcprofile.io |
| `mcprofile.io` | Bedrock: gamertag → XUID (fallback), UUID → gamertag reverse lookup | None |

Bedrock Floodgate UUIDs are computed deterministically from the Xbox XUID:
`UUID(0, xuid_as_long)` → `00000000-0000-0000-{high4}-{low12hex}`

---

## 16. User-Facing Pages

| URL | Component | Purpose |
|---|---|---|
| `/settings/minecraft-accounts` | `settings.minecraft-accounts` | User: link, view, cancel, unlink accounts |
| `/acp` → Minecraft Users | `admin-manage-mc-users-page` | Admin: browse all linked accounts |
| `/acp` → Command Log | `admin-manage-mc-command-log-page` | Admin: RCON audit log with search and filter |
| User profile card | `users/display-basic-details` | View accounts per user; admin can revoke |

---

## 17. Key Implementation Files

| Purpose | File |
|---|---|
| Phase 1 (code generation) | `app/Actions/GenerateVerificationCode.php` |
| Phase 2 (webhook completion) | `app/Actions/CompleteVerification.php` |
| User unlink | `app/Actions/UnlinkMinecraftAccount.php` |
| Admin revoke | `app/Actions/RevokeMinecraftAccount.php` |
| Rank sync | `app/Actions/SyncMinecraftRanks.php` |
| Staff sync | `app/Actions/SyncMinecraftStaff.php` |
| Rank + staff together | `app/Actions/SyncMinecraftPermissions.php` |
| RCON dispatch point | `app/Actions/SendMinecraftCommand.php` |
| Brig placement | `app/Actions/PutUserInBrig.php` |
| Brig release | `app/Actions/ReleaseUserFromBrig.php` |
| RCON client | `app/Services/MinecraftRconService.php` |
| Local RCON mock | `app/Services/FakeMinecraftRconService.php` |
| Java API client | `app/Services/MojangApiService.php` |
| Bedrock API client | `app/Services/McProfileService.php` |
| Account model | `app/Models/MinecraftAccount.php` |
| Verification model | `app/Models/MinecraftVerification.php` |
| Command audit model | `app/Models/MinecraftCommandLog.php` |
| Account status enum | `app/Enums/MinecraftAccountStatus.php` |
| Account type enum | `app/Enums/MinecraftAccountType.php` |
| Rank mapping | `app/Enums/MembershipLevel.php` (`minecraftRank()`) |
| Authorization policy | `app/Policies/MinecraftAccountPolicy.php` |
| Webhook API reference | `docs/MINECRAFT_API.md` |
