<?php

namespace App\Actions;

use App\Models\Announcement;
use App\Services\DiscordApiService;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;

class PostAnnouncementToDiscord
{
    use AsAction;

    public function handle(Announcement $announcement): bool
    {
        $channelId = config('services.discord.announcements_channel_id');

        if (! $channelId) {
            return false;
        }

        $url = route('dashboard');
        $body = $this->formatForDiscord($announcement->content);
        $content = "## {$announcement->title}\n\n{$body}\n\n{$url}";

        // Discord message limit is 2000 characters
        if (mb_strlen($content) > 2000) {
            $suffix = "...\n\n{$url}";
            $available = max(0, 2000 - mb_strlen($suffix));
            $content = mb_substr($content, 0, $available).$suffix;
        }

        try {
            $sent = app(DiscordApiService::class)->sendChannelMessage($channelId, $content);

            if (! $sent) {
                Log::warning('Failed to post announcement to Discord channel', [
                    'announcement_id' => $announcement->id,
                ]);
            }

            return $sent;
        } catch (\Exception $e) {
            Log::warning('Failed to post announcement to Discord channel', [
                'announcement_id' => $announcement->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Convert HTML content to Discord-compatible markdown.
     *
     * Converts headings, bold, italic, underline, strikethrough, and links
     * to Discord markdown. Strips remaining HTML and collapses whitespace.
     */
    private function formatForDiscord(string $html): string
    {
        // Convert <br> tags to newlines
        $text = preg_replace('/<br\s*\/?>/i', "\n", $html);

        // Convert h1-h3 to Discord markdown headings
        $text = preg_replace_callback('/<h([1-3])[^>]*>(.*?)<\/h\1>/is', function ($matches) {
            $prefix = str_repeat('#', (int) $matches[1]);

            return "\n{$prefix} ".trim(strip_tags($matches[2]))."\n";
        }, $text);

        // Convert <a href="url">text</a> to Discord markdown [text](url)
        $text = preg_replace_callback('/<a\s[^>]*href=["\']([^"\']+)["\'][^>]*>(.*?)<\/a>/is', function ($matches) {
            $url = $matches[1];
            $linkText = trim(strip_tags($matches[2]));

            return "[{$linkText}]({$url})";
        }, $text);

        // Convert bold tags to **text**
        $text = preg_replace('/<(strong|bold|b)\b[^>]*>(.*?)<\/\1>/is', '**$2**', $text);

        // Convert italic tags to *text*
        $text = preg_replace('/<(em|i)\b[^>]*>(.*?)<\/\1>/is', '*$2*', $text);

        // Convert underline tags to __text__
        $text = preg_replace('/<u\b[^>]*>(.*?)<\/u>/is', '__$1__', $text);

        // Convert strikethrough tags to ~~text~~
        $text = preg_replace('/<(s|strike|del)\b[^>]*>(.*?)<\/\1>/is', '~~$2~~', $text);

        // Add newlines before/after block-level elements to preserve paragraph breaks
        $text = preg_replace('/<\/(p|div|h[4-6]|li|tr|blockquote)>/i', "\n", $text);
        $text = preg_replace('/<(p|div|h[4-6]|li|tr|blockquote)[\s>]/i', "\n", $text);

        // Strip all remaining HTML tags
        $text = strip_tags($text);

        // Decode HTML entities
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Collapse runs of 3+ newlines down to 2
        $text = preg_replace("/\n{3,}/", "\n\n", $text);

        return trim($text);
    }
}
