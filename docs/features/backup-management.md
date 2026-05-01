# Backup Management -- Technical Documentation

> **Audience:** Project owner, developers, AI agents
> **Generated:** 2026-04-28
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

The Backup Management feature provides Backup Manager staff with a complete database backup lifecycle: creating, downloading, restoring, and deleting both local and S3-hosted backups of the application database. It supports PostgreSQL, MySQL/MariaDB, and SQLite databases.

The feature is accessible only to users holding the **Backup Manager** role. The dashboard is linked from the Staff Ready Room. Automated scheduling handles daily backup creation, daily local retention cleanup, and S3 uploads every three days.

Key concepts:
- **Local backups**: Compressed SQL dumps (`.sql.gz`) stored at `storage/app/backups/`. Created on-demand or by the daily cron. Retained for a configurable window (default: 7 days).
- **S3 backups**: Copies of local backups uploaded to the configured S3 bucket under the `backups/` prefix. Retained using a three-tier strategy: 2 most recent, 1 per week for 4 weeks, and 1 per calendar month for 3 months.
- **Maintenance mode**: Optionally puts the site into Laravel maintenance mode during backup and restore operations, configurable via `SiteConfig`.
- **Storage stats**: An informational panel showing file counts and sizes for all public asset directories (staff photos, board member photos, message images, etc.).

Users interact primarily via the `/backups` dashboard. Artisan commands (`app:backup-create`, `app:backup-restore`, `app:backup-cleanup`, `app:backup-push-s3`) allow CLI-level operation as well.

---

## 2. Database Schema

No new tables are created by this feature. It seeds two sets of existing-table data:

### `roles` table (seeded entry)

| Column | Type | Value |
|--------|------|-------|
| `name` | string | `Backup Manager` |
| `description` | string | `Access to the backup management dashboard: create, download, restore, and delete database backups.` |
| `color` | string | `amber` |
| `icon` | string | `circle-stack` |

**Migration:** `database/migrations/2026_04_28_000001_seed_backup_manager_role.php`

### `site_configs` table (seeded entries)

| Key | Default Value | Description |
|-----|---------------|-------------|
| `backup.local_retention_days` | `7` | Number of days to retain local backup files |
| `backup.offline_during_backup` | `false` | Whether to enter maintenance mode during backup |
| `backup.offline_during_restore` | `true` | Whether to enter maintenance mode during restore |

**Migration:** `database/migrations/2026_04_28_000002_seed_backup_site_config.php`

Backup files themselves are stored on the filesystem, not in the database.

---

## 3. Models & Relationships

### SiteConfig (`app/Models/SiteConfig.php`)

Used as the backing store for backup configuration. No backup-specific relationships.

**Key Methods:**
- `getValue(string $key, ?string $default): ?string` -- Retrieves a config value with a 5-minute cache TTL.
- `setValue(string $key, ?string $value): void` -- Upserts a value and clears the cache.

**Casts:** None relevant to this feature.

No dedicated model exists for backups — files are managed directly via the filesystem and S3 SDK.

---

## 4. Enums Reference

Not applicable for this feature. No enums are used in the backup system.

---

## 5. Authorization & Permissions

### Gates (from `AuthServiceProvider`)

| Gate Name | Who Can Pass | Logic Summary |
|-----------|-------------|---------------|
| `backup-manager` | Users with the "Backup Manager" role | `$user->hasRole('Backup Manager')` |

The `hasRole()` method on `User` checks three paths in order: admin override (admins pass all gates), staff position roles (direct assignment or `has_all_roles_at` flag), and rank-based role assignments.

### Policies

No policies are defined for this feature. Authorization is enforced entirely via the `backup-manager` gate.

### Permissions Matrix

