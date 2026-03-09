<?php

namespace App\Services;

use App\Services\Docs\BookDTO;
use App\Services\Docs\ChapterDTO;
use App\Services\Docs\GuideDTO;
use App\Services\Docs\PageDTO;
use App\Services\Docs\PartDTO;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Yaml\Yaml;

class DocumentationService
{
    private ?Collection $booksCache = null;

    private ?Collection $guidesCache = null;

    public function __construct(private string $basePath) {}

    // ─── Discovery ───────────────────────────────────────────────

    public function getAllBooks(): Collection
    {
        if ($this->booksCache !== null) {
            return $this->booksCache;
        }

        $booksPath = $this->basePath.'/books';
        if (! is_dir($booksPath)) {
            return $this->booksCache = collect();
        }

        $books = collect();
        foreach ($this->getSortedDirectories($booksPath) as $bookDir) {
            $book = $this->buildBook($bookDir);
            if ($book) {
                $books->push($book);
            }
        }

        return $this->booksCache = $books->sortBy('order')->values();
    }

    public function getAllGuides(): Collection
    {
        if ($this->guidesCache !== null) {
            return $this->guidesCache;
        }

        $guidesPath = $this->basePath.'/guides';
        if (! is_dir($guidesPath)) {
            return $this->guidesCache = collect();
        }

        $guides = collect();
        foreach ($this->getSortedDirectories($guidesPath) as $guideDir) {
            $guide = $this->buildGuide($guideDir);
            if ($guide) {
                $guides->push($guide);
            }
        }

        return $this->guidesCache = $guides->sortBy('order')->values();
    }

    public function getBook(string $slug): ?BookDTO
    {
        return $this->getAllBooks()->first(fn (BookDTO $b) => $b->slug === $slug);
    }

    public function getGuide(string $slug): ?GuideDTO
    {
        return $this->getAllGuides()->first(fn (GuideDTO $g) => $g->slug === $slug);
    }

    // ─── Page Resolution ─────────────────────────────────────────

    public function findBookPage(string $bookSlug, string $partSlug, string $chapterSlug, string $pageSlug): ?PageDTO
    {
        $book = $this->getBook($bookSlug);
        if (! $book) {
            return null;
        }

        $part = $book->parts->first(fn (PartDTO $p) => $p->slug === $partSlug);
        if (! $part) {
            return null;
        }

        $chapter = $part->chapters->first(fn (ChapterDTO $c) => $c->slug === $chapterSlug);
        if (! $chapter) {
            return null;
        }

        return $chapter->pages->first(fn (PageDTO $p) => $p->slug === $pageSlug);
    }

    public function findPartIndex(string $bookSlug, string $partSlug): ?PartDTO
    {
        $book = $this->getBook($bookSlug);
        if (! $book) {
            return null;
        }

        return $book->parts->first(fn (PartDTO $p) => $p->slug === $partSlug);
    }

    public function findChapterIndex(string $bookSlug, string $partSlug, string $chapterSlug): ?ChapterDTO
    {
        $part = $this->findPartIndex($bookSlug, $partSlug);
        if (! $part) {
            return null;
        }

        return $part->chapters->first(fn (ChapterDTO $c) => $c->slug === $chapterSlug);
    }

    public function findGuidePage(string $guideSlug, string $pageSlug): ?PageDTO
    {
        $guide = $this->getGuide($guideSlug);
        if (! $guide) {
            return null;
        }

        return $guide->pages->first(fn (PageDTO $p) => $p->slug === $pageSlug);
    }

    // ─── Parsing ─────────────────────────────────────────────────

    public function parseFile(string $filePath): array
    {
        if (! file_exists($filePath)) {
            return ['meta' => [], 'body' => ''];
        }

        $content = file_get_contents($filePath);

        if (preg_match('/\A---\s*\n(.*?)\n---\s*\n?(.*)\z/s', $content, $matches)) {
            try {
                $meta = Yaml::parse($matches[1]) ?? [];
            } catch (\Exception $e) {
                Log::warning("Failed to parse YAML front matter in {$filePath}: {$e->getMessage()}");
                $meta = [];
            }
            $body = $matches[2];
        } else {
            $meta = [];
            $body = $content;
        }

        return ['meta' => $meta, 'body' => trim($body)];
    }

    // ─── Navigation ──────────────────────────────────────────────

