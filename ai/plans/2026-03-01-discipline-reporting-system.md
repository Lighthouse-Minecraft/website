# Plan: Disciplinary Reporting System

**Date**: 2026-03-01
**Planned by**: Claude Code
**Status**: IMPLEMENTED

## Summary

Staff members can create disciplinary reports about users, documenting incidents with location, witnesses, actions taken, and severity. Reports follow a draft/published workflow where any staff member (JrCrew+) can create drafts, but only Officers can publish them. A risk score system aggregates severity points over 7/30/90-day windows with intentional triple-counting of recent events. Reports appear on user profiles, the Quartermaster dashboard widget, an ACP reports log, and the parent portal for child accounts.

## Files to Read (for implementing agent context)
- `CLAUDE.md`
- `ai/CONVENTIONS.md`
- `ai/ARCHITECTURE.md`
- `app/Providers/AuthServiceProvider.php`
- `app/Models/User.php`
- `app/Actions/PutUserInBrig.php` (action pattern reference)
- `app/Notifications/UserPutInBrigNotification.php` (notification pattern reference)
- `resources/views/livewire/users/display-basic-details.blade.php` (Volt component pattern)
- `resources/views/livewire/admin-control-panel-tabs.blade.php` (ACP tab pattern)
- `resources/views/livewire/parent-portal/index.blade.php` (parent portal pattern)
- `resources/views/dashboard.blade.php` (dashboard layout)
- `tests/Feature/Actions/Actions/PutUserInBrigTest.php` (test pattern reference)

## Authorization Rules

### Gates (add to `app/Providers/AuthServiceProvider.php`)

```php
// Staff (JrCrew+) can manage discipline reports
Gate::define('manage-discipline-reports', function ($user) {
    return $user->hasRole('Admin') || $user->isAtLeastRank(StaffRank::JrCrew);
});

// Only Officers+ can publish discipline reports
Gate::define('publish-discipline-reports', function ($user) {
    return $user->hasRole('Admin') || $user->isAtLeastRank(StaffRank::Officer);
});

// Reuse existing view-mc-command-log pattern for ACP log tab access
// (Admin || Officer || Engineer dept)
Gate::define('view-discipline-report-log', $canViewLogs);
```

### Policy: `app/Policies/DisciplineReportPolicy.php`

```php
// before(): Auto-allow Admin || Command Officer (same as UserPolicy)
// viewAny(): Staff (JrCrew+)
// view(): Staff (JrCrew+) OR subject user (published only) OR subject's parent (published only)
// create(): Staff (JrCrew+)
// update(): (Creator OR Officer+) AND status == draft
// publish(): Officer+ only
// delete(): false (reports are never deleted)
```

Register in AuthServiceProvider `$policies` array:
```php
\App\Models\DisciplineReport::class => \App\Policies\DisciplineReportPolicy::class,
```

## Database Changes

| Migration file | Table | Change |
|---|---|---|
| `YYYY_MM_DD_HHMMSS_create_discipline_reports_table.php` | `discipline_reports` | Create table |

### Column Details

| Column | Type | Nullable | Default | Purpose |
|---|---|---|---|---|
| `id` | bigIncrements | no | auto | PK |
| `subject_user_id` | foreignId | no | — | User the report is about |
| `reporter_user_id` | foreignId | no | — | Staff member who created it |
| `publisher_user_id` | foreignId | yes | null | Officer who published it |
| `description` | text | no | — | What happened |
| `location` | string | no | — | Where it happened (enum) |
| `witnesses` | text | yes | null | Free text witness description |
| `actions_taken` | text | no | — | What actions were taken |
| `severity` | string | no | — | Severity level (enum) |
| `status` | string | no | 'draft' | Draft or Published (enum) |
| `published_at` | timestamp | yes | null | When the report was published |
| `timestamps` | — | — | — | created_at, updated_at |

Foreign keys:
- `subject_user_id` → `users.id` cascade on delete
- `reporter_user_id` → `users.id` cascade on delete
- `publisher_user_id` → `users.id` set null on delete

Indexes:
- `subject_user_id` (implicit from FK)
- `['subject_user_id', 'status', 'published_at']` (composite for risk score queries)

---

## Implementation Steps (execute in this exact order)

---

### Step 1: Enums

**Files to create:**

