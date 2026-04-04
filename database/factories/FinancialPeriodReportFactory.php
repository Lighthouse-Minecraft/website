<?php

namespace Database\Factories;

use App\Models\FinancialPeriodReport;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\FinancialPeriodReport>
 */
class FinancialPeriodReportFactory extends Factory
{
    protected $model = FinancialPeriodReport::class;

    public function definition(): array
    {
        return [
            'month' => now()->startOfMonth()->toDateString(),
            'published_at' => null,
            'published_by' => null,
        ];
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'published_at' => now(),
            'published_by' => User::factory(),
        ]);
    }

    public function forMonth(string $month): static
    {
        return $this->state(fn (array $attributes) => [
            'month' => $month,
        ]);
    }
}
