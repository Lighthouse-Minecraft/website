<?php

declare(strict_types=1);

// Allowlist helper for time formatting
function timeFormattingAllowlistedFile(string $relativeBladePath): bool
{
    $allowlistPath = base_path('tests/Allowlists/time_formatting.php');
    if (! file_exists($allowlistPath)) {
        return false;
    }
    $data = include $allowlistPath;
    $rules = $data[$relativeBladePath] ?? null;
    if (! is_array($rules)) {
        return false;
    }
    if (($rules['all'] ?? false) === true) {
        return true;
    }
    $snippetContains = $rules['snippet_contains'] ?? [];
    if (empty($snippetContains)) {
        return false;
    }
    $fullPath = base_path($relativeBladePath);
    $contents = is_file($fullPath) ? (string) file_get_contents($fullPath) : '';
    foreach ($snippetContains as $needle) {
        if ($needle !== '' && str_contains($contents, $needle)) {
            return true;
        }
    }

    return false;
}

function getBladeFiles(string $path): array
{
    $files = [];
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path));
    foreach ($it as $fileinfo) {
        if ($fileinfo->isFile() && str_ends_with($fileinfo->getFilename(), '.blade.php')) {
            $files[] = $fileinfo->getPathname();
        }
    }

    return $files;
}

function isCommentContext(string $path): bool
{
    $p = strtolower($path);

    // Consider anything inside a 'comments' folder or filename containing 'comments' as a comment context
    return str_contains($p, '/comments/') || str_contains(basename($p), 'comments');
}

// Central collector for allowlist-requested skips across the tests in this file.
$GLOBALS['time_formatting_allowlist_skipped'] = [];

