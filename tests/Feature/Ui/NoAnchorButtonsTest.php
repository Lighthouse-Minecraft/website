<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Policy: In Livewire views, prefer <flux:button> or <flux:link> over raw <a href> for button-like actions.
 * This test scans Livewire Blade files for <a href> usage and fails on matches,
 * except for external links or anchors clearly intended for HTML documents.
 *
 * Allowlist & Usage: See tests/Allowlists/no_anchor.php for documented rules,
 * examples, and governance. For one-off anchors, add data-allow-anchor or
 * data-lint-ignore="anchor" directly on the tag.
 */
describe('No Anchor Buttons Test', function () {
    describe('Livewire Views', function () {
        it('does not use <a href> where <flux:button> should be used', function () {
            $config = [];
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
                    // New format: return ['config' => [...], 'allowlist' => [...]]
                    if (isset($data['allowlist']) && is_array($data['allowlist'])) {
                        $allowlist = $data['allowlist'];
                    } else {
                        // Back-compat: entire array is the allowlist
                        $allowlist = $data;
                    }

                    if (isset($data['config']) && is_array($data['config'])) {
                        $config = $data['config'];
                    }
                }
            }

            // Default configuration (can be overridden in tests/Allowlists/no_anchor.php under 'config')
            $defaults = [
                // base path (relative to repo root) to scan for anchors
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
            ];

            $config = array_merge($defaults, $config);

            // Resolve scan base(s) now that config has been merged. Support string or array.
            $scanBases = is_array($config['scan_base']) ? $config['scan_base'] : [$config['scan_base']];
            $existingBases = array_filter($scanBases, fn ($b) => is_dir(base_path($b)));
            if (empty($existingBases)) {
                $this->markTestSkipped('No configured view directories found.');
            }

            // Central collector for no-anchor allowlist skips
            $GLOBALS['no_anchor_allowlist_skipped'] = $GLOBALS['no_anchor_allowlist_skipped'] ?? [];

            // Ensure consolidated no-anchor allowlist report is always printed at process exit
            register_shutdown_function(function (): void {
                if (empty($GLOBALS['no_anchor_allowlist_skipped'])) {
                    return;
                }

                $out = "No-anchor allowlist requested skip:\n";
                foreach ($GLOBALS['no_anchor_allowlist_skipped'] as $file => $meta) {
                    $line = $meta['line'] ?? 'unknown';
                    $snippet = $meta['snippet'] ?? '';
                    $out .= "\033[33m- skipped {$file}\033[0m\n";
                    $out .= "    \033[31mlocated on line:\033[0m {$line}\n";
                    if ($snippet !== '') {
                        $out .= "    \033[36msnippet:\033[0m {$snippet}\n";
                    }
                }
                fwrite(STDOUT, $out);
            });

            // Support multiple scan bases (scan_base may be a string or an array of relative paths)
            $scanBases = is_array($config['scan_base']) ? $config['scan_base'] : [$config['scan_base']];

            foreach ($scanBases as $scanBaseRel) {
                $basePath = base_path($scanBaseRel);
                if (! is_dir($basePath)) {
                    continue; // skip missing configured bases
                }

                $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($basePath));
                /** @var SplFileInfo $file */
                foreach ($rii as $file) {
                    if (! $file->isFile()) {
                        continue;
                    }

                    // Inspect file extensions from config
                    $pathname = $file->getPathname();
                    $matchesExt = false;
                    foreach ($config['scan_extensions'] as $ext) {
                        if (str_ends_with($pathname, $ext)) {
                            $matchesExt = true;
                            break;
                        }
                    }

                    if (! $matchesExt) {
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
                    $pattern = '/<a\s+[^>]*href\s*=\s*("([^\"]*)"|\'([^\']*)\')[^>]*>/i';
                    if (preg_match_all($pattern, $contents, $matches, PREG_SET_ORDER)) {
                        foreach ($matches as $m) {
                            // Support both quote capture groups
                            $href = $m[2] ?? ($m[3] ?? '');
                            $tag = $m[0] ?? '';

                            $isExternal = preg_match('/^(https?:\/\/|mailto:|tel:)/i', $href) === 1;
                            $isAnchorOnly = str_starts_with($href, '#');
                            $hasTargetBlank = stripos($tag, 'target="_blank"') !== false;
                            $relativePath = str_replace(base_path().'/', '', $file->getPathname());
                            // auto-allow paths from config
                            $inLayoutsOrComponents = false;
                            foreach ($config['auto_allow_paths'] as $p) {
                                if (str_contains($relativePath, $p)) {
                                    $inLayoutsOrComponents = true;
                                    break;
                                }
                            }
                            $isWelcome = $config['allow_welcome'] && str_ends_with($relativePath, 'resources/views/welcome.blade.php');

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
                                    // record the first occurrence for the consolidated allowlist report
                                    if (! isset($GLOBALS['no_anchor_allowlist_skipped'][$relativePath])) {
                                        $GLOBALS['no_anchor_allowlist_skipped'][$relativePath] = [
                                            'line' => 'unknown',
                                            'snippet' => $tag,
                                        ];
                                    }

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
                                    if (! isset($GLOBALS['no_anchor_allowlist_skipped'][$relativePath])) {
                                        $GLOBALS['no_anchor_allowlist_skipped'][$relativePath] = [
                                            'line' => 'unknown',
                                            'snippet' => $tag,
                                        ];
                                    }

                                    continue; // allowed by file allowlist
                                }
                            }

                            $offenders[] = [
                                'file' => $relativePath,
                                'tag' => $tag,
                                'href' => $href,
                            ];
                        }
                    }
                }
            }

            if (! empty($offenders)) {
                $message = "Found <a href> usage where <flux:button> or <flux:link> should be used.\n";
                foreach ($offenders as $o) {
                    $message .= "- {$o['file']} -> href=\"{$o['href']}\"\n";
                }
                $this->fail($message);
            }

            expect($offenders)->toBe([]);
        })->done(assignee: 'ghostridr');
    })->done(assignee: 'ghostridr');

    // Allowlist skipped tests
    describe('Allowlist skipped tests', function () {
        it('skips allowlisted tests', function () {
            $this->assertTrue(true);

            // Ensure consolidated allowlist skips are written to STDOUT so test runners capture them.
            if (! empty($GLOBALS['no_anchor_allowlist_skipped'])) {
                $out = "No Anchor Buttons allowlist requested skip:\n";
                foreach ($GLOBALS['no_anchor_allowlist_skipped'] as $file => $meta) {
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
