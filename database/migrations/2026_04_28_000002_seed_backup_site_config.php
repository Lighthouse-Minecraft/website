<?php

use App\Models\SiteConfig;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        SiteConfig::setValue('backup.local_retention_days', '7');
        SiteConfig::query()->where('key', 'backup.local_retention_days')->update([
            'description' => 'Number of days to retain local backup files before automatic cleanup',
        ]);

        SiteConfig::setValue('backup.offline_during_backup', 'false');
        SiteConfig::query()->where('key', 'backup.offline_during_backup')->update([
            'description' => 'Whether to put the site into maintenance mode during a backup operation',
        ]);

        SiteConfig::setValue('backup.offline_during_restore', 'true');
        SiteConfig::query()->where('key', 'backup.offline_during_restore')->update([
            'description' => 'Whether to put the site into maintenance mode during a restore operation',
        ]);
    }

    public function down(): void
    {
        SiteConfig::query()->whereIn('key', [
            'backup.local_retention_days',
            'backup.offline_during_backup',
            'backup.offline_during_restore',
        ])->delete();
    }
};
