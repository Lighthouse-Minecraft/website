<?php

namespace Database\Factories;

use App\Enums\QuestionSuggestionStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\QuestionSuggestion>
 */
class QuestionSuggestionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'question_text' => $this->faker->sentence().'?',
            'status' => QuestionSuggestionStatus::Suggested,
        ];
    }
}