    public function getBookNavigation(string $bookSlug): array
    {
        $book = $this->getBook($bookSlug);
        if (! $book) {
            return [];
        }

        $nav = [];
        foreach ($book->parts as $part) {
            $partItem = [
                'label' => $part->title,
                'url' => $part->url,
                'children' => [],
            ];

            foreach ($part->chapters as $chapter) {
                $chapterItem = [
                    'label' => $chapter->title,
                    'url' => $chapter->url,
                    'children' => [],
                ];

                foreach ($chapter->pages as $page) {
                    $chapterItem['children'][] = [
                        'label' => $page->title,
                        'url' => $page->url,
                    ];
                }

                $partItem['children'][] = $chapterItem;
            }

            $nav[] = $partItem;
        }

        return $nav;
    }

    public function getGuideNavigation(string $guideSlug): array
    {
        $guide = $this->getGuide($guideSlug);
        if (! $guide) {
            return [];
        }

        $nav = [];
        foreach ($guide->pages as $page) {
            $nav[] = [
                'label' => $page->title,
                'url' => $page->url,
            ];
        }

        return $nav;
    }

    public function getAdjacentPages(string $type, string ...$slugs): array
    {
        $allPages = $this->getFlattenedPages($type, $slugs[0] ?? '');

        // Build the current page URL to match against, since slugs alone
        // are not unique across parts/chapters (e.g. "linking-your-account"
        // exists in both Minecraft and Discord).
        $currentUrl = match ($type) {
            'book' => count($slugs) >= 4 ? route('library.books.page', [$slugs[0], $slugs[1], $slugs[2], $slugs[3]]) : '',
            'guide' => count($slugs) >= 2 ? route('library.guides.page', [$slugs[0], $slugs[1]]) : '',
            default => '',
        };

        $index = $allPages->search(fn (PageDTO $p) => $p->url === $currentUrl);

        if ($index === false) {
            return ['prev' => null, 'next' => null];
        }

        return [
            'prev' => $index > 0 ? $allPages[$index - 1] : null,
            'next' => $index < $allPages->count() - 1 ? $allPages[$index + 1] : null,
        ];
    }

    // ─── Visibility ──────────────────────────────────────────────

    public function resolveVisibility(array $meta, string $dirPath): string
    {
        if (! empty($meta['visibility'])) {
            return $meta['visibility'];
        }

        // Walk up the directory tree looking for _index.md with visibility
        $current = $dirPath;
        while ($current !== $this->basePath && strlen($current) > strlen($this->basePath)) {
            $indexPath = $current.'/_index.md';
            if (file_exists($indexPath)) {
                $parentMeta = $this->parseFile($indexPath)['meta'];
                if (! empty($parentMeta['visibility'])) {
                    return $parentMeta['visibility'];
                }
            }
            $current = dirname($current);
        }

        return 'public';
    }

    // ─── Editor Support ──────────────────────────────────────────

    public function getEditableTree(): array
    {
        $tree = [];

        // Books
        $booksPath = $this->basePath.'/books';
        if (is_dir($booksPath)) {
            $tree[] = [
                'title' => 'Books',
                'slug' => 'books',
                'type' => 'section',
                'children' => $this->buildEditableTreeRecursive($booksPath, 'books'),
            ];
        }

        // Guides
        $guidesPath = $this->basePath.'/guides';
        if (is_dir($guidesPath)) {
            $tree[] = [
                'title' => 'Guides',
                'slug' => 'guides',
                'type' => 'section',
                'children' => $this->buildEditableTreeRecursive($guidesPath, 'guides'),
            ];
        }

        return $tree;
    }

    public function savePage(string $relativePath, array $meta, string $body): void
    {
        $fullPath = $this->basePath.'/'.$relativePath;

        if (! $this->isValidDocsPath($relativePath)) {
            throw new \InvalidArgumentException('Invalid file path.');
        }

        $content = $this->buildFileContent($meta, $body);
        file_put_contents($fullPath, $content);

        $this->clearCache();
    }

    public function createPage(string $relativeDir, string $filename, array $meta, string $body): void
    {
        $dirPath = $this->basePath.'/'.$relativeDir;

        if (! $this->isValidDocsPath($relativeDir)) {
            throw new \InvalidArgumentException('Invalid directory path.');
        }

        if (! is_dir($dirPath)) {
            mkdir($dirPath, 0755, true);
        }

        $fullPath = $dirPath.'/'.$filename;
        $content = $this->buildFileContent($meta, $body);
        file_put_contents($fullPath, $content);

        $this->clearCache();
    }