#### `app/Enums/ReportLocation.php` — Create
```php
<?php

namespace App\Enums;

enum ReportLocation: string
{
    case Minecraft = 'minecraft';
    case DiscordText = 'discord_text';
    case DiscordVoice = 'discord_voice';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Minecraft => 'Minecraft',
            self::DiscordText => 'Discord Text',
            self::DiscordVoice => 'Discord Voice',
            self::Other => 'Other',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Minecraft => 'green',
            self::DiscordText => 'indigo',
            self::DiscordVoice => 'purple',
            self::Other => 'zinc',
        };
    }
}
```

#### `app/Enums/ReportSeverity.php` — Create
```php
<?php

namespace App\Enums;

enum ReportSeverity: string
{
    case Trivial = 'trivial';
    case Minor = 'minor';
    case Moderate = 'moderate';
    case Major = 'major';
    case Severe = 'severe';

    public function label(): string
    {
        return match ($this) {
            self::Trivial => 'Trivial',
            self::Minor => 'Minor',
            self::Moderate => 'Moderate',
            self::Major => 'Major',
            self::Severe => 'Severe',
        };
    }

    public function points(): int
    {
        return match ($this) {
            self::Trivial => 1,
            self::Minor => 2,
            self::Moderate => 4,
            self::Major => 7,
            self::Severe => 10,
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Trivial => 'zinc',
            self::Minor => 'blue',
            self::Moderate => 'yellow',
            self::Major => 'orange',
            self::Severe => 'red',
        };
    }
}
```

#### `app/Enums/ReportStatus.php` — Create
```php
<?php

namespace App\Enums;

enum ReportStatus: string
{
    case Draft = 'draft';
    case Published = 'published';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Published => 'Published',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'amber',
            self::Published => 'green',
        };
    }
}
```

---

### Step 2: Migration

**File**: `database/migrations/YYYY_MM_DD_HHMMSS_create_discipline_reports_table.php` — Create

```php
Schema::create('discipline_reports', function (Blueprint $table) {
    $table->id();
    $table->foreignId('subject_user_id')->constrained('users')->cascadeOnDelete();
    $table->foreignId('reporter_user_id')->constrained('users')->cascadeOnDelete();
    $table->foreignId('publisher_user_id')->nullable()->constrained('users')->nullOnDelete();
    $table->text('description');
    $table->string('location');
    $table->text('witnesses')->nullable();
    $table->text('actions_taken');
    $table->string('severity');
    $table->string('status')->default('draft');
    $table->timestamp('published_at')->nullable();
    $table->timestamps();

    $table->index(['subject_user_id', 'status', 'published_at']);
});
```

Rollback:
```php
Schema::dropIfExists('discipline_reports');
```

---

### Step 3: Model

**File**: `app/Models/DisciplineReport.php` — Create

```php
<?php

namespace App\Models;

use App\Enums\ReportLocation;
use App\Enums\ReportSeverity;
use App\Enums\ReportStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class DisciplineReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'subject_user_id',
        'reporter_user_id',
        'publisher_user_id',
        'description',
        'location',
        'witnesses',
        'actions_taken',
        'severity',
        'status',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'location' => ReportLocation::class,
            'severity' => ReportSeverity::class,
            'status' => ReportStatus::class,
            'published_at' => 'datetime',
        ];
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(User::class, 'subject_user_id');
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_user_id');
    }

    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'publisher_user_id');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', ReportStatus::Published);
    }

    public function scopeDraft(Builder $query): Builder
    {
        return $query->where('status', ReportStatus::Draft);
    }

    public function scopeForSubject(Builder $query, User $user): Builder
    {
        return $query->where('subject_user_id', $user->id);
    }

    public function isDraft(): bool
    {
        return $this->status === ReportStatus::Draft;
    }

    public function isPublished(): bool
    {
        return $this->status === ReportStatus::Published;
    }
}
```

---

### Step 4: Factory

**File**: `database/factories/DisciplineReportFactory.php` — Create

