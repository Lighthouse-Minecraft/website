<?php

use App\Models\SiteConfig;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        SiteConfig::setValue('ticket_escalation_threshold_minutes', '30');
        SiteConfig::query()->where('key', 'ticket_escalation_threshold_minutes')->update([
            'description' => 'Minutes before an unassigned open ticket is escalated to Ticket Escalation - Receiver role holders (0 to disable)',
        ]);
    }

    public function down(): void
    {
        SiteConfig::query()->where('key', 'ticket_escalation_threshold_minutes')->delete();
    }
};