    public function renamePage(string $oldRelativePath, string $newFilename): string
    {
        if (! $this->isValidDocsPath($oldRelativePath)) {
            throw new \InvalidArgumentException('Invalid source path.');
        }

        $oldFullPath = $this->basePath.'/'.$oldRelativePath;
        if (! file_exists($oldFullPath)) {
            throw new \InvalidArgumentException('Source file does not exist.');
        }

        $dir = dirname($oldRelativePath);
        $newRelativePath = $dir.'/'.$newFilename;

        if (! $this->isValidDocsPath($dir)) {
            throw new \InvalidArgumentException('Invalid directory path.');
        }

        $newFullPath = $this->basePath.'/'.$newRelativePath;
        if (file_exists($newFullPath)) {
            throw new \InvalidArgumentException('A file with that name already exists.');
        }

        rename($oldFullPath, $newFullPath);
        $this->clearCache();

        return $newRelativePath;
    }

    public function getRelativePath(string $absolutePath): string
    {
        return str_replace($this->basePath.'/', '', $absolutePath);
    }

    public function resolveViewUrl(string $relativePath): ?string
    {
        $parts = explode('/', $relativePath);
        $type = $parts[0] ?? '';

        if ($type === 'books' && count($parts) >= 2) {
            $bookSlug = $this->toSlug($parts[1]);
            $fileName = end($parts);

            if (count($parts) === 3 && $fileName === '_index.md') {
                return route('library.books.show', $bookSlug);
            }
            if (count($parts) === 4 && $fileName === '_index.md') {
                return route('library.books.part', [$bookSlug, $this->toSlug($parts[2])]);
            }
            if (count($parts) === 5 && $fileName === '_index.md') {
                return route('library.books.chapter', [$bookSlug, $this->toSlug($parts[2]), $this->toSlug($parts[3])]);
            }
            if (count($parts) === 5 && $fileName !== '_index.md') {
                return route('library.books.page', [$bookSlug, $this->toSlug($parts[2]), $this->toSlug($parts[3]), $this->toSlug($fileName)]);
            }
        }

        if ($type === 'guides' && count($parts) >= 2) {
            $guideSlug = $this->toSlug($parts[1]);
            $fileName = end($parts);

            if (count($parts) === 3 && $fileName === '_index.md') {
                return route('library.guides.show', $guideSlug);
            }
            if (count($parts) === 3 && $fileName !== '_index.md') {
                return route('library.guides.page', [$guideSlug, $this->toSlug($fileName)]);
            }
        }

        return null;
    }

    public function isValidDocsPath(string $path): bool
    {
        // Prevent path traversal
        $normalized = realpath($this->basePath.'/'.$path);

        // If file doesn't exist yet, check the parent directory
        if ($normalized === false) {
            $parentDir = dirname($this->basePath.'/'.$path);
            $normalized = realpath($parentDir);
            if ($normalized === false) {
                return false;
            }
        }

        return str_starts_with($normalized, realpath($this->basePath));
    }

    // ─── Private Builders ────────────────────────────────────────

    private function buildBook(string $dirPath): ?BookDTO
    {
        $dirName = basename($dirPath);
        $slug = $this->toSlug($dirName);
        $indexData = $this->parseFile($dirPath.'/_index.md');
        $meta = $indexData['meta'];
        $visibility = $meta['visibility'] ?? 'public';

        $parts = collect();
        foreach ($this->getSortedDirectories($dirPath) as $partDir) {
            $part = $this->buildPart($partDir, $slug, $visibility);
            if ($part) {
                $parts->push($part);
            }
        }

        return new BookDTO(
            title: $meta['title'] ?? $this->slugToTitle($slug),
            slug: $slug,
            visibility: $visibility,
            order: $meta['order'] ?? $this->extractOrder($dirName),
            summary: $meta['summary'] ?? '',
            body: $indexData['body'],
            url: route('library.books.show', $slug),
            parts: $parts->sortBy('order')->values(),
            filePath: $dirPath.'/_index.md',
        );
    }