```php
<?php

namespace Database\Factories;

use App\Enums\ReportLocation;
use App\Enums\ReportSeverity;
use App\Enums\ReportStatus;
use App\Models\DisciplineReport;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class DisciplineReportFactory extends Factory
{
    protected $model = DisciplineReport::class;

    public function definition(): array
    {
        return [
            'subject_user_id' => User::factory(),
            'reporter_user_id' => User::factory(),
            'publisher_user_id' => null,
            'description' => $this->faker->paragraph(),
            'location' => $this->faker->randomElement(ReportLocation::cases()),
            'witnesses' => $this->faker->optional()->sentence(),
            'actions_taken' => $this->faker->sentence(),
            'severity' => ReportSeverity::Minor,
            'status' => ReportStatus::Draft,
            'published_at' => null,
        ];
    }

    public function published(): static
    {
        return $this->state(fn () => [
            'status' => ReportStatus::Published,
            'publisher_user_id' => User::factory(),
            'published_at' => now(),
        ]);
    }

    public function trivial(): static
    {
        return $this->state(fn () => ['severity' => ReportSeverity::Trivial]);
    }

    public function minor(): static
    {
        return $this->state(fn () => ['severity' => ReportSeverity::Minor]);
    }

    public function moderate(): static
    {
        return $this->state(fn () => ['severity' => ReportSeverity::Moderate]);
    }

    public function major(): static
    {
        return $this->state(fn () => ['severity' => ReportSeverity::Major]);
    }

    public function severe(): static
    {
        return $this->state(fn () => ['severity' => ReportSeverity::Severe]);
    }

    public function forSubject(User $user): static
    {
        return $this->state(fn () => ['subject_user_id' => $user->id]);
    }

    public function byReporter(User $user): static
    {
        return $this->state(fn () => ['reporter_user_id' => $user->id]);
    }

    public function publishedDaysAgo(int $days): static
    {
        return $this->state(fn () => [
            'status' => ReportStatus::Published,
            'publisher_user_id' => User::factory(),
            'published_at' => now()->subDays($days),
        ]);
    }
}
```

---

### Step 5: User Model Changes

**File**: `app/Models/User.php` — Modify

Add relationship:
```php
use App\Models\DisciplineReport;

public function disciplineReports(): HasMany
{
    return $this->hasMany(DisciplineReport::class, 'subject_user_id');
}
```

Add risk score method (cached daily, cache busted on publish):
```php
/**
 * Calculate discipline risk score over 7/30/90-day windows.
 * Cached for 24 hours; cache is busted when a report is published for this user.
 * Returns ['7d' => int, '30d' => int, '90d' => int, 'total' => int]
 */
public function disciplineRiskScore(): array
{
    return Cache::remember("user.{$this->id}.discipline_risk_score", now()->addDay(), function () {
        $reports = $this->disciplineReports()
            ->published()
            ->where('published_at', '>=', now()->subDays(90))
            ->get(['severity', 'published_at']);

        $score7 = 0;
        $score30 = 0;
        $score90 = 0;

        foreach ($reports as $report) {
            $points = $report->severity->points();
            $score90 += $points;

            if ($report->published_at >= now()->subDays(30)) {
                $score30 += $points;
            }
            if ($report->published_at >= now()->subDays(7)) {
                $score7 += $points;
            }
        }

        return [
            '7d' => $score7,
            '30d' => $score30,
            '90d' => $score90,
            'total' => $score7 + $score30 + $score90,
        ];
    });
}

/**
 * Clear the cached discipline risk score.
 */
public function clearDisciplineRiskScoreCache(): void
{
    Cache::forget("user.{$this->id}.discipline_risk_score");
}

/**
 * Get the color for a risk score total.
 */
public static function riskScoreColor(int $total): string
{
    return match (true) {
        $total >= 51 => 'red',
        $total >= 26 => 'orange',
        $total >= 11 => 'yellow',
        $total >= 1 => 'green',
        default => 'zinc',
    };
}
```

---

### Step 6: Policy

**File**: `app/Policies/DisciplineReportPolicy.php` — Create

```php
<?php

namespace App\Policies;

use App\Enums\ReportStatus;
use App\Enums\StaffDepartment;
use App\Enums\StaffRank;
use App\Models\DisciplineReport;
use App\Models\User;

class DisciplineReportPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        if ($user->hasRole('Admin')) {
            return true;
        }

        if ($user->isAtLeastRank(StaffRank::Officer) && $user->isInDepartment(StaffDepartment::Command)) {
            return true;
        }

        return null;
    }

    public function viewAny(User $user): bool
    {
        return $user->isAtLeastRank(StaffRank::JrCrew);
    }

    public function view(User $user, DisciplineReport $report): bool
    {
        // Staff can view any report
        if ($user->isAtLeastRank(StaffRank::JrCrew)) {
            return true;
        }

        // Subject user can view their own published reports
        if ($user->id === $report->subject_user_id && $report->isPublished()) {
            return true;
        }

        // Parent of subject can view published reports
        if ($report->isPublished() && $user->children()->where('child_user_id', $report->subject_user_id)->exists()) {
            return true;
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $user->isAtLeastRank(StaffRank::JrCrew);
    }

    public function update(User $user, DisciplineReport $report): bool
    {
        if (! $report->isDraft()) {
            return false;
        }

        return $user->id === $report->reporter_user_id
            || $user->isAtLeastRank(StaffRank::Officer);
    }

    public function publish(User $user, DisciplineReport $report): bool
    {
        return $report->isDraft() && $user->isAtLeastRank(StaffRank::Officer);
    }

    public function delete(User $user, DisciplineReport $report): bool
    {
        return false;
    }
}
```

