<?php

namespace App\Services\Docs;

use Illuminate\Support\Collection;

class GuideDTO
{
    public function __construct(
        public string $title,
        public string $slug,
        public string $visibility,
        public int $order,
        public string $summary,
        public string $body,
        public string $url,
        public Collection $pages,
    ) {}
}