describe('Time Formatting', function () {
    it('imports timescript in app.js', function () {
        $appJs = base_path('resources/js/app.js');

        expect(file_exists($appJs))->toBeTrue();

        $content = (string) file_get_contents($appJs);

        expect($content)
            ->toContain("import './timescript.js'");
    })->done(assignee: 'ghostridr');

    it('has timescript.js with the expected formatter IIFE', function () {
        $file = base_path('resources/js/timescript.js');

        expect(file_exists($file))->toBeTrue();

        $content = (string) file_get_contents($file);

        expect($content)
            ->toContain('(function ()')
            ->toContain('function formatLocalTimes()')
            ->toContain('Intl.DateTimeFormat')
            ->toContain("document.querySelectorAll('time.comment-ts[datetime]')");
    })->done(assignee: 'ghostridr');

    it('does not include inline time-formatting scripts in Blade views', function () {
        $bladeDir = resource_path('views');
        $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($bladeDir));
        $offenders = [];
        $skipped = [];

        foreach ($rii as $file) {
            if (! $file->isFile() || ! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }
            $relativePath = str_replace(base_path().'/', '', $file->getPathname());
            $contents = file_get_contents($file->getPathname());
            if ($contents === false) {
                continue;
            }
            $stripped = preg_replace('/\{\{\-\-[\s\S]*?\-\-\}\}/m', '', $contents);
            $stripped = preg_replace('/<!--([\s\S]*?)-->/', '', $stripped);
            $lines = explode("\n", $stripped);
            foreach ($lines as $lineNumber => $line) {
                $hasInline = preg_match('/function\s+formatLocalTimes\s*\(/', $line) === 1
                    || str_contains($line, "document.querySelectorAll('time.comment-ts[datetime]')");
                if (! $hasInline) {
                    continue;
                }

                if (timeFormattingAllowlistedFile($relativePath)) {
                    if (! isset($GLOBALS['time_formatting_allowlist_skipped'][$relativePath])) {
                        $GLOBALS['time_formatting_allowlist_skipped'][$relativePath] = [
                            'line' => $lineNumber + 1,
                            'snippet' => trim($line),
                        ];
                    }

                    continue;
                }

                $offenders[] = [
                    'file' => $relativePath,
                    'line' => $lineNumber + 1,
                    'issue' => 'Inline time format script present; use resources/js/timescript.js via Vite',
                ];
            }
        }

        if (! empty($offenders)) {
            $message = "Found inline time-formatting script usage.\n";
            foreach ($offenders as $o) {
                $message .= "- {$o['file']} -> {$o['issue']}\n";
            }
            $this->fail($message);
        }
        expect($offenders)->toBe([]);
    })->done(assignee: 'ghostridr');

    it('wraps toIso8601String() outputs in a <time> tag with a datetime attr, includes an allowed fallback format, and requires class="comment-ts" only in comment contexts', function () {
        $bladeDir = resource_path('views');
        $offenders = [];
        $skipped = [];
        foreach (getBladeFiles($bladeDir) as $path) {
            $relative = str_replace(base_path().'/', '', $path);
            $lines = @file($path) ?: [];
            foreach ($lines as $lineNumber => $line) {
                if (! (str_contains($line, 'toIso8601String(') || str_contains($line, 'toIso8601String()'))) {
                    continue;
                }
                // If file is allowlisted, record first occurrence line and snippet only
                if (timeFormattingAllowlistedFile($relative)) {
                    if (! isset($GLOBALS['time_formatting_allowlist_skipped'][$relative])) {
                        $GLOBALS['time_formatting_allowlist_skipped'][$relative] = [
                            'line' => $lineNumber + 1,
                            'snippet' => trim($line),
                        ];
                    }

                    continue;
                }
                if (str_contains($line, '{{--')) {
                    continue; // skip Blade comments
                }

                $start = max(0, $lineNumber - 5);
                $end = min(count($lines) - 1, $lineNumber + 15);
                $snippet = '';
                for ($i = $start; $i <= $end; $i++) {
                    $snippet .= $lines[$i];
                }
                if (preg_match('/data-[a-zA-Z0-9_-]*\s*=\s*\"[^\"]*toIso8601String\(/', $snippet)) {
                    continue; // skip data-* attributes
                }
                // Must contain <time and datetime=
                if (! (str_contains($snippet, '<time') && str_contains($snippet, 'datetime='))) {
                    $offenders[] = [
                        'file' => $relative,
                        'line' => $lineNumber + 1,
                        'issue' => 'Missing <time ... datetime=...> near toIso8601String()',
                        'context' => trim($line),
                    ];

                    continue;
                }
                // Only require class="comment-ts" in comment contexts
                if (isCommentContext($path) && ! str_contains($snippet, 'class="comment-ts"')) {
                    $offenders[] = [
                        'file' => $relative,
                        'line' => $lineNumber + 1,
                        'issue' => 'Missing class="comment-ts" in comment context',
                        'context' => trim($line),
                    ];

                    continue;
                }
                $allowedFallbacks = [
                    'M d, Y H:i',
                    'M d, Y',
                    'Y-m-d H:i',
                    'M j, Y H:i',
                    'M d, Y g:i A',
                    'm/d/y @ h:i a',
                ];
                $hasAllowedFallback = false;
                foreach ($allowedFallbacks as $pattern) {
                    if (str_contains($snippet, "format('{$pattern}')") ||
                        str_contains($snippet, "format(\"{$pattern}\")") ||
                        str_contains($snippet, "translatedFormat('{$pattern}')") ||
                        str_contains($snippet, "translatedFormat(\"{$pattern}\")")) {
                        $hasAllowedFallback = true;
                        break;
                    }
                }
                if (! $hasAllowedFallback) {
                    $offenders[] = [
                        'file' => $relative,
                        'line' => $lineNumber + 1,
                        'issue' => 'Missing allowed fallback format near toIso8601String()',
                        'context' => trim($line),
                    ];
                }
            }
        }

        if (! empty($offenders)) {
            $message = "Time formatting policy violations found:\n";
            foreach ($offenders as $o) {
                $message .= "- {$o['file']}:{$o['line']} {$o['issue']} | {$o['context']}\n";
            }
            $this->fail($message);
        }
        expect($offenders)->toBe([]);
    })->done(assignee: 'ghostridr');

    it('ensures direct format()/translatedFormat() usages are wrapped in a <time datetime="...toIso8601String()">', function () {
        $bladeDir = resource_path('views');
        $offenders = [];
        $skipped = [];
        foreach (getBladeFiles($bladeDir) as $path) {
            $relative = str_replace(base_path().'/', '', $path);
            $raw = file_get_contents($path) ?: '';
            $stripped = preg_replace('/\{\{\-\-[\s\S]*?\-\-\}\}/m', '', $raw);
            $stripped = preg_replace('/<!--([\s\S]*?)-->/', '', $stripped);
            $lines = explode("\n", $stripped);
            $foundAndRecorded = false;
            foreach ($lines as $lineNumber => $line) {
                if (! (str_contains($line, '->format(') || str_contains($line, '->translatedFormat('))) {
                    continue;
                }
                // If the file is allowlisted, record the first matching line+snippet and skip further checks for this file
                if (timeFormattingAllowlistedFile($relative)) {
                    if (! isset($GLOBALS['time_formatting_allowlist_skipped'][$relative])) {
                        $GLOBALS['time_formatting_allowlist_skipped'][$relative] = [
                            'line' => $lineNumber + 1,
                            'snippet' => trim($line),
                        ];
                    }
                    $foundAndRecorded = true;
                    break;
                }
                $start = max(0, $lineNumber - 5);
                $end = min(count($lines) - 1, $lineNumber + 15);
                $snippet = '';
                for ($i = $start; $i <= $end; $i++) {
                    $snippet .= $lines[$i];
                }
                if (preg_match('/data-[a-zA-Z0-9_-]*\s*=\s*\"[^\"]*->format\(/', $snippet) || preg_match('/data-[a-zA-Z0-9_-]*\s*=\s*\"[^\"]*->translatedFormat\(/', $snippet)) {
                    continue; // skip data-* attributes
                }
                // Must contain <time and datetime= and toIso8601String nearby
                if (! (str_contains($snippet, '<time') && str_contains($snippet, 'datetime=') && (str_contains($snippet, 'toIso8601String(') || str_contains($snippet, 'toIso8601String()')))) {
                    $offenders[] = [
                        'file' => $relative,
                        'line' => $lineNumber + 1,
                        'issue' => 'Direct ->format()/->translatedFormat() used without wrapping in <time ... datetime="...toIso8601String()">',
                        'context' => trim($line),
                    ];
                }
            }
            if ($foundAndRecorded) {
                continue;
            }
        }

        if (! empty($offenders)) {
            $message = "Direct date format usage not wrapped in <time> found:\n";
            foreach ($offenders as $o) {
                $message .= "- {$o['file']}:{$o['line']} {$o['issue']} | {$o['context']}\n";
            }
            $this->fail($message);
        }
        expect($offenders)->toBe([]);
    })->done(assignee: 'ghostridr');

    // Allowlist skipped tests
    describe('Allowlist skipped tests', function () {
        it('skips allowlisted tests', function () {
            $this->assertTrue(true);

            // Ensure consolidated allowlist skips are written to STDOUT so test runners capture them.
            if (! empty($GLOBALS['time_formatting_allowlist_skipped'])) {
                $out = "UI Time Formatting allowlist requested skip:\n";
                foreach ($GLOBALS['time_formatting_allowlist_skipped'] as $file => $meta) {
                    $line = $meta['line'] ?? 'unknown';
                    $snippet = $meta['snippet'] ?? '';
                    $out .= "\033[33m- skipped {$file}\033[0m\n";
                    $out .= "    \033[31mlocated on line:\033[0m {$line}\n";
                    if ($snippet !== '') {
                        $out .= "    \033[36msnippet:\033[0m {$snippet}\n";
                    }
                }
                fwrite(STDOUT, $out);
            }
        })->done(assignee: 'ghostridr');
    })->done(assignee: 'ghostridr');
})->done(assignee: 'ghostridr');