---

### Step 7: Gates

**File**: `app/Providers/AuthServiceProvider.php` — Modify

Add to `$policies` array:
```php
\App\Models\DisciplineReport::class => \App\Policies\DisciplineReportPolicy::class,
```

Add gates inside `boot()` (after existing `$canViewLogs` variable):
```php
Gate::define('manage-discipline-reports', function ($user) {
    return $user->hasRole('Admin') || $user->isAtLeastRank(StaffRank::JrCrew);
});

Gate::define('publish-discipline-reports', function ($user) {
    return $user->hasRole('Admin') || $user->isAtLeastRank(StaffRank::Officer);
});

Gate::define('view-discipline-report-log', $canViewLogs);
```

Also update `hasLogsTabs()` in the ACP tabs component (see Step 13).

---

### Step 8: Actions

#### `app/Actions/CreateDisciplineReport.php` — Create

```php
<?php

namespace App\Actions;

use App\Enums\ReportLocation;
use App\Enums\ReportSeverity;
use App\Enums\ReportStatus;
use App\Enums\StaffRank;
use App\Models\DisciplineReport;
use App\Models\User;
use App\Notifications\DisciplineReportPendingReviewNotification;
use App\Services\TicketNotificationService;
use Lorisleiva\Actions\Concerns\AsAction;

class CreateDisciplineReport
{
    use AsAction;

    public function handle(
        User $subject,
        User $reporter,
        string $description,
        ReportLocation $location,
        string $actionsTaken,
        ReportSeverity $severity,
        ?string $witnesses = null,
    ): DisciplineReport {
        $report = DisciplineReport::create([
            'subject_user_id' => $subject->id,
            'reporter_user_id' => $reporter->id,
            'description' => $description,
            'location' => $location,
            'witnesses' => $witnesses,
            'actions_taken' => $actionsTaken,
            'severity' => $severity,
            'status' => ReportStatus::Draft,
        ]);

        RecordActivity::run($subject, 'discipline_report_created',
            "Discipline report created by {$reporter->name}. Severity: {$severity->label()}.");

        // If reporter is not an Officer, notify Quartermaster dept for review
        if (! $reporter->isAtLeastRank(StaffRank::Officer)) {
            $this->notifyQuartermasterDepartment($report);
        }

        return $report;
    }

    private function notifyQuartermasterDepartment(DisciplineReport $report): void
    {
        $qmStaff = User::where('staff_department', \App\Enums\StaffDepartment::Quartermaster)
            ->where('staff_rank', '!=', \App\Enums\StaffRank::None)
            ->where('id', '!=', $report->reporter_user_id) // Don't notify the reporter
            ->get();

        $notificationService = app(TicketNotificationService::class);
        $notification = new DisciplineReportPendingReviewNotification($report);

        foreach ($qmStaff as $staffMember) {
            $notificationService->send($staffMember, $notification, 'staff_alerts');
        }
    }
}
```

#### `app/Actions/UpdateDisciplineReport.php` — Create

```php
<?php

namespace App\Actions;

use App\Enums\ReportLocation;
use App\Enums\ReportSeverity;
use App\Models\DisciplineReport;
use App\Models\User;
use Lorisleiva\Actions\Concerns\AsAction;

class UpdateDisciplineReport
{
    use AsAction;

    public function handle(
        DisciplineReport $report,
        User $editor,
        string $description,
        ReportLocation $location,
        string $actionsTaken,
        ReportSeverity $severity,
        ?string $witnesses = null,
    ): DisciplineReport {
        $report->update([
            'description' => $description,
            'location' => $location,
            'witnesses' => $witnesses,
            'actions_taken' => $actionsTaken,
            'severity' => $severity,
        ]);

        RecordActivity::run($report->subject, 'discipline_report_updated',
            "Discipline report #{$report->id} updated by {$editor->name}.");

        return $report->fresh();
    }
}
```

