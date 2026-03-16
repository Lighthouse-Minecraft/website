<?php

namespace Database\Factories;

use App\Models\StaffApplicationAnswer;
use Illuminate\Database\Eloquent\Factories\Factory;

class StaffApplicationAnswerFactory extends Factory
{
    protected $model = StaffApplicationAnswer::class;

    public function definition(): array
    {
        return [
            'answer' => $this->faker->sentence(),
        ];
    }
}
