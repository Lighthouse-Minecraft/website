<?php

namespace Database\Factories;

use App\Enums\ReportLocation;
use App\Enums\ReportSeverity;
use App\Enums\ReportStatus;
use App\Models\DisciplineReport;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class DisciplineReportFactory extends Factory
{
    protected $model = DisciplineReport::class;

    public function definition(): array
    {
        return [
            'subject_user_id' => User::factory(),
            'reporter_user_id' => User::factory(),
            'publisher_user_id' => null,
            'report_category_id' => null,
            'description' => $this->faker->paragraph(),
            'location' => $this->faker->randomElement(ReportLocation::cases()),
            'witnesses' => $this->faker->optional()->sentence(),
            'actions_taken' => $this->faker->sentence(),
            'severity' => ReportSeverity::Minor,
            'status' => ReportStatus::Draft,
            'published_at' => null,
        ];
    }

    public function published(): static
    {
        return $this->state(fn () => [
            'status' => ReportStatus::Published,
            'publisher_user_id' => User::factory(),
            'published_at' => now(),
        ]);
    }

    public function trivial(): static
    {
        return $this->state(fn () => ['severity' => ReportSeverity::Trivial]);
    }

    public function minor(): static
    {
        return $this->state(fn () => ['severity' => ReportSeverity::Minor]);
    }

    public function moderate(): static
    {
        return $this->state(fn () => ['severity' => ReportSeverity::Moderate]);
    }

    public function major(): static
    {
        return $this->state(fn () => ['severity' => ReportSeverity::Major]);
    }

    public function severe(): static
    {
        return $this->state(fn () => ['severity' => ReportSeverity::Severe]);
    }

    public function forSubject(User $user): static
    {
        return $this->state(fn () => ['subject_user_id' => $user->id]);
    }

    public function byReporter(User $user): static
    {
        return $this->state(fn () => ['reporter_user_id' => $user->id]);
    }

    public function withCategory(\App\Models\ReportCategory $category): static
    {
        return $this->state(fn () => ['report_category_id' => $category->id]);
    }

    public function publishedDaysAgo(int $days): static
    {
        return $this->state(fn () => [
            'status' => ReportStatus::Published,
            'publisher_user_id' => User::factory(),
            'published_at' => now()->subDays($days),
        ]);
    }
}
