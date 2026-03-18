<?php

use App\Models\ApplicationQuestion;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $questions = [
            ['question_text' => 'Full Name', 'type' => 'short_text', 'category' => 'core', 'sort_order' => 0],
            ['question_text' => 'Tell us about your relationship with God.', 'type' => 'short_text', 'category' => 'core', 'sort_order' => 1],
            ['question_text' => 'What are your thoughts on the Lighthouse Mission?', 'type' => 'short_text', 'category' => 'core', 'sort_order' => 1],
            ['question_text' => 'How well do you work with a team? Please share an example.', 'type' => 'short_text', 'category' => 'core', 'sort_order' => 2],
            ['question_text' => 'How comfortable are you working independently? Please share an example.', 'type' => 'short_text', 'category' => 'core', 'sort_order' => 2],
            ['question_text' => 'Why do you want to serve in this position within Lighthouse?', 'type' => 'long_text', 'category' => 'core', 'sort_order' => 3],
            ['question_text' => 'What are your plans for this position? Do you have ideas for how to make this department better?', 'type' => 'long_text', 'category' => 'core', 'sort_order' => 3],
            ['question_text' => 'If you saw someone, even a friend, breaking the rules in the community, how would you handle it?', 'type' => 'long_text', 'category' => 'core', 'sort_order' => 4],
            ['question_text' => 'What strengths or skills would you bring to the Lighthouse staff team?', 'type' => 'short_text', 'category' => 'core', 'sort_order' => 4],
            ['question_text' => 'Is there anything else you\'d like the staff team to know about you?', 'type' => 'long_text', 'category' => 'core', 'sort_order' => 30],
            ['question_text' => 'Officers are the leaders of their department and have to manage the Crew Members within that department. Are you comfortable in this role? Why or why not?', 'type' => 'long_text', 'category' => 'officer', 'sort_order' => 5],
            ['question_text' => 'Our entire Officer staff must have background checks. Do you agree to having a background check done?', 'type' => 'yes_no', 'category' => 'officer', 'sort_order' => 10],
            ['question_text' => 'Some crew positions require a background check. If asked, are you willing to submit to a background check?', 'type' => 'yes_no', 'category' => 'crew_member', 'sort_order' => 10],
        ];

        foreach ($questions as $question) {
            ApplicationQuestion::firstOrCreate(
                ['question_text' => $question['question_text']],
                array_merge($question, ['is_active' => true]),
            );
        }
    }

    public function down(): void
    {
        ApplicationQuestion::whereIn('question_text', [
            'Full Name',
            'Tell us about your relationship with God.',
            'What are your thoughts on the Lighthouse Mission?',
            'How well do you work with a team? Please share an example.',
            'How comfortable are you working independently? Please share an example.',
            'Why do you want to serve in this position within Lighthouse?',
            'What are your plans for this position? Do you have ideas for how to make this department better?',
            'If you saw someone, even a friend, breaking the rules in the community, how would you handle it?',
            'What strengths or skills would you bring to the Lighthouse staff team?',
            'Is there anything else you\'d like the staff team to know about you?',
            'Officers are the leaders of their department and have to manage the Crew Members within that department. Are you comfortable in this role? Why or why not?',
            'Our entire Officer staff must have background checks. Do you agree to having a background check done?',
            'Some crew positions require a background check. If asked, are you willing to submit to a background check?',
        ])->delete();
    }
};
