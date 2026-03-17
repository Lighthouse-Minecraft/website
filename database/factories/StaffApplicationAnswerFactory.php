<?php

namespace Database\Factories;

use App\Models\ApplicationQuestion;
use App\Models\StaffApplication;
use App\Models\StaffApplicationAnswer;
use Illuminate\Database\Eloquent\Factories\Factory;

class StaffApplicationAnswerFactory extends Factory
{
    protected $model = StaffApplicationAnswer::class;

    public function definition(): array
    {
        return [
            'staff_application_id' => StaffApplication::factory(),
            'application_question_id' => ApplicationQuestion::factory(),
            'answer' => $this->faker->sentence(),
        ];
    }
}
