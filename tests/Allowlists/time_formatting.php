<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| UI Time Formatting Allowlist (for tests/Feature/Ui/UiTimeFormattingTest.php)
|--------------------------------------------------------------------------
|
| Purpose
| - Let intentional exceptions to the time-formatting test pass when they are
|   necessary (e.g., legacy views, special countdown widgets), while keeping
|   the default policy strict: use <time datetime="...toIso8601String()"> with
|   a server-rendered fallback and rely on resources/js/timescript.js.
|
| Ownership & Governance
| - Owners: Web Platform Team (set an explicit contact/owner per entry below)
| - Review cadence: Monthly. Each entry should include added/review_on dates
|   and a short reason to avoid allowlist drift.
| - Removal playbook: Prefer migrating to <time datetime> + fallback text and
|   the shared timescript.js. Avoid inline time-formatting scripts.
|
| How it works
| - This file returns a PHP array mapping repo-relative file paths to rules.
| - The test reads this file and applies rules per file before flagging a
|   time-formatting offender.
|
| Keys (per file):
| - 'all' => true
|     Skip ALL time-formatting enforcement in this file. Use sparingly,
|     typically for legacy or deprecating views.
|
| - 'skip' => [ 'logical_key_1', 'logical_key_2', ... ]
|     Skip specific logical test blocks identified by keys (see UiTimeFormattingTest
|     for the exact keys used, e.g., 'dashboard.blogs_widget'). Use narrow, temporary entries.
|
| - 'inline_all' => true
|     Allow ALL inline time-formatting scripts in this file. Prefer using
|     'inline_contains' when possible to keep scope tight.
|
| - 'inline_contains' => [ 'substring1', 'substring2', ... ]
|     If ANY provided substring is found within the file content, inline
|     time-formatting scripts are allowed. Useful to gate exceptions behind a
|     specific marker or known pattern.
|
| - 'snippet_contains' => [ 'substring1', 'substring2', ... ]
|     If ANY provided substring is found within the nearby snippet where a
|     toIso usage is detected, the enforcement for that occurrence is skipped.
|     Useful for special cases like countdowns (e.g., data-countdown).
|
| Optional metadata (ignored by the test, for humans):
| - 'owner' => 'Team/Name'
| - 'reason' => 'Explain why this exception is needed'
| - 'ticket' => 'ticket_id'
| - 'added' => 'YYY-MM-DD'
| - 'review_on' => 'YYY-MM-DD'
|
| Paths
| - Use the relative path exactly as the test reports in a failure message,
|   e.g.: 'resources/views/livewire/example.blade.php'
|
| Quick examples
| return [
|     // 1) Skip specific logical blocks by key
|     'resources/views/livewire/example/special.blade.php' => [
|         'skip' => [
|             'dashboard.blogs_widget',
|         ],
|     ],
|
|     // 2) Allow inline time-formatting only when a marker is present
|     'resources/views/livewire/example/special.blade.php' => [
|         'inline_contains' => [
|             'ALLOW_INLINE_TIME_FOR_EXPERIMENT',
|         ],
|     ],
|
|     // 3) Skip enforcement for snippets containing a countdown data attr
|     'resources/views/livewire/example/countdown.blade.php' => [
|         'snippet_contains' => [
|             'data-countdown',
|             'data-created-at',
|         ],
|     ],
|
|     // 4) Skip everything in a legacy/temporary file
|     'resources/views/partials/legacy.blade.php' => [
|         'all' => true,
|     ],
| ];
|
| Inline opt-out (preferred for one-offs)
| - Not supported by this test to avoid masking markup issues.
| - Use a tight allowlist entry above instead of per-tag opt-outs.
|
| Already auto-allowed by the test
| - Blade-commented code is ignored.
| - data-* attribute references near toIso are ignored to reduce false flags.
|
| Tips
| - Copy the reported path from a failing test and paste it as the array key.
| - Prefer inline_contains/snippet_contains over 'all' to keep scope tight.
| - This file is PHP, not JSON—watch quotes and trailing commas.
*/

