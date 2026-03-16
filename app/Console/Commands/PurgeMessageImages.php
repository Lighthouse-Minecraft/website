<?php

namespace App\Console\Commands;

use App\Enums\ThreadType;
use App\Models\Message;
use App\Models\SiteConfig;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class PurgeMessageImages extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'messages:purge-images';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Purge images from messages in closed tickets and locked discussions after the configured retention period';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $purgeDays = (int) SiteConfig::getValue('message_image_purge_days', '60');
        $cutoff = now()->subDays($purgeDays);
        $disk = Storage::disk(config('filesystems.public_disk'));

        $messages = Message::whereNotNull('image_path')
            ->where('image_was_purged', false)
            ->whereHas('thread', function ($query) use ($cutoff) {
                $query->where(function ($q) use ($cutoff) {
                    // Closed tickets
                    $q->where('type', ThreadType::Ticket)
                        ->whereNotNull('closed_at')
                        ->where('closed_at', '<=', $cutoff);
                })->orWhere(function ($q) use ($cutoff) {
                    // Locked discussions
                    $q->where('type', ThreadType::Topic)
                        ->where('is_locked', true)
                        ->whereNotNull('locked_at')
                        ->where('locked_at', '<=', $cutoff);
                });
            })
            ->get();

        $count = 0;

        foreach ($messages as $message) {
            if ($message->image_path && $disk->exists($message->image_path)) {
                $disk->delete($message->image_path);
            }

            $message->update([
                'image_path' => null,
                'image_was_purged' => true,
            ]);

            $count++;
        }

        $this->info("Purged {$count} message image(s).");

        return self::SUCCESS;
    }
}
