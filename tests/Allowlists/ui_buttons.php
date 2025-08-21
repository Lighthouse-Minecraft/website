<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| UI Buttons Allowlist (for tests/Feature/Ui/UiButtonsTest.php)
|--------------------------------------------------------------------------
|
| Purpose
| - Let intentional exceptions to the UI Buttons test pass when necessary
|   (e.g., in-flight widgets or temporary UI gaps), while keeping the
|   default policy strict: assertions in UiButtonsTest should pass without
|   skips.
|
| Ownership & Governance
| - Owners: Web Platform Team (set an explicit contact/owner per entry below)
| - Review cadence: Monthly. Each entry should include added/review_on dates
|   and a short reason to avoid allowlist drift.
| - Removal playbook: Implement/fix the missing UI and remove the allowlist
|   entries. Prefer enabling assertions instead of long-lived skips.
|
| How it works
| - This file returns a PHP array mapping repo-relative TEST file paths to
|   rules. The test reads this file and applies rules per test file before
|   running an assertion block.
|
| Keys (per test path):
| - 'skip' => [ 'logical_key_1', 'logical_key_2', ... ]
|     Skip specific logical test blocks identified by keys (see UiButtonsTest
|     for the exact keys used, e.g., 'dashboard.blogs_widget'). Use narrow,
|     temporary entries.
|
| Optional metadata (ignored by the test, for humans):
| - 'owner' => 'Team/Name'
| - 'reason' => 'Explain why this exception is needed'
| - 'ticket' => 'ticket_id'
| - 'added' => 'YYY-MM-DD'
| - 'review_on' => 'YYY-MM-DD'
|
| Paths
| - Use the relative path exactly as the test file location, e.g.:
|   'tests/Feature/Ui/UiButtonsTest.php'
|
| Quick examples
| return [
|     'tests/Feature/Ui/UiButtonsTest.php' => [
|         'skip' => [
|             'dashboard.blogs_widget',
|             'dashboard.announcements_widget',
|         ],
|         'owner' => 'Web Platform',
|         'reason' => 'Widgets under active development; enable after wiring',
|         'added' => 'YYY-MM-DD',
|         'review_on' => 'YYY-MM-DD',
|     ],
| ];
|
| return [
|     'resources/views/livewire/dashboard/blogs-widget.blade.php' => [
|         'all' => false,
|         'owner' => 'Web Platform',
|         'reason' => 'Widgets under active development; enable after wiring',
|         'added' => 'YYY-MM-DD',
|         'review_on' => 'YYY-MM-DD',
|     ],
| ];
|
| Inline opt-out (preferred for one-offs)
| - Not supported by this test to avoid masking UI regressions.
| - Use a tight allowlist entry above instead of per-assertion opt-outs.
|
| Already auto-allowed by the test
| - None. This allowlist is the only mechanism to skip assertions.
|
| Tips
| - Use concise, descriptive logical keys; keep entries temporary.
| - This file is PHP, not JSON—watch quotes and trailing commas.
*/

return [
    // Dashboard widgets: temporarily allow relaxed button assertions until wired
    '' => [
        'skip' => [
            '',
            '',
        ],
        'owner' => '',
        'reason' => '',
        'added' => '',
        'review_on' => '',
    ],
    // Add new entries above as needed. Keep scope tight and temporary.
];
