<?php

namespace Database\Factories;

use App\Models\DisciplineReport;
use Illuminate\Database\Eloquent\Factories\Factory;

class DisciplineReportImageFactory extends Factory
{
    public function definition(): array
    {
        return [
            'discipline_report_id' => DisciplineReport::factory(),
            'path' => 'report-evidence/1/'.$this->faker->uuid().'.jpg',
            'original_filename' => $this->faker->word().'.jpg',
        ];
    }
}