| User Type | Access Dashboard | Create Backup | Restore | Upload | Delete Local | Delete S3 | View Stats |
|-----------|:---:|:---:|:---:|:---:|:---:|:---:|:---:|
| Unauthenticated | ✗ | ✗ | ✗ | ✗ | ✗ | ✗ | ✗ |
| Authenticated (no role) | ✗ | ✗ | ✗ | ✗ | ✗ | ✗ | ✗ |
| Backup Manager | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| Admin | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |

---

## 6. Routes

| Method | URL | Middleware | Handler | Route Name |
|--------|-----|-----------|---------|------------|
| GET | `/backups` | `auth`, `can:backup-manager` | `backup.dashboard` (Volt) | `backups.index` |

---

## 7. User Interface Components

### Backup Dashboard
**File:** `resources/views/livewire/backup/dashboard.blade.php`
**Route:** `/backups` (route name: `backups.index`)

**Purpose:** Single-page dashboard for all backup operations: listing/creating/downloading/restoring/deleting local backups, S3 backup management, file upload, storage stats, and maintenance mode settings.

**Authorization:** `$this->authorize('backup-manager')` called in `mount()` and each mutating method.

**User Actions Available:**
- **Create Backup Now** — dispatches `CreateBackupJob` → `BackupService::create()` → creates `.sql.gz` file
- **Download (local)** — streams local file to browser via `response()->streamDownload()`
- **Restore (local)** — confirms via modal, then dispatches `RestoreBackupJob` → `RestoreService::restore()`
- **Delete (local)** — confirms via modal, then calls `unlink()` on the file path
- **Download (S3)** — streams S3 file to browser via `Storage::disk('s3')->readStream()`
- **Delete (S3)** — confirms via modal, then calls `BackupStorageService::delete()`
- **Upload** — uploads a `.sql.gz` file from the browser to `storage/app/backups/`
- **Offline During Backup toggle** — saves to `SiteConfig::setValue('backup.offline_during_backup', ...)`
- **Offline During Restore toggle** — saves to `SiteConfig::setValue('backup.offline_during_restore', ...)`

**Computed Properties:**
- `localBackups()` — globs `storage/app/backups/*.sql.gz`, returns array with `filename`, `size`, `date`, `db_type`
- `s3Configured()` — checks `filesystems.disks.s3.key` and `bucket` are non-empty
- `s3Connected()` — calls `BackupStorageService::isConnected()`; short-circuits to `false` if not configured
- `s3Backups()` — calls `BackupStorageService::listWithMetadata()`; returns `[]` when not connected
- `storageStats()` — iterates 7 known public asset directories using `Storage::disk($publicDisk)->allFiles()`, returns `label`, `directory`, `count`, `total_size`

**UI Elements:**
- Local Backups card: table of local `.sql.gz` files with Download/Restore/Delete buttons per row; "Create Backup Now" primary button
- S3 Backups card: connectivity badge (green/red/grey), table of S3 files with Download/Delete buttons; configured/unreachable/not-configured states
- Upload Backup card: file input (`accept=".gz"`), form submit
- File Asset Storage card: table of asset directory stats (label, path, file count, total size)
- Settings card: two `flux:switch` toggles with `wire:model.live`
- Three confirmation modals: `confirm-restore`, `confirm-delete`, `confirm-delete-s3`

---

## 8. Actions (Business Logic)

