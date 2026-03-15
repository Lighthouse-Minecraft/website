<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $configs = [
            [
                'key' => 'registration_question',
                'value' => '',
                'description' => 'Question shown to new users during registration. Leave empty to skip.',
            ],
            [
                'key' => 'ai_meeting_notes_prompt',
                'value' => config('lighthouse.ai.meeting_notes_system_prompt', ''),
                'description' => 'System prompt for AI meeting notes summarization.',
            ],
            [
                'key' => 'donation_goal',
                'value' => (string) config('lighthouse.donation_goal', 60),
                'description' => 'Monthly donation goal amount in dollars.',
            ],
            [
                'key' => 'donation_current_month_amount',
                'value' => (string) config('lighthouse.donation_current_month_amount', 0),
                'description' => 'Current month donation amount received.',
            ],
            [
                'key' => 'donation_current_month_name',
                'value' => config('lighthouse.donation_current_month_name', ''),
                'description' => 'Current month name for donation display.',
            ],
            [
                'key' => 'donation_last_month_amount',
                'value' => (string) config('lighthouse.donation_last_month_amount', 0),
                'description' => 'Last month donation amount received.',
            ],
            [
                'key' => 'donation_last_month_name',
                'value' => config('lighthouse.donation_last_month_name', ''),
                'description' => 'Last month name for donation display.',
            ],
        ];

        foreach ($configs as $config) {
            DB::table('site_configs')->insertOrIgnore([
                'key' => $config['key'],
                'value' => $config['value'],
                'description' => $config['description'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('site_configs')->whereIn('key', [
            'registration_question',
            'ai_meeting_notes_prompt',
            'donation_goal',
            'donation_current_month_amount',
            'donation_current_month_name',
            'donation_last_month_amount',
            'donation_last_month_name',
        ])->delete();
    }
};