    private function buildPart(string $dirPath, string $bookSlug, string $parentVisibility): ?PartDTO
    {
        $dirName = basename($dirPath);
        $slug = $this->toSlug($dirName);
        $indexData = $this->parseFile($dirPath.'/_index.md');
        $meta = $indexData['meta'];
        $visibility = $meta['visibility'] ?? $parentVisibility;

        $chapters = collect();
        foreach ($this->getSortedDirectories($dirPath) as $chapterDir) {
            $chapter = $this->buildChapter($chapterDir, $bookSlug, $slug, $visibility);
            if ($chapter) {
                $chapters->push($chapter);
            }
        }

        return new PartDTO(
            title: $meta['title'] ?? $this->slugToTitle($slug),
            slug: $slug,
            order: $meta['order'] ?? $this->extractOrder($dirName),
            summary: $meta['summary'] ?? '',
            body: $indexData['body'],
            url: route('library.books.part', [$bookSlug, $slug]),
            visibility: $visibility,
            chapters: $chapters->sortBy('order')->values(),
            filePath: $dirPath.'/_index.md',
        );
    }

    private function buildChapter(string $dirPath, string $bookSlug, string $partSlug, string $parentVisibility): ?ChapterDTO
    {
        $dirName = basename($dirPath);
        $slug = $this->toSlug($dirName);
        $indexData = $this->parseFile($dirPath.'/_index.md');
        $meta = $indexData['meta'];
        $visibility = $meta['visibility'] ?? $parentVisibility;

        $pages = collect();
        foreach ($this->getSortedFiles($dirPath) as $file) {
            $page = $this->buildPage($file, $bookSlug, $partSlug, $slug, $visibility);
            if ($page) {
                $pages->push($page);
            }
        }

        return new ChapterDTO(
            title: $meta['title'] ?? $this->slugToTitle($slug),
            slug: $slug,
            order: $meta['order'] ?? $this->extractOrder($dirName),
            summary: $meta['summary'] ?? '',
            body: $indexData['body'],
            url: route('library.books.chapter', [$bookSlug, $partSlug, $slug]),
            visibility: $visibility,
            pages: $pages->sortBy('order')->values(),
            filePath: $dirPath.'/_index.md',
        );
    }

    private function buildPage(string $filePath, string $bookSlug, string $partSlug, string $chapterSlug, string $parentVisibility): ?PageDTO
    {
        $fileName = basename($filePath);
        $slug = $this->toSlug($fileName);
        $parsed = $this->parseFile($filePath);
        $meta = $parsed['meta'];
        $visibility = $meta['visibility'] ?? $parentVisibility;

        return new PageDTO(
            title: $meta['title'] ?? $this->slugToTitle($slug),
            slug: $slug,
            visibility: $visibility,
            order: $meta['order'] ?? $this->extractOrder($fileName),
            summary: $meta['summary'] ?? '',
            filePath: $filePath,
            body: $parsed['body'],
            url: route('library.books.page', [$bookSlug, $partSlug, $chapterSlug, $slug]),
        );
    }

    private function buildGuide(string $dirPath): ?GuideDTO
    {
        $dirName = basename($dirPath);
        $slug = $this->toSlug($dirName);
        $indexData = $this->parseFile($dirPath.'/_index.md');
        $meta = $indexData['meta'];
        $visibility = $meta['visibility'] ?? 'public';

        $pages = collect();
        foreach ($this->getSortedFiles($dirPath) as $file) {
            $page = $this->buildGuidePage($file, $slug, $visibility);
            if ($page) {
                $pages->push($page);
            }
        }

        return new GuideDTO(
            title: $meta['title'] ?? $this->slugToTitle($slug),
            slug: $slug,
            visibility: $visibility,
            order: $meta['order'] ?? $this->extractOrder($dirName),
            summary: $meta['summary'] ?? '',
            body: $indexData['body'],
            url: route('library.guides.show', $slug),
            pages: $pages->sortBy('order')->values(),
            filePath: $dirPath.'/_index.md',
        );
    }

    private function buildGuidePage(string $filePath, string $guideSlug, string $parentVisibility): ?PageDTO
    {
        $fileName = basename($filePath);
        $slug = $this->toSlug($fileName);
        $parsed = $this->parseFile($filePath);
        $meta = $parsed['meta'];
        $visibility = $meta['visibility'] ?? $parentVisibility;

        return new PageDTO(
            title: $meta['title'] ?? $this->slugToTitle($slug),
            slug: $slug,
            visibility: $visibility,
            order: $meta['order'] ?? $this->extractOrder($fileName),
            summary: $meta['summary'] ?? '',
            filePath: $filePath,
            body: $parsed['body'],
            url: route('library.guides.page', [$guideSlug, $slug]),
        );
    }

