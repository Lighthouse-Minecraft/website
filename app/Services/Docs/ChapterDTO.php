<?php

namespace App\Services\Docs;

use Illuminate\Support\Collection;

class ChapterDTO
{
    public function __construct(
        public string $title,
        public string $slug,
        public int $order,
        public string $summary,
        public string $body,
        public string $url,
        public string $visibility,
        public Collection $pages,
        public string $filePath = '',
        public ?string $lastUpdated = null,
    ) {}
}