#### `app/Actions/PublishDisciplineReport.php` — Create

```php
<?php

namespace App\Actions;

use App\Models\DisciplineReport;
use App\Models\User;
use App\Notifications\DisciplineReportPublishedNotification;
use App\Services\TicketNotificationService;
use Lorisleiva\Actions\Concerns\AsAction;

class PublishDisciplineReport
{
    use AsAction;

    public function handle(DisciplineReport $report, User $publisher): DisciplineReport
    {
        $report->update([
            'status' => \App\Enums\ReportStatus::Published,
            'publisher_user_id' => $publisher->id,
            'published_at' => now(),
        ]);

        // Bust the cached risk score for the subject user
        $report->subject->clearDisciplineRiskScoreCache();

        RecordActivity::run($report->subject, 'discipline_report_published',
            "Discipline report #{$report->id} published by {$publisher->name}. Severity: {$report->severity->label()}.");

        $this->notifySubjectAndParents($report);

        return $report->fresh();
    }

    private function notifySubjectAndParents(DisciplineReport $report): void
    {
        $notificationService = app(TicketNotificationService::class);
        $notification = new DisciplineReportPublishedNotification($report);

        // Notify the subject user
        $notificationService->send($report->subject, $notification, 'account');

        // Notify parent accounts
        foreach ($report->subject->parents as $parent) {
            $notificationService->send($parent, $notification, 'account');
        }
    }
}
```

---

### Step 9: Notifications

#### `app/Notifications/DisciplineReportPendingReviewNotification.php` — Create

Sent to Quartermaster dept when a non-officer creates a report.

```php
<?php

namespace App\Notifications;

use App\Models\DisciplineReport;
use App\Notifications\Channels\PushoverChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DisciplineReportPendingReviewNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected array $allowedChannels = ['mail'];
    protected ?string $pushoverKey = null;

    public function __construct(public DisciplineReport $report) {}

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
        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Discipline Report Pending Review')
            ->line("A new discipline report has been submitted by {$this->report->reporter->name} and needs review.")
            ->line("**Subject:** {$this->report->subject->name}")
            ->line("**Severity:** {$this->report->severity->label()}")
            ->line("**Location:** {$this->report->location->label()}")
            ->action('View Report', route('profile.show', $this->report->subject));
    }

    public function toPushover(object $notifiable): array
    {
        return [
            'title' => 'Discipline Report Needs Review',
            'message' => "New {$this->report->severity->label()} report about {$this->report->subject->name} by {$this->report->reporter->name}.",
            'url' => route('profile.show', $this->report->subject),
        ];
    }
}
```

#### `app/Notifications/DisciplineReportPublishedNotification.php` — Create

Sent to subject user and their parents when a report is published.

```php
<?php

namespace App\Notifications;

use App\Models\DisciplineReport;
use App\Notifications\Channels\PushoverChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DisciplineReportPublishedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected array $allowedChannels = ['mail'];
    protected ?string $pushoverKey = null;

    public function __construct(public DisciplineReport $report) {}

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
        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Discipline Report Filed')
            ->line('A discipline report has been filed.')
            ->line("**Severity:** {$this->report->severity->label()}")
            ->line("**Location:** {$this->report->location->label()}")
            ->line("**Description:** {$this->report->description}")
            ->action('View Profile', route('profile.show', $this->report->subject));
    }

    public function toPushover(object $notifiable): array
    {
        return [
            'title' => 'Discipline Report Filed',
            'message' => "A {$this->report->severity->label()} discipline report has been filed. Location: {$this->report->location->label()}.",
            'url' => route('profile.show', $this->report->subject),
        ];
    }
}
```

---

### Step 10: Profile — Discipline Reports Card Component

**File**: `resources/views/livewire/users/discipline-reports-card.blade.php` — Create

Livewire Volt component that:
- Accepts `User $user` prop
- Shows risk score badge (total) with tooltip showing 7d/30d/90d breakdown
- Lists reports in a table (published only for non-staff; all for staff, with status badges)
- "Create Report" button visible to staff only → opens create modal
- Create modal with form: description (textarea), location (select), witnesses (input), actions_taken (textarea), severity (radio group)
- View report modal (click a row → see full details)
- Edit button on draft reports (for creator or Officers) → opens edit modal
- Publish button on draft reports (Officers only)

