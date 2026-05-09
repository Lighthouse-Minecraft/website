<?php

use App\Models\SiteConfig;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        SiteConfig::setValue('bg_check_no_record_message', 'Waiting for more donations to come in before we can do more background checks');
        SiteConfig::query()->where('key', 'bg_check_no_record_message')->update([
            'description' => 'Tooltip shown on staff directory badge when no terminal background check record exists for the staff member',
        ]);
    }

    public function down(): void
    {
        SiteConfig::query()->where('key', 'bg_check_no_record_message')->delete();
    }
};
