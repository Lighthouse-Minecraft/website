<?php

use App\Models\SiteConfig;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        SiteConfig::setValue('meeting_payout_jr_crew', '50');
        SiteConfig::query()->where('key', 'meeting_payout_jr_crew')->update([
            'description' => 'Lumen payout amount for Jr Crew after a staff meeting (0 to disable)',
        ]);

        SiteConfig::setValue('meeting_payout_crew_member', '75');
        SiteConfig::query()->where('key', 'meeting_payout_crew_member')->update([
            'description' => 'Lumen payout amount for Crew Members after a staff meeting (0 to disable)',
        ]);

        SiteConfig::setValue('meeting_payout_officer', '100');
        SiteConfig::query()->where('key', 'meeting_payout_officer')->update([
            'description' => 'Lumen payout amount for Officers after a staff meeting (0 to disable)',
        ]);
    }

    public function down(): void
    {
        SiteConfig::query()->whereIn('key', [
            'meeting_payout_jr_crew',
            'meeting_payout_crew_member',
            'meeting_payout_officer',
        ])->delete();
    }
};