Not applicable for this feature — the feature does not use Action classes (the `AsAction` trait pattern). Business logic lives in Service classes. See [Section 12: Services](#12-services).

---

## 9. Notifications

### BackupCreatedNotification (`app/Notifications/BackupCreatedNotification.php`)

**Triggered by:** `CreateBackup` Artisan command (after successful `BackupService::create()`)
**Recipient:** All users with the "Backup Manager" role
**Channels:** `mail` (always), `pushover` (if `pushoverKey` set on the notification)
**Mail subject:** `"Database Backup Created"`
**Content summary:** "A database backup was created successfully. File: {filename}"
**Queued:** Yes (`ShouldQueue`)

### BackupFailedNotification (`app/Notifications/BackupFailedNotification.php`)

**Triggered by:** `CreateBackup` Artisan command (on exception from `BackupService::create()`)
**Recipient:** All users with the "Backup Manager" role
**Channels:** `mail` (always), `pushover` (if `pushoverKey` set)
**Mail subject:** `"Database Backup Failed"`
**Content summary:** "A database backup attempt failed. Error: {error message}"
**Queued:** Yes (`ShouldQueue`)

### RestoreCompletedNotification (`app/Notifications/RestoreCompletedNotification.php`)

**Triggered by:** `RestoreBackup` Artisan command (after successful `RestoreService::restore()`)
**Recipient:** All users with the "Backup Manager" role
**Channels:** `mail` (always), `pushover` (if `pushoverKey` set)
**Mail subject:** `"Database Restore Completed"`
**Content summary:** "Database restore completed successfully. File: {filename}"
**Queued:** Yes (`ShouldQueue`)

### RestoreFailedNotification (`app/Notifications/RestoreFailedNotification.php`)

**Triggered by:** `RestoreBackup` Artisan command (on exception from `RestoreService::restore()`)
**Recipient:** All users with the "Backup Manager" role
**Channels:** `mail` (always), `pushover` (if `pushoverKey` set)
**Mail subject:** `"Database Restore Failed"`
**Content summary:** "A database restore attempt failed. Error: {error message}"
**Queued:** Yes (`ShouldQueue`)

> **Note:** Notifications are sent via `TicketNotificationService::send($manager, $notification, 'staff_alerts')`. Only the Artisan commands send notifications — the Livewire dashboard jobs (`CreateBackupJob`, `RestoreBackupJob`) do **not** send notifications directly. To receive notifications on dashboard-triggered operations, the jobs would need to call the commands or notify separately.

---

## 10. Background Jobs

### CreateBackupJob (`app/Jobs/CreateBackupJob.php`)

**Triggered by:** `Flux::button` → `createBackup()` in the Backup Dashboard Volt component
**What it does:** Resolves `BackupService` from the container and calls `create()`, which produces a `.sql.gz` file in `storage/app/backups/`
**Queue/Delay:** Default queue, no delay. `ShouldQueue`. No retry configuration specified (uses framework defaults).

### RestoreBackupJob (`app/Jobs/RestoreBackupJob.php`)

**Triggered by:** `restoreBackup()` in the Backup Dashboard Volt component, after the confirmation modal
**What it does:** Receives the full `$path` as a constructor argument, resolves `RestoreService`, and calls `restore($path)`
**Queue/Delay:** Default queue, no delay. `ShouldQueue`. No retry configuration specified.

> **Important:** Both dashboard jobs run silently — they do not send success/failure notifications. Notifications are only sent by the `CreateBackup` and `RestoreBackup` Artisan commands.

---

## 11. Console Commands & Scheduled Tasks

### `app:backup-create`
**File:** `app/Console/Commands/CreateBackup.php`
**Scheduled:** Yes — daily at `03:00` (`runInBackground`)
**What it does:** Calls `BackupService::create()`. Optionally skips maintenance mode with `--skip-offline`. Notifies all Backup Manager users of success or failure via `BackupCreatedNotification` / `BackupFailedNotification`.
**Options:** `--skip-offline` — bypasses the `backup.offline_during_backup` SiteConfig check.

### `app:backup-restore`
**File:** `app/Console/Commands/RestoreBackup.php`
**Scheduled:** No (manual only)
**What it does:** Accepts a bare filename argument (e.g., `backup_2026-04-28_03-00-00_sqlite.sql.gz`), builds the full path from `storage/app/backups/`, calls `RestoreService::restore($path)`. Notifies all Backup Manager users of success or failure.

### `app:backup-cleanup`
**File:** `app/Console/Commands/CleanupBackups.php`
**Scheduled:** Yes — daily at `04:00` (`runInBackground`)
**What it does:** Calls `BackupRetentionService::enforceLocalRetention()` to delete local files older than `backup.local_retention_days` (default: 7). If S3 is configured (`filesystems.disks.s3.key` is non-empty), also runs `enforceS3Retention()` — failures are caught and warned but do not fail the command.

### `app:backup-push-s3`
**File:** `app/Console/Commands/PushBackupToS3.php`
**Scheduled:** Yes — every 3 days at `03:30` (`cron('30 3 */3 * *')`, `runInBackground`)
**What it does:** Finds the most recent local `.sql.gz` by `filemtime()`, uploads it to S3 via `BackupStorageService::upload()`, then enforces S3 retention tiers via `BackupRetentionService::enforceS3Retention()`. Returns `FAILURE` if no local backup files exist.

---

## 12. Services

### BackupService (`app/Services/BackupService.php`)

**Purpose:** Creates a gzip-compressed SQL dump of the active database and writes it to `storage/app/backups/`.

**Key methods:**
- `setSkipOffline(bool $skip): self` — Chainable; bypasses the maintenance mode setting for this instance.
- `create(): string` — Orchestrates: resolves DB driver, formats filename (`backup_YYYY-MM-DD_HH-MM-SS_{dbtype}.sql.gz`), optionally enters maintenance mode, dumps the database, writes to disk, exits maintenance mode. Returns the file path.

**Driver support:**
| Driver | Tool used |
|--------|-----------|
| `pgsql` | `pg_dump` via `Process::run()` with `PGPASSWORD` env var |
| `mysql` / `mariadb` | `mysqldump` via `Process::run()` |
| `sqlite` | Native PDO: reads all non-system tables' CREATE statements and INSERT rows |

**Called by:** `CreateBackupJob`, `CreateBackup` Artisan command.

---

### RestoreService (`app/Services/RestoreService.php`)

**Purpose:** Restores a gzip-compressed SQL backup file into the active database.

**Key methods:**
- `restore(string $path): void` — Detects source DB type from filename, matches against target DB driver, optionally enters maintenance mode, calls the appropriate restore path.

**Source type detection:** Reads `_pgsql.sql.gz`, `_mysql.sql.gz`, or `_sqlite.sql.gz` suffix from the filename.

**Restore paths:**
| Source = Target | Method |
|-----------------|--------|
| `pgsql → pgsql` | Pipes SQL to `psql` via `Process::input()` |
| `mysql → mysql` | Pipes SQL to `mysql` via `Process::input()` |
| `sqlite → sqlite` | Drops all tables, re-runs CREATE + INSERT via PDO (splits on `;`, strips comments) |
| Cross-type | Requires `pgloader` on `$PATH`; builds DSN and invokes `pgloader`; raises a clear error if missing |

**Driver resolution:** Uses `$targetConfig['driver']` (not the connection name) to handle named connections like `restore_test`.

**Called by:** `RestoreBackupJob`, `RestoreBackup` Artisan command.

---

### BackupStorageService (`app/Services/BackupStorageService.php`)

**Purpose:** Wraps S3 operations for backup files under the `backups/` prefix.

**Key methods:**
- `upload(string $localPath): string` — Uploads file to S3 at `backups/{filename}`, returns the S3 key.
- `list(): array` — Returns all `.sql.gz` keys under `backups/`, sorted newest-first by filename timestamp.
- `download(string $key): string` — Downloads S3 file to a temp path and returns the local path. Caller is responsible for cleanup.
- `delete(string $key): void` — Deletes the S3 key.
- `isConfigured(): bool` — Returns `true` when `filesystems.disks.s3.key` and `bucket` are both non-empty.
- `isConnected(): bool` — Returns `true` if configured and `Storage::disk('s3')->files(...)` succeeds without exception.
- `listWithMetadata(): array` — Returns array of `{key, filename, size (formatted), date}` for each S3 backup.
- `parseTimestamp(string $key): Carbon` — Extracts `YYYY-MM-DD_HH-MM-SS` from the filename and returns a Carbon instance. Returns `Carbon::epoch()` if the pattern does not match.

**Called by:** `PushBackupToS3` command, `CleanupBackups` command, `BackupRetentionService`, Backup Dashboard component.

---

### BackupRetentionService (`app/Services/BackupRetentionService.php`)

**Purpose:** Enforces retention policies for both local and S3 backups.

**Key methods:**
- `enforceLocalRetention(): array` — Deletes `.sql.gz` files in `storage/app/backups/` older than `backup.local_retention_days` SiteConfig days. Returns array of deleted paths.
- `enforceS3Retention(BackupStorageService $storage): array` — Applies three-tier retention to S3 keys. Returns array of deleted keys.

**S3 retention tiers:**
1. **2 most recent** (by filename timestamp, regardless of age)
2. **1 per 7-day window** for the past 4 weeks
3. **1 per calendar month** for the past 3 months

Any key not selected by any tier is deleted. Note: a key can qualify for multiple tiers and is kept once.

**Called by:** `CleanupBackups` command, `PushBackupToS3` command.

---

## 13. Activity Log Entries

Not applicable for this feature. No `RecordActivity::run()` calls are made in the backup system. Backup operations are tracked via notifications to managers rather than the activity log.

---

## 14. Data Flow Diagrams

### Creating a Backup (Dashboard)

```
User clicks "Create Backup Now" on /backups
  -> wire:click="createBackup"
    -> dashboard.blade.php::createBackup()
      -> $this->authorize('backup-manager')
      -> CreateBackupJob::dispatch()
        [queued job]
        -> BackupService::create()
          -> Reads DB driver from config
          -> Optionally: Artisan::call('down') [if offline_during_backup=true]
          -> Dumps DB (pg_dump / mysqldump / PDO SQLite)
          -> gzopen() + gzwrite() → storage/app/backups/backup_YYYY-MM-DD_HH-MM-SS_<type>.sql.gz
          -> Optionally: Artisan::call('up')
          -> Returns file path
      -> Flux::toast('Backup job queued.', 'Success', variant: 'success')
```

### Creating a Backup (Scheduled)

```
Scheduler fires dailyAt('03:00')
  -> app:backup-create (CreateBackup command)
    -> BackupService::create() [same as above]
    -> On success:
      -> BackupCreatedNotification sent to all Backup Manager users (mail + pushover)
    -> On failure:
      -> BackupFailedNotification sent to all Backup Manager users (mail + pushover)
```

### Restoring a Backup (Dashboard)

```
User clicks "Restore" button on a local backup row
  -> wire:click="confirmRestore('{filename}')"
    -> $restoreTarget = $filename
    -> Flux::modal('confirm-restore')->show()

User clicks "Restore" in the confirmation modal
  -> wire:click="restoreBackup"
    -> $this->authorize('backup-manager')
    -> Builds path: storage/app/backups/{$restoreTarget}
    -> RestoreBackupJob::dispatch($path)
      [queued job]
      -> RestoreService::restore($path)
        -> Detects source type from filename
        -> Resolves target driver from config
        -> Optionally: Artisan::call('down')
        -> Restores via psql / mysql / PDO (same-type) or pgloader (cross-type)
        -> Optionally: Artisan::call('up')
    -> Flux::modal('confirm-restore')->close()
    -> Flux::toast('Restore job queued.', 'Success', variant: 'success')
```

### Uploading a Backup

```
User selects a .gz file in the Upload form and clicks "Upload"
  -> wire:submit="uploadBackup"
    -> $this->authorize('backup-manager')
    -> validate(['uploadFile' => ['required','file','mimes:gz','max:524288']])
    -> Checks getClientOriginalName() ends with '.sql.gz'
      -> If not: $this->addError('uploadFile', '...')  [return early]
    -> Ensures storage/app/backups/ directory exists
    -> $this->uploadFile->storeAs('backups', $originalName, 'local')
    -> $this->uploadFile = null
    -> unset($this->localBackups) [clears computed cache]
    -> Flux::toast("Uploaded {filename}.", 'Done', variant: 'success')
```

### S3 Upload (Scheduled)

```
Scheduler fires cron('30 3 */3 * *')
  -> app:backup-push-s3 (PushBackupToS3 command)
    -> Globs storage/app/backups/*.sql.gz, picks most recent by filemtime()
    -> BackupStorageService::upload($localPath)
      -> Storage::disk('s3')->put("backups/{$filename}", file_get_contents($localPath))
    -> BackupRetentionService::enforceS3Retention($storageService)
      -> Evaluates 3-tier retention, deletes excess keys from S3
```

### Local Retention Cleanup (Scheduled)

```
Scheduler fires dailyAt('04:00')
  -> app:backup-cleanup (CleanupBackups command)
    -> BackupRetentionService::enforceLocalRetention()
      -> Globs storage/app/backups/*.sql.gz
      -> Deletes files with filemtime() older than backup.local_retention_days
    -> If S3 configured:
      -> BackupRetentionService::enforceS3Retention(BackupStorageService)
```

---

## 15. Configuration

### Environment Variables

| Variable | Used By | Purpose |
|----------|---------|---------|
| `AWS_ACCESS_KEY_ID` | `config/filesystems.php` (s3 disk) | S3 authentication key |
| `AWS_SECRET_ACCESS_KEY` | `config/filesystems.php` (s3 disk) | S3 authentication secret |
| `AWS_DEFAULT_REGION` | `config/filesystems.php` (s3 disk) | S3 region (e.g., `us-east-1`) |
| `AWS_BUCKET` | `config/filesystems.php` (s3 disk) | S3 bucket name |
| `AWS_URL` | `config/filesystems.php` (s3 disk) | Custom S3 endpoint URL (optional) |
| `AWS_ENDPOINT` | `config/filesystems.php` (s3 disk) | Custom S3 endpoint (e.g., for MinIO) |
| `AWS_USE_PATH_STYLE_ENDPOINT` | `config/filesystems.php` (s3 disk) | Path-style endpoint toggle (default: `false`) |

### SiteConfig Keys

| Key | Default | Purpose | Set By |
|-----|---------|---------|--------|
| `backup.local_retention_days` | `7` | Days before local backups are auto-deleted | Migration (seeded) |
| `backup.offline_during_backup` | `false` | Maintenance mode during backup | Dashboard toggle |
| `backup.offline_during_restore` | `true` | Maintenance mode during restore | Dashboard toggle |

### Config Files

| File | Key | Purpose |
|------|-----|---------|
| `config/filesystems.php` | `public_disk` | Disk name for public asset storage (default: `public`); used by storage stats panel |
| `config/filesystems.php` | `disks.s3.*` | S3 connection parameters |

---

## 16. Test Coverage

### Test Files

| File | Tests | What It Covers |
|------|-------|----------------|
| `tests/Feature/Backup/BackupDashboardTest.php` | 20 | Dashboard auth, local backup list, create job, delete, restore, upload, settings, S3 panel, storage stats |
| `tests/Feature/Commands/CreateBackupTest.php` | 8 | Backup file creation, filename format, notifications, maintenance mode, scheduling |
| `tests/Feature/Commands/RestoreBackupTest.php` | 5 | Database state restoration, cross-type error, maintenance mode, notifications |
| `tests/Feature/Commands/CleanupBackupsTest.php` | 5 | Local retention policy, custom retention, scheduling |
| `tests/Feature/Services/BackupStorageServiceTest.php` | 15 | S3 upload/list/download/delete, retention tiers, push command, isConfigured/isConnected, listWithMetadata |

### Test Case Inventory

#### `BackupDashboardTest.php`
- unauthenticated users are redirected from the backup dashboard
- users without backup-manager role get 403 on the backup dashboard
- backup manager can access the backup dashboard
- ready room header shows Backups link only for backup managers
- dashboard lists local backup files with metadata
- clicking Create Backup Now dispatches a CreateBackupJob
- deleting a backup removes it from disk
- non-backup-manager is blocked at the route level
- restore action dispatches RestoreBackupJob
- confirmRestore sets restoreTarget
- uploading a valid .sql.gz file places it in storage
- uploading a non-.sql.gz file is rejected
- toggling offlineDuringBackup persists to SiteConfig
- toggling offlineDuringRestore persists to SiteConfig
- mount loads SiteConfig values into toggle properties
- dashboard shows S3 not configured when credentials are missing
- dashboard shows S3 connected when S3 is reachable
- dashboard lists S3 backup files in the S3 panel
- deleteS3Backup removes file from S3
- dashboard storage stats panel shows known asset directories

#### `CreateBackupTest.php`
- creates a .sql.gz backup file
- backup file has non-zero size
- backup filename includes timestamp and database type
- notifies backup managers on success
- notifies backup managers on failure
- does not trigger maintenance mode when offline_during_backup is false
- does not trigger maintenance mode with --skip-offline flag
- is scheduled daily at 03:00

#### `RestoreBackupTest.php`
- restores a backup and results in expected database state
- aborts cross-type restore with clear error when pgloader is not installed
- enters and exits maintenance mode during restore when offline_during_restore is true
- notifies backup managers on successful restore
- notifies backup managers on restore failure

#### `CleanupBackupsTest.php`
- deletes files older than the retention window and keeps newer files
- respects a custom retention window from SiteConfig
- returns an empty array when no files need to be deleted
- the app:backup-cleanup command exits successfully
- app:backup-cleanup is scheduled daily at 04:00

#### `BackupStorageServiceTest.php`
- uploads a local backup to S3 under the backups/ prefix
- lists S3 backups sorted newest-first by filename timestamp
- downloads an S3 backup to a temp file
- deletes an S3 backup by key
- S3 retention keeps 2 most recent, 1 per week for 4 weeks, 1 per month for 3 months
- S3 retention returns empty when no files exist
- app:backup-push-s3 uploads most recent local backup to S3
- app:backup-push-s3 fails when no local backups exist
- app:backup-push-s3 is scheduled every 3 days at 03:30
- isConfigured returns true when S3 credentials are set
- isConfigured returns false when S3 key is missing
- isConnected returns true when S3 is reachable
- isConnected returns false when S3 is not configured
- listWithMetadata returns filename, size, and date for each S3 backup
- listWithMetadata returns newest-first ordering

### Coverage Gaps

- **Dashboard-triggered job notifications**: `CreateBackupJob` and `RestoreBackupJob` (dispatched from the dashboard) do not send notifications on success or failure. Only the Artisan commands do. There are no tests verifying dashboard-triggered backup/restore outcomes beyond job dispatch.
- **Cross-type restore with pgloader present**: The cross-type restore path is only tested for the error case (pgloader missing). The success path when pgloader is available is untested.
- **S3 download streaming to browser**: `downloadFromS3()` uses `Storage::disk('s3')->readStream()` but this is not covered by any test (only `BackupStorageService::download()` to a temp file is tested).
- **Storage stats accuracy**: The storage stats panel test only checks that directory labels appear in the output — it does not verify correct file counts or sizes.
- **Maintenance mode lifecycle during jobs**: `RestoreBackupTest` tests maintenance mode for the Artisan command path, but not for the `RestoreBackupJob` queue path.

---

## 17. File Map

**Models:**
- `app/Models/SiteConfig.php` (configuration store)

**Enums:** None

**Actions:** None (uses Services instead)

**Policies:** None

**Gates:** `app/Providers/AuthServiceProvider.php` — gate: `backup-manager`

**Notifications:**
- `app/Notifications/BackupCreatedNotification.php`
- `app/Notifications/BackupFailedNotification.php`
- `app/Notifications/RestoreCompletedNotification.php`
- `app/Notifications/RestoreFailedNotification.php`

**Jobs:**
- `app/Jobs/CreateBackupJob.php`
- `app/Jobs/RestoreBackupJob.php`

**Services:**
- `app/Services/BackupService.php`
- `app/Services/RestoreService.php`
- `app/Services/BackupStorageService.php`
- `app/Services/BackupRetentionService.php`

**Controllers:** None (Volt component handles everything)

**Volt Components:**
- `resources/views/livewire/backup/dashboard.blade.php`

**Routes:**
- `GET /backups` → `backups.index`

**Migrations:**
- `database/migrations/2026_04_28_000001_seed_backup_manager_role.php`
- `database/migrations/2026_04_28_000002_seed_backup_site_config.php`

**Console Commands:**
- `app/Console/Commands/CreateBackup.php` (`app:backup-create`)
- `app/Console/Commands/RestoreBackup.php` (`app:backup-restore`)
- `app/Console/Commands/CleanupBackups.php` (`app:backup-cleanup`)
- `app/Console/Commands/PushBackupToS3.php` (`app:backup-push-s3`)

**Tests:**
- `tests/Feature/Backup/BackupDashboardTest.php`
- `tests/Feature/Commands/CreateBackupTest.php`
- `tests/Feature/Commands/RestoreBackupTest.php`
- `tests/Feature/Commands/CleanupBackupsTest.php`
- `tests/Feature/Services/BackupStorageServiceTest.php`

**Config:**
- `config/filesystems.php` — `disks.s3.*`, `public_disk`
- `routes/console.php` — scheduled task registrations

**Other:**
- `resources/views/dashboard/ready-room.blade.php` — Backups button (conditional on `backup-manager` gate)

---

## 18. Known Issues & Improvement Opportunities

1. **Dashboard jobs do not notify on completion**: `CreateBackupJob` and `RestoreBackupJob` run silently. Staff who trigger a backup or restore from the dashboard have no way to know if it succeeded or failed other than checking back on the dashboard. The Artisan commands send notifications but the jobs do not. Consider adding a `onFailed()` method to both jobs, or integrating notifications into the service layer rather than only the commands.

2. **`BackupService` reads the entire SQLite DB into memory**: The SQLite dump in `BackupService::dumpSqlite()` fetches all rows from all tables via `fetchAll()`. For large databases this could exhaust memory. A streaming approach or use of the `sqlite3` CLI tool would be more robust.

3. **S3 download (`downloadFromS3`) downloads the entire file to memory before streaming**: The current implementation uses `Storage::disk('s3')->readStream()` and `fpassthru()`, which should stream correctly. However, if the S3 stream is unavailable, `abort(404)` is called inside a streaming closure, which may produce inconsistent behavior.

4. **Storage stats panel performs N×M filesystem calls**: `storageStats()` calls `Storage::disk($publicDisk)->allFiles($dir)` plus `->size($file)` for every file in every directory. On large deployments this could be slow. No caching is applied to the computed property.

5. **`backup.local_retention_days` is not exposed in the dashboard UI**: The SiteConfig key exists and is respected by the cleanup command, but there is no UI control to change it. It can only be updated by direct database manipulation or migration.

6. **Cross-type restore error message references pgloader as the only solution**: The error message in `RestoreService::restoreCrossType()` only mentions `pgloader` and `pg2mysql`. If a user is trying to restore a PostgreSQL backup to SQLite (common in dev), this error is not particularly actionable.

7. **No audit trail for backup operations**: Backup creates, restores, and deletes are not logged to the `activity_logs` table. If a restore causes data loss, there is no in-app record of who triggered it or when.

8. **Hardcoded asset directory list in `storageStats()`**: The list of asset directories to inspect is hardcoded in the Volt component (`staff-photos`, `board-member-photos`, etc.). Adding a new upload type requires updating this list manually.
