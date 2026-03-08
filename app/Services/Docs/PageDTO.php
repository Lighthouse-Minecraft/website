<?php

namespace App\Services\Docs;

class PageDTO
{
    public function __construct(
        public string $title,
        public string $slug,
        public string $visibility,
        public int $order,
        public string $summary,
        public string $filePath,
        public string $body,
        public string $url,
    ) {}

    public function renderedHtml(): string
    {
        return \Illuminate\Support\Str::markdown($this->body, [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);
    }
}
