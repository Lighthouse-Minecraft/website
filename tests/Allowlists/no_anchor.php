<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| No-Anchor Allowlist (for tests/Feature/Ui/NoAnchorButtonsTest.php)
|--------------------------------------------------------------------------
|
| Purpose
| - Let intentional <a href> anchors pass the “no-anchor” test when they are
|   semantically correct (e.g., real links, nav items, docs), while keeping
|   the default policy strict for button-like actions.
|
| Ownership & Governance
| - Owners: Web Platform Team (set an explicit contact/owner per entry below)
| - Review cadence: Monthly. Each entry should include added/review_on dates
|   and a short reason to avoid allowlist drift.
| - Removal playbook: Prefer replacing <a> with <flux:button wire:navigate>
|   when the element behaves like a button. Keep <a> only for real links.
|
| How it works
| - This file returns a PHP array mapping repo-relative file paths to rules.
| - The test reads this file and applies rules per file before flagging an
|   anchor as an offender.
|
| Keys (per file):
| - 'all' => true
|     Allow ALL anchors in this file. Use sparingly, typically for legacy or
|     deprecating views, or for layout/navigation partials that are entirely
|     composed of anchors.
|
| - 'skip' => [ 'logical_key_1', 'logical_key_2', ... ]
|     Skip specific logical test blocks identified by keys (see NoAnchorButtonsTest
|     for the exact keys used, e.g., 'dashboard.blogs_widget'). Use narrow, temporary entries.
|
| - 'href_contains' => [ 'substring1', 'substring2', ... ]
|     If ANY provided substring is found within the anchor’s href, this anchor
|     is allowed. Useful for allowing specific routes or paths.
|
| - 'tag_contains' => [ 'substring1', 'substring2', ... ]
|     If ANY provided substring is found within the full <a ...> tag string,
|     this anchor is allowed. Useful for class/data attributes, etc.
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
|     'resources/views/livewire/example.blade.php' => [
|         'skip' => [
|             'dashboard.blogs_widget',
|         ],
|     ],
|
|     // 2) Allow specific anchors by href match
|     'resources/views/livewire/example.blade.php' => [
|         'href_contains' => [
|             "route('dashboard')", // route helper usage
|             '/legal/terms',        // string path
|         ],
|     ],
|
|     // 3) Allow anchors with a particular CSS class or data attribute
|     'resources/views/livewire/nav.blade.php' => [
|         'tag_contains' => [
|             'class="allowed-link"',
|             'data-link="external"',
|         ],
|     ],
|
|     // 4) Allow everything in a legacy/temporary file
|     'resources/views/partials/legacy.blade.php' => [
|         'all' => true,
|     ],
| ];
|
| Inline opt-out (preferred for one-offs)
| - Instead of allowlisting a whole file, mark a specific anchor:
|   <a href="..." data-allow-anchor>...</a>
|   <a href="..." data-lint-ignore="anchor">...</a>
|
| Already auto-allowed by the test
| - External links (http/https, mailto, tel)
| - target="_blank"
| - Hash-only anchors (href="#...")
| - Anything under resources/views/components/ or any /layouts/ path
| - The welcome page (resources/views/welcome.blade.php)
|
| Tips
| - Copy the reported path from a failing test and paste it as the array key.
| - Prefer href_contains/tag_contains over 'all' to keep the scope tight.
| - This file is PHP, not JSON—watch quotes and trailing commas.
*/

/*
| Example: repository-specific overrides
| - You can add extra scan paths, change extensions, or exclude generated folders.
|
| return [
|     'config' => [
|         // add another folder to scan
|         'scan_base' => [
|             'resources/views',
|             'resources/routes',
|             'app/views',
|         ],
|         // exclude a generated directory
|         'exclude_paths' => ['vendor/', 'storage/', 'node_modules/', 'public/build/', 'storage/generated/'],
|     ],
| ];
*/

return [
    // Optional configuration overrides for NoAnchorButtonsTest. Any keys here
    // will be merged with the test's defaults. Useful CI or repo-specific
    // adjustments (for example: scanning additional folders, adding
    // extensions, or excluding generated paths).
    'config' => [
        // base path(s) (relative to repo root) to scan for anchors. String or array.
        'scan_base' => [
            'resources/views',
            'resources/routes',
        ],
        // file extensions to include in the scan
        'scan_extensions' => [
            '.blade.php',
            '.php',
        ],
        // path substrings to always exclude from scanning
        'exclude_paths' => [
            'vendor/',
            'storage/',
            'node_modules/',
            'public/build/',
        ],
        // paths that are auto-allowed (components/layouts)
        'auto_allow_paths' => [
            'resources/views/components/',
            '/layouts/',
        ],
        // whether to auto-allow the welcome page
        'allow_welcome' => true,
    ],

    // Allowlist entries (file => rules). Keep this key for the new format.
    'allowlist' => [
        // Legacy/deprecating views (safe to allow entirely)
        // 'resources/views/livewire/announcements/deprecating-show.blade.php' => [
        //     'all' => true,
        //     // metadata
        //     'owner' => 'Web Platform',
        //     'reason' => 'Legacy/deprecating view; anchors acceptable until removal',
        //     'ticket' => 'TBD',
        //     'added' => '2025-08-20',
        //     'review_on' => 'REMOVED',
        // ],
        // 'resources/views/livewire/blogs/deprecating-show.blade.php' => [
        //     'all' => true,
        //     // metadata
        //     'owner' => 'Web Platform',
        //     'reason' => 'Legacy/deprecating view; anchors acceptable until removal',
        //     'ticket' => 'TBD',
        //     'added' => '2025-08-20',
        //     'review_on' => 'REMOVED',
        // ],

        // Layout/navigation components that intentionally use anchors
        // (test already auto-allows components/layouts, but kept here for explicitness)
        '' => [
            'all' => false,
            // metadata
            'owner' => '',
            'reason' => '',
            'ticket' => '',
            'added' => '',
            'review_on' => '',
        ],

        // Add new entries above as needed.
    ],
];
