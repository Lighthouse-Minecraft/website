<?php

namespace Database\Factories;

use App\Models\BackgroundCheck;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\BackgroundCheckDocument>
 */
class BackgroundCheckDocumentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'background_check_id' => BackgroundCheck::factory(),
            'path' => 'background-checks/'.$this->faker->uuid().'.pdf',
            'original_filename' => $this->faker->word().'-background-check.pdf',
            'uploaded_by_user_id' => User::factory(),
        ];
    }
}