return [
    // Admin grids: temporarily allow direct format usage until refactor
    'resources/views/livewire/admin-manage-pages-page.blade.php' => [
        'snippet_contains' => ['<flux:table', 'wire:click="sort('],
        'owner' => 'Web Platform',
        'reason' => 'Admin grid formatting to be migrated to <time>',
        'added' => '2025-08-20',
        'review_on' => '2025-09-20',
    ],
    'resources/views/livewire/admin-manage-roles-page.blade.php' => [
        'snippet_contains' => ['<flux:table', 'wire:click="sort('],
        'owner' => 'Web Platform',
        'reason' => 'Admin grid formatting to be migrated to <time>',
        'added' => '2025-08-20',
        'review_on' => '2025-09-20',
    ],
    'resources/views/livewire/admin-manage-users-page.blade.php' => [
        'snippet_contains' => ['<flux:table', 'wire:click="sort('],
        'owner' => 'Web Platform',
        'reason' => 'Admin grid formatting to be migrated to <time>',
        'added' => '2025-08-20',
        'review_on' => '2025-09-20',
    ],
    // Non target domains (meetings/users) temporarily allowed
    'resources/views/livewire/meeting/create-modal.blade.php' => [
        'snippet_contains' => ['Rule::date()->format('],
        'owner' => 'Web Platform',
        'reason' => 'Validation rule format string; not a <time> display',
        'added' => '2025-08-20',
        'review_on' => '2025-09-20',
    ],
    'resources/views/livewire/meetings/list.blade.php' => [
        'snippet_contains' => ['scheduled_time->setTimezone'],
        'owner' => 'Web Platform',
        'reason' => 'Meeting times to be migrated to <time>',
        'added' => '2025-08-20',
        'review_on' => '2025-09-20',
    ],
    'resources/views/livewire/users/display-basic-details.blade.php' => [
        'snippet_contains' => ['Joined on', 'created_at->format('],
        'owner' => 'Web Platform',
        'reason' => 'User profile dates to be migrated to <time>',
        'added' => '2025-08-20',
        'review_on' => '2025-09-20',
    ],
    // Dashboard widgets and meetings: temporarily allow direct formats
    'resources/views/livewire/dashboard/stowaway-users-widget.blade.php' => [
        'snippet_contains' => [
            '$selectedUser->created_at->format(',
            '$selectedUser->rules_accepted_at->format(',
        ],
        'owner' => 'Web Platform',
        'reason' => 'Widget dates to be migrated to <time> with toIso',
        'added' => '2025-08-20',
        'review_on' => '2025-09-20',
    ],
    'resources/views/livewire/meeting/notes-display.blade.php' => [
        'snippet_contains' => [
            'scheduled_time?->format(',
        ],
        'owner' => 'Web Platform',
        'reason' => 'Meeting notes date to be migrated to <time> with toIso',
        'added' => '2025-08-20',
        'review_on' => '2025-09-20',
    ],
    'resources/views/livewire/meetings/manage-meeting.blade.php' => [
        'snippet_contains' => [
            "now()->format('Y-m-d')",
            'scheduled_time->setTimezone',
            'start_time->setTimezone',
            'pivot->added_at->setTimezone',
        ],
        'owner' => 'Web Platform',
        'reason' => 'Manage meeting times to be migrated to <time> with toIso',
        'added' => '2025-08-20',
        'review_on' => '2025-09-20',
    ],
    // Prayer widget: temporarily allow direct format usage for current year
    'resources/views/livewire/prayer/prayer-widget.blade.php' => [
        'snippet_contains' => [
            "now()->format('Y')",
        ],
        'owner' => 'Web Platform',
        'reason' => 'Current year display not critical for <time> markup, will migrate if needed.',
        'added' => '2025-08-24',
        'review_on' => '2025-09-24',
    ],
    // Add allowlist entries here as needed. Keep scope tight and temporary.
];
