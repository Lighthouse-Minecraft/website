<?php

namespace App\Services\Docs;

use Illuminate\Support\Collection;

class PartDTO
{
    public function __construct(
        public string $title,
        public string $slug,
        public int $order,
        public string $summary,
        public string $body,
        public string $url,
        public string $visibility,
        public Collection $chapters,
    ) {}
}
