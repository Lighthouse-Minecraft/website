<?php

namespace Database\Factories;

use App\Models\ReportCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

class ReportCategoryFactory extends Factory
{
    protected $model = ReportCategory::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->word(),
            'color' => $this->faker->randomElement(['red', 'orange', 'yellow', 'green', 'blue', 'indigo', 'purple', 'zinc']),
        ];
    }
}
