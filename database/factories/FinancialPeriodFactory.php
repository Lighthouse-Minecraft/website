<?php

namespace Database\Factories;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

class FinancialPeriodFactory extends Factory
{
    public function definition(): array
    {
        $year = fake()->numberBetween(2024, 2028);
        $month = fake()->numberBetween(1, 12);
        $start = Carbon::create($year, $month, 1);

        return [
            'fiscal_year' => $year,
            'month_number' => $month,
            'name' => $start->format('F Y'),
            'start_date' => $start->toDateString(),
            'end_date' => $start->copy()->endOfMonth()->toDateString(),
            'status' => 'open',
            'closed_at' => null,
            'closed_by_id' => null,
        ];
    }

    public function open(): static
    {
        return $this->state(['status' => 'open']);
    }

    public function closed(): static
    {
        return $this->state([
            'status' => 'closed',
            'closed_at' => now(),
        ]);
    }

    public function reconciling(): static
    {
        return $this->state(['status' => 'reconciling']);
    }
}
