<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Policy: In Livewire views, prefer <flux:button> over raw <a href> for button-like actions.
 * This test scans Livewire Blade files for <a href> usage and fails on matches,
 * except for external links or anchors clearly intended for HTML documents.
 *
 * Allowlist & Usage: See tests/Allowlists/no_anchor.php for documented rules,
 * examples, and governance. For one-off anchors, add data-allow-anchor or
 * data-lint-ignore="anchor" directly on the tag.
 */
it('does not use <a href> where <flux:button> should be used', function () {
    // Scan the entire views directory, with allow-list exceptions for places where raw anchors are expected
    $base = base_path('resources/views');
    if (! is_dir($base)) {
        $this->markTestSkipped('No views directory found.');
    }

    $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base));
    $offenders = [];

    // Optional file/path based allowlist
    // Create tests/Allowlists/no_anchor.php returning an array like:
    // return [
    //   'resources/views/livewire/some.blade.php' => [
    //       'all' => false,
    //       'href_contains' => ['route(\'dashboard\')'],
    //       'tag_contains' => ['class="allowed-link"'],
    //   ],
    // ];
    $allowlist = [];
    $allowlistPath = base_path('tests/Allowlists/no_anchor.php');
    if (file_exists($allowlistPath)) {
        $data = include $allowlistPath;
        if (is_array($data)) {
            $allowlist = $data;
        }
    }

    /** @var SplFileInfo $file */
    foreach ($rii as $file) {
        if (! $file->isFile()) {
            continue;
        }
        if (! str_ends_with($file->getFilename(), '.blade.php')) {
            continue;
        }

        $contents = file_get_contents($file->getPathname());
        if ($contents === false) {
            continue;
        }

        // Strip Blade and HTML comments to avoid false positives from commented-out code
        // Remove Blade comments: {{-- ... --}}
        $contents = preg_replace('/\{\{\-\-[\s\S]*?\-\-\}\}/m', '', $contents);
        // Remove HTML comments: <!-- ... -->
        $contents = preg_replace('/<!--([\s\S]*?)-->/', '', $contents);

        // Allow external/document anchors
        // - http/https/mailto/tel
        // - target="_blank"
        // - anchor-only hrefs like href="#..."
        // Additionally allow anchors in layout/components and welcome page.
        // We will flag any other <a href="..."> occurrences.
        $pattern = '/<a\s+[^>]*href\s*=\s*(\"([^\"]*)\"|\'([^\']*)\')[^>]*>/i';
        if (preg_match_all($pattern, $contents, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                // Support both quote capture groups
                $href = $m[2] ?? ($m[3] ?? '');
                $tag = $m[0] ?? '';

                $isExternal = preg_match('/^(https?:\/\/|mailto:|tel:)/i', $href) === 1;
                $isAnchorOnly = str_starts_with($href, '#');
                $hasTargetBlank = stripos($tag, 'target="_blank"') !== false;
                $relativePath = str_replace(base_path().'/', '', $file->getPathname());
                $inLayoutsOrComponents = str_contains($relativePath, 'resources/views/components/') || str_contains($relativePath, '/layouts/');
                $isWelcome = str_ends_with($relativePath, 'resources/views/welcome.blade.php');

                // Inline opt-out: add data-allow-anchor or data-lint-ignore="anchor" to the <a> tag
                $hasInlineAllow = stripos($tag, 'data-allow-anchor') !== false
                    || stripos($tag, 'data-lint-ignore="anchor"') !== false
                    || stripos($tag, "data-lint-ignore='anchor'") !== false;

                if ($isExternal || $isAnchorOnly || $hasTargetBlank || $inLayoutsOrComponents || $isWelcome || $hasInlineAllow) {
                    continue; // allowed
                }

                // File/path based allowlist checks
                if (isset($allowlist[$relativePath]) && is_array($allowlist[$relativePath])) {
                    $rules = $allowlist[$relativePath];
                    if (($rules['all'] ?? false) === true) {
                        continue; // allow all anchors in this file
                    }
                    $hrefContains = $rules['href_contains'] ?? [];
                    $tagContains = $rules['tag_contains'] ?? [];
                    $allowedByHref = array_reduce($hrefContains, function ($carry, $needle) use ($href) {
                        return $carry || ($needle !== '' && str_contains($href, $needle));
                    }, false);
                    $allowedByTag = array_reduce($tagContains, function ($carry, $needle) use ($tag) {
                        return $carry || ($needle !== '' && str_contains($tag, $needle));
                    }, false);
                    if ($allowedByHref || $allowedByTag) {
                        continue; // allowed by file allowlist
                    }
                }

                // Not allowed: internal navigation in application views should use <flux:button wire:navigate>
                $offenders[] = [
                    'file' => $relativePath,
                    'tag' => $tag,
                    'href' => $href,
                ];
            }
        }
    }

    if (! empty($offenders)) {
        $message = "Found <a href> usage where <flux:button> should be used.\n";
        foreach ($offenders as $o) {
            $message .= "- {$o['file']} -> href=\"{$o['href']}\"\n";
        }
        $this->fail($message);
    }

    expect($offenders)->toBe([]);
})->done('ghostridr');