**Key authorization in component class:**
```php
// Visibility: staff, the user themselves, or parent of the user
public function mount(User $user): void
{
    $authUser = Auth::user();
    $isStaff = $authUser->isAtLeastRank(StaffRank::JrCrew) || $authUser->hasRole('Admin');
    $isSelf = $authUser->id === $user->id;
    $isParent = $authUser->children()->where('child_user_id', $user->id)->exists();

    if (! $isStaff && ! $isSelf && ! $isParent) {
        abort(403);
    }

    $this->user = $user;
    $this->isStaffViewing = $isStaff;
}
```

**Key methods:**
- `createReport()` — validates form, calls `CreateDisciplineReport::run()`, closes modal, toasts success
- `updateReport()` — validates form, calls `UpdateDisciplineReport::run()`, closes modal, toasts success
- `publishReport(int $reportId)` — calls `PublishDisciplineReport::run()`, toasts success
- `viewReport(int $reportId)` — loads report into view modal
- `editReport(int $reportId)` — loads report into edit modal

**Risk score display:**
```blade
@php $riskScore = $user->disciplineRiskScore(); @endphp
@if($riskScore['total'] > 0)
    <flux:badge color="{{ \App\Models\User::riskScoreColor($riskScore['total']) }}" size="sm"
        x-data x-tooltip.raw="7d: {{ $riskScore['7d'] }} | 30d: {{ $riskScore['30d'] }} | 90d: {{ $riskScore['90d'] }}">
        Risk: {{ $riskScore['total'] }}
    </flux:badge>
@endif
```

---

### Step 11: Profile Page — Add Component

**File**: `resources/views/users/show.blade.php` — Modify

Add after the `<livewire:users.display-basic-details>` div, before the activity log button:

```blade
<div class="my-6">
    <livewire:users.discipline-reports-card :user="$user" lazy />
</div>
```

The component itself handles its own visibility check in `mount()` (staff, self, or parent).

---

### Step 12: Dashboard — Discipline Reports Widget

**File**: `resources/views/livewire/dashboard/discipline-reports-widget.blade.php` — Create

Livewire Volt component that shows:
- Count of reports pending review (draft status)
- 5 most recent reports (all statuses) with subject name, severity badge, status badge, date
- Top 5 users by risk score (total > 0)
- "View All" link to ACP reports log tab

Authorization: only visible via gate `manage-discipline-reports` (staff JrCrew+).

---

### Step 13: Dashboard Page — Add Widget

**File**: `resources/views/dashboard.blade.php` — Modify

Inside the Quartermaster section grid (line ~110-118), add:

```blade
@can('manage-discipline-reports')
    <livewire:dashboard.discipline-reports-widget />
@endcan
```

---

### Step 14: ACP — Reports Log Tab Component

**File**: `resources/views/livewire/admin-manage-discipline-reports-page.blade.php` — Create

Livewire Volt component with:
- `WithPagination` trait
- Filters: status (all/draft/published), severity (all/each level), date range
- Sorting: subject name, reporter name, severity, status, created_at, published_at
- Table columns: Subject, Reporter, Location, Severity (badge), Status (badge), Created, Published, Actions
- Click row or "View" to open detail modal
- "Publish" button on draft rows (Officers only)
- 15 items per page with pagination

Follows exact pattern from `admin-manage-users-page.blade.php`.

---

### Step 15: ACP Tabs — Add Reports Log Tab

**File**: `resources/views/livewire/admin-control-panel-tabs.blade.php` — Modify

**In PHP class — update `hasLogsTabs()`:**
```php
public function hasLogsTabs(): bool
{
    $user = auth()->user();

    return $user && (
        $user->can('view-mc-command-log')
        || $user->can('view-activity-log')
        || $user->can('view-discipline-report-log')
    );
}
```

**In PHP class — update `defaultTabFor()` logs case:**
```php
'logs' => match (true) {
    $user?->can('view-mc-command-log') => 'mc-command-log',
    $user?->can('view-activity-log') => 'activity-log',
    $user?->can('view-discipline-report-log') => 'discipline-report-log',
    default => 'mc-command-log',
},
```

**In Blade — inside the logs category tabs (after Activity Log tab):**
```blade
@can('view-discipline-report-log')
    <flux:tab name="discipline-report-log">Reports Log</flux:tab>
@endcan
```

**In Blade — inside the logs category tab panels (after Activity Log panel):**
```blade
<flux:tab.panel name="discipline-report-log">
    @can('view-discipline-report-log')
        <livewire:admin-manage-discipline-reports-page />
    @endcan
</flux:tab.panel>
```

