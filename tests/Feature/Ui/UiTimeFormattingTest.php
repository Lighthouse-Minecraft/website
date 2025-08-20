<?php

declare(strict_types=1);

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
    })->done('ghostridr');

    it('does not include inline time-formatting scripts in Blade views', function () {
        $bladeDir = resource_path('views');
        $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($bladeDir));
        $offenders = [];

        // Optional allowlist for inline time checks
        $allowlist = [];
        $allowlistPath = base_path('tests/Allowlists/time_formatting.php');
        if (file_exists($allowlistPath)) {
            $data = include $allowlistPath;
            if (is_array($data)) {
                $allowlist = $data;
            }
        }

        /** @var SplFileInfo $file */
        foreach ($rii as $file) {
            if (! $file->isFile() || ! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }

            $relativePath = str_replace(base_path().'/', '', $file->getPathname());
            $contents = file_get_contents($file->getPathname());
            if ($contents === false) {
                continue;
            }

            // Strip Blade and HTML comments
            $stripped = preg_replace('/\{\{\-\-[\s\S]*?\-\-\}\}/m', '', $contents);
            $stripped = preg_replace('/<!--([\s\S]*?)-->/', '', $stripped);

            // Allowlist decisions
            $rules = $allowlist[$relativePath] ?? [];
            $inlineAll = ($rules['inline_all'] ?? false) === true;
            $inlineContains = $rules['inline_contains'] ?? [];

            if ($inlineAll) {
                continue;
            }

            $hasInline = preg_match('/function\s+formatLocalTimes\s*\(/', $stripped) === 1
                || str_contains($stripped, "document.querySelectorAll('time.comment-ts[datetime]')");

            if ($hasInline) {
                $allowedByContains = false;
                foreach ($inlineContains as $needle) {
                    if ($needle !== '' && str_contains($stripped, $needle)) {
                        $allowedByContains = true;
                        break;
                    }
                }
                if ($allowedByContains) {
                    continue;
                }

                $offenders[] = [
                    'file' => $relativePath,
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
    })->done('ghostridr');

    it('wraps toIso8601String() outputs in a <time> tag with a datetime attr, includes an allowed fallback format, and requires class="comment-ts" only in comment contexts', function () {
        $bladeDir = resource_path('views');
        $offenders = [];

        // Optional allowlist for time formatting enforcement
        $allowlist = [];
        $allowlistPath = base_path('tests/Allowlists/time_formatting.php');
        if (file_exists($allowlistPath)) {
            $data = include $allowlistPath;
            if (is_array($data)) {
                $allowlist = $data;
            }
        }

        foreach (getBladeFiles($bladeDir) as $path) {
            $lines = @file($path) ?: [];
            foreach ($lines as $lineNumber => $line) {
                if (! (str_contains($line, 'toIso8601String(') || str_contains($line, 'toIso8601String()'))) {
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

                $relative = str_replace(base_path().'/', '', $path);

                // File-level allow-all skip
                if (($allowlist[$relative]['all'] ?? false) === true) {
                    continue;
                }

                // Allowlist snippet patterns (skip if any match)
                $snippetContains = $allowlist[$relative]['snippet_contains'] ?? [];
                $skipBySnippet = false;
                foreach ($snippetContains as $needle) {
                    if ($needle !== '' && str_contains($snippet, $needle)) {
                        $skipBySnippet = true;
                        break;
                    }
                }
                if ($skipBySnippet) {
                    continue;
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

                // Allowed fallback patterns (expandable). Must contain at least ONE nearby.
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
    })->done('ghostridr');

    it('ensures direct format()/translatedFormat() usages are wrapped in a <time datetime="...toIso8601String()">', function () {
        $bladeDir = resource_path('views');
        $offenders = [];

        // Optional allowlist for time formatting enforcement
        $allowlist = [];
        $allowlistPath = base_path('tests/Allowlists/time_formatting.php');
        if (file_exists($allowlistPath)) {
            $data = include $allowlistPath;
            if (is_array($data)) {
                $allowlist = $data;
            }
        }

        foreach (getBladeFiles($bladeDir) as $path) {
            $raw = file_get_contents($path) ?: '';
            // Strip Blade and HTML comments across the whole file to avoid false positives
            $stripped = preg_replace('/\{\{\-\-[\s\S]*?\-\-\}\}/m', '', $raw);
            $stripped = preg_replace('/<!--([\s\S]*?)-->/', '', $stripped);
            $lines = explode("\n", $stripped);
            foreach ($lines as $lineNumber => $line) {
                if (! (str_contains($line, '->format(') || str_contains($line, '->translatedFormat('))) {
                    continue;
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

                $relative = str_replace(base_path().'/', '', $path);

                // File-level allow-all skip
                if (($allowlist[$relative]['all'] ?? false) === true) {
                    continue;
                }

                // Allowlist snippet patterns (skip if any match)
                $snippetContains = $allowlist[$relative]['snippet_contains'] ?? [];
                $skipBySnippet = false;
                foreach ($snippetContains as $needle) {
                    if ($needle !== '' && str_contains($snippet, $needle)) {
                        $skipBySnippet = true;
                        break;
                    }
                }
                if ($skipBySnippet) {
                    continue;
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
        }

        if (! empty($offenders)) {
            $message = "Direct date format usage not wrapped in <time> found:\n";
            foreach ($offenders as $o) {
                $message .= "- {$o['file']}:{$o['line']} {$o['issue']} | {$o['context']}\n";
            }
            $this->fail($message);
        }

        expect($offenders)->toBe([]);
    })->done('ghostridr');
})->done('ghostridr');
