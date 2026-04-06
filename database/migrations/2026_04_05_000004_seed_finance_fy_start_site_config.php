<?php

use App\Models\SiteConfig;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        SiteConfig::setValue('finance_fy_start_month', '10');
        SiteConfig::query()->where('key', 'finance_fy_start_month')->update([
            'description' => 'Fiscal year start month number (1–12). Default: 10 (October).',
        ]);
    }

    public function down(): void
    {
        SiteConfig::query()->where('key', 'finance_fy_start_month')->delete();
    }
};