---

### Step 16: Parent Portal — Add Reports Section

**File**: `resources/views/livewire/parent-portal/index.blade.php` — Modify

**In PHP class — add batch loading for reports:**

Add a computed property or load in mount alongside existing ticket batch-loading:
```php
#[Computed]
public function childReports(): array
{
    $childIds = $this->getTargetUser()->children()->pluck('child_user_id');

    if ($childIds->isEmpty()) {
        return [];
    }

    return \App\Models\DisciplineReport::whereIn('subject_user_id', $childIds)
        ->published()
        ->latest('published_at')
        ->get()
        ->groupBy('subject_user_id')
        ->toArray();
}
```

Add method to view report details:
```php
public ?int $viewingReportId = null;

public function viewReport(int $reportId): void
{
    $report = \App\Models\DisciplineReport::findOrFail($reportId);

    // Verify the subject is a child of the target user
    $childIds = $this->getTargetUser()->children()->pluck('child_user_id');
    abort_unless($childIds->contains($report->subject_user_id) && $report->isPublished(), 403);

    $this->viewingReportId = $reportId;
    Flux::modal('view-discipline-report-modal')->show();
}
```

**In Blade — add inside each child card, after the "Recent Tickets" section:**

```blade
{{-- Discipline Reports --}}
@php
    $childReports = \App\Models\DisciplineReport::where('subject_user_id', $child->id)
        ->published()
        ->latest('published_at')
        ->limit(10)
        ->get();
@endphp
@if($childReports->isNotEmpty())
    <div class="mt-4">
        <flux:heading size="sm">Discipline Reports</flux:heading>
        <flux:separator variant="subtle" class="my-2" />
        <div class="space-y-2">
            @foreach($childReports as $report)
                <div class="flex items-center justify-between cursor-pointer hover:bg-zinc-800 rounded p-2"
                     wire:click="viewReport({{ $report->id }})">
                    <div>
                        <flux:text>{{ $report->description }}</flux:text>
                        <flux:text variant="subtle" class="text-xs">
                            {{ $report->published_at->format('M j, Y') }}
                        </flux:text>
                    </div>
                    <flux:badge color="{{ $report->severity->color() }}" size="sm">
                        {{ $report->severity->label() }}
                    </flux:badge>
                </div>
            @endforeach
        </div>
    </div>
@endif
```

**Add view-report modal at the bottom of the component** (outside the child loop):
```blade
<flux:modal name="view-discipline-report-modal" class="w-full md:w-1/2">
    @if($viewingReportId)
        @php $viewReport = \App\Models\DisciplineReport::find($viewingReportId); @endphp
        @if($viewReport)
            <div class="space-y-4">
                <flux:heading>Discipline Report</flux:heading>
                <div>
                    <flux:text class="font-medium">Description</flux:text>
                    <flux:text>{{ $viewReport->description }}</flux:text>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <flux:text class="font-medium">Location</flux:text>
                        <flux:badge color="{{ $viewReport->location->color() }}">{{ $viewReport->location->label() }}</flux:badge>
                    </div>
                    <div>
                        <flux:text class="font-medium">Severity</flux:text>
                        <flux:badge color="{{ $viewReport->severity->color() }}">{{ $viewReport->severity->label() }}</flux:badge>
                    </div>
                </div>
                @if($viewReport->witnesses)
                    <div>
                        <flux:text class="font-medium">Witnesses</flux:text>
                        <flux:text>{{ $viewReport->witnesses }}</flux:text>
                    </div>
                @endif
                <div>
                    <flux:text class="font-medium">Actions Taken</flux:text>
                    <flux:text>{{ $viewReport->actions_taken }}</flux:text>
                </div>
                <div>
                    <flux:text class="font-medium">Date</flux:text>
                    <flux:text>{{ $viewReport->published_at->format('M j, Y g:i A') }}</flux:text>
                </div>
            </div>
        @endif
    @endif
</flux:modal>
```

---

### Step 17: Tests — Actions

**File**: `tests/Feature/Actions/DisciplineReports/CreateDisciplineReportTest.php` — Create

Test cases:
- `it('creates a draft discipline report')`
- `it('records activity when report is created')`
- `it('notifies quartermaster department when non-officer creates report')`
- `it('does not notify the reporter even if they are in the quartermaster department')`
- `it('does not notify quartermaster when officer creates report')`
- `it('creates report with null witnesses when not provided')`

