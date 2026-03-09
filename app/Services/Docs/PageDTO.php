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
        $body = self::processConfigVariables(self::processWikiLinks($this->body));

        return \Illuminate\Support\Str::markdown($body, [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);
    }

    /**
     * Convert [[path]] and [[path|label]] wiki links to markdown links.
     *
     * Supported formats:
     *   [[books/handbook/part/chapter/page]]
     *   [[books/handbook/part/chapter/page|Display Text]]
     *   [[guides/guide-name/page]]
     *   [[guides/guide-name/page|Display Text]]
     */
    public static function processWikiLinks(string $body): string
    {
        return preg_replace_callback('/\[\[([^\]|]+?)(?:\|([^\]]+?))?\]\]/', function ($matches) {
            $path = trim($matches[1]);
            $label = isset($matches[2]) ? trim($matches[2]) : null;

            $url = '/library/'.ltrim($path, '/');
            $displayText = $label ?? self::labelFromPath($path);

            return "[$displayText]($url)";
        }, $body);
    }

    /**
     * Replace {{config:key}} placeholders with config values.
     *
     * Only whitelisted keys are allowed to prevent leaking secrets.
     */
    public static function processConfigVariables(string $body): string
    {
        return preg_replace_callback('/\{\{config:([a-zA-Z0-9_.]+)\}\}/', function ($matches) {
            $key = trim($matches[1]);

            if (! in_array($key, self::safeConfigKeys())) {
                return $matches[0]; // leave unresolved
            }

            $value = config($key);

            return $value !== null ? (string) $value : $matches[0];
        }, $body);
    }

    private static function safeConfigKeys(): array
    {
        return [
            'lighthouse.max_minecraft_accounts',
            'lighthouse.max_discord_accounts',
            'lighthouse.minecraft_verification_grace_period_minutes',
            'lighthouse.minecraft.server_name',
            'lighthouse.minecraft.server_host',
            'lighthouse.minecraft.server_port_java',
            'lighthouse.minecraft.server_port_bedrock',
            'lighthouse.donation_goal',
            'app.name',
        ];
    }

    private static function labelFromPath(string $path): string
    {
        $last = basename($path);

        return str_replace('-', ' ', ucfirst($last));
    }
}