    // ─── Helpers ─────────────────────────────────────────────────

    private function toSlug(string $filenameOrDir): string
    {
        $name = preg_replace('/^\d+-/', '', $filenameOrDir);

        return str_replace('.md', '', $name);
    }

    private function extractOrder(string $filenameOrDir): int
    {
        if (preg_match('/^(\d+)-/', $filenameOrDir, $matches)) {
            return (int) $matches[1];
        }

        return 99;
    }

    private function slugToTitle(string $slug): string
    {
        return str_replace('-', ' ', ucfirst($slug));
    }

    private function getSortedDirectories(string $path): array
    {
        if (! is_dir($path)) {
            return [];
        }

        $dirs = [];
        foreach (scandir($path) as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $fullPath = $path.'/'.$item;
            if (is_dir($fullPath)) {
                $dirs[] = $fullPath;
            }
        }

        usort($dirs, fn ($a, $b) => $this->extractOrder(basename($a)) <=> $this->extractOrder(basename($b)));

        return $dirs;
    }

    private function getSortedFiles(string $path): array
    {
        if (! is_dir($path)) {
            return [];
        }

        $files = [];
        foreach (scandir($path) as $item) {
            if ($item === '.' || $item === '..' || $item === '_index.md') {
                continue;
            }
            $fullPath = $path.'/'.$item;
            if (is_file($fullPath) && str_ends_with($item, '.md')) {
                $files[] = $fullPath;
            }
        }

        usort($files, fn ($a, $b) => $this->extractOrder(basename($a)) <=> $this->extractOrder(basename($b)));

        return $files;
    }

    private function getFlattenedPages(string $type, string $topSlug): Collection
    {
        if ($type === 'book') {
            $book = $this->getBook($topSlug);
            if (! $book) {
                return collect();
            }

            $pages = collect();
            foreach ($book->parts as $part) {
                foreach ($part->chapters as $chapter) {
                    foreach ($chapter->pages as $page) {
                        $pages->push($page);
                    }
                }
            }

            return $pages;
        }

        if ($type === 'guide') {
            $guide = $this->getGuide($topSlug);

            return $guide ? $guide->pages : collect();
        }

        return collect();
    }

    private function buildEditableTreeRecursive(string $path, string $relativePath): array
    {
        $items = [];

        // Add _index.md if it exists
        $indexPath = $path.'/_index.md';
        if (file_exists($indexPath)) {
            $parsed = $this->parseFile($indexPath);
            $items[] = [
                'title' => $parsed['meta']['title'] ?? '_index',
                'slug' => '_index',
                'type' => 'file',
                'path' => $relativePath.'/_index.md',
                'children' => [],
            ];
        }

        // Add subdirectories
        foreach ($this->getSortedDirectories($path) as $dir) {
            $dirName = basename($dir);
            $slug = $this->toSlug($dirName);
            $childRelative = $relativePath.'/'.$dirName;
            $indexData = $this->parseFile($dir.'/_index.md');

            $items[] = [
                'title' => $indexData['meta']['title'] ?? $this->slugToTitle($slug),
                'slug' => $slug,
                'type' => 'directory',
                'path' => $childRelative,
                'children' => $this->buildEditableTreeRecursive($dir, $childRelative),
            ];
        }

        // Add regular files
        foreach ($this->getSortedFiles($path) as $file) {
            $fileName = basename($file);
            $slug = $this->toSlug($fileName);
            $parsed = $this->parseFile($file);

            $items[] = [
                'title' => $parsed['meta']['title'] ?? $this->slugToTitle($slug),
                'slug' => $slug,
                'type' => 'file',
                'path' => $relativePath.'/'.$fileName,
                'children' => [],
            ];
        }

        return $items;
    }

    private function buildFileContent(array $meta, string $body): string
    {
        $yaml = Yaml::dump($meta, 2, 2);

        return "---\n{$yaml}---\n\n{$body}\n";
    }

    private function clearCache(): void
    {
        $this->booksCache = null;
        $this->guidesCache = null;
    }
}