**File**: `tests/Feature/Actions/DisciplineReports/UpdateDisciplineReportTest.php` — Create

Test cases:
- `it('updates a draft discipline report')`
- `it('records activity when report is updated')`

**File**: `tests/Feature/Actions/DisciplineReports/PublishDisciplineReportTest.php` — Create

Test cases:
- `it('publishes a draft report')`
- `it('sets publisher and published_at on publish')`
- `it('records activity when report is published')`
- `it('notifies subject user when report is published')`
- `it('notifies parent accounts when report is published')`
- `it('clears the subject risk score cache when report is published')`

---

### Step 18: Tests — Policy

**File**: `tests/Feature/Policies/DisciplineReportPolicyTest.php` — Create

Test cases:
- `it('allows admin to perform any action')`
- `it('allows command officer to perform any action')`
- `it('allows jr crew to view any reports')`
- `it('allows jr crew to create reports')`
- `it('allows report creator to update their draft report')`
- `it('allows officer to update any draft report')`
- `it('prevents updating a published report')`
- `it('allows officer to publish a draft report')`
- `it('prevents non-officer from publishing')`
- `it('allows subject user to view their published report')`
- `it('prevents subject user from viewing draft reports')`
- `it('allows parent to view published reports about their child')`
- `it('prevents parent from viewing draft reports about their child')`
- `it('prevents non-staff non-subject from viewing reports')`
- `it('prevents deletion of reports')`

---

### Step 19: Tests — Risk Score

**File**: `tests/Feature/Models/UserDisciplineRiskScoreTest.php` — Create

Test cases:
- `it('returns zero scores when user has no reports')`
- `it('calculates 7-day score from published reports only')`
- `it('calculates 30-day score correctly')`
- `it('calculates 90-day score correctly')`
- `it('triple counts recent reports in total (7d + 30d + 90d)')`
- `it('excludes draft reports from risk score')`
- `it('excludes reports older than 90 days')`
- `it('returns correct color for each threshold')`
- `it('caches risk score for 24 hours')`
- `it('clears cached risk score when clearDisciplineRiskScoreCache is called')`

---

### Step 20: Tests — Livewire Components

**File**: `tests/Feature/Livewire/DisciplineReportsCardTest.php` — Create

Test cases:
- `it('shows discipline reports card to staff on profile page')`
- `it('shows discipline reports card to the subject user')`
- `it('shows discipline reports card to parent of subject')`
- `it('hides discipline reports card from unrelated users')`
- `it('shows only published reports to non-staff users')`
- `it('shows all reports including drafts to staff')`
- `it('allows staff to create a report via modal')`
- `it('allows officer to publish a draft report')`
- `it('prevents non-officer from publishing')`
- `it('allows creator to edit their draft report')`
- `it('prevents editing of published reports')`
- `it('shows risk score badge with correct color')`

---

## Edge Cases

1. **Deleted user cascade**: Reports are deleted when subject or reporter is deleted (cascade FK). Publisher is set to null (nullOnDelete).
2. **Self-reports**: No restriction on staff reporting themselves, but unlikely in practice.
3. **Risk score with no reports**: Returns all zeros, no badge displayed.
4. **Parent viewing reports**: Only sees published reports for their linked children, not for other users.
5. **Race condition on publish**: Only Officers can publish; policy check prevents double-publish since `isDraft()` check will fail on second attempt.
6. **Notification deduplication**: If a Quartermaster is also an Officer, the pending review notification still goes to them (they can then self-publish).

## Known Risks

1. **Risk score performance**: The `disciplineRiskScore()` method queries the DB each time. For the dashboard widget showing top users, this could be N+1. Mitigate by collecting all reports in a single query in the widget, not by calling the User method.
2. **ACP reports table**: For a large number of reports, pagination handles this. No caching needed initially.

## Definition of Done

- [ ] `php artisan migrate:fresh` passes
- [ ] `./vendor/bin/pest` passes with zero failures
- [ ] All test cases from this plan are implemented
- [ ] No ad-hoc auth checks in Blade templates (all through policy/gates)
- [ ] Risk score displays correctly with tooltip on profile
- [ ] Staff can create, edit (draft), and publish reports
- [ ] Non-staff subject users see only published reports
- [ ] Parents see published reports in parent portal
- [ ] Dashboard widget shows recent reports and top risk users
- [ ] ACP Reports Log tab is accessible and functional
- [ ] Notifications sent correctly on create (to QM) and publish (to subject + parents)
