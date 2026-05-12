<?php

namespace Database\Factories;

use App\Enums\ApplicationStatus;
use App\Models\StaffApplication;
use App\Models\StaffPosition;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class StaffApplicationFactory extends Factory
{
    protected $model = StaffApplication::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'staff_position_id' => StaffPosition::factory(),
            'status' => ApplicationStatus::Submitted,
            'reviewer_notes' => null,
            'conditions' => null,
            'reviewed_by' => null,
        ];
    }

    public function underReview(): self
    {
        return $this->state(fn () => ['status' => ApplicationStatus::UnderReview]);
    }

    public function interview(): self
    {
        return $this->state(fn () => ['status' => ApplicationStatus::Interview]);
    }

    public function backgroundCheck(): self
    {
        return $this->state(fn () => ['status' => ApplicationStatus::BackgroundCheck]);
    }

    public function approved(): self
    {
        return $this->state(fn () => ['status' => ApplicationStatus::Approved]);
    }

    public function denied(): self
    {
        return $this->state(fn () => ['status' => ApplicationStatus::Denied]);
    }

    public function withdrawn(): self
    {
        return $this->state(fn () => ['status' => ApplicationStatus::Withdrawn]);
    }
}
