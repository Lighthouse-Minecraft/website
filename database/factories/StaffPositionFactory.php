<?php

namespace Database\Factories;

use App\Enums\StaffDepartment;
use App\Enums\StaffRank;
use App\Models\StaffPosition;
use Illuminate\Database\Eloquent\Factories\Factory;

class StaffPositionFactory extends Factory
{
    protected $model = StaffPosition::class;

    public function definition(): array
    {
        return [
            'title' => $this->faker->jobTitle(),
            'department' => $this->faker->randomElement(StaffDepartment::cases()),
            'rank' => $this->faker->randomElement([StaffRank::Officer, StaffRank::CrewMember]),
            'description' => $this->faker->sentence(),
            'responsibilities' => null,
            'requirements' => null,
            'user_id' => null,
            'sort_order' => 0,
        ];
    }

    public function officer(): self
    {
        return $this->state(fn () => ['rank' => StaffRank::Officer]);
    }

    public function crewMember(): self
    {
        return $this->state(fn () => ['rank' => StaffRank::CrewMember]);
    }

    public function inDepartment(StaffDepartment $department): self
    {
        return $this->state(fn () => ['department' => $department]);
    }

    public function assignedTo($userId): self
    {
        return $this->state(fn () => ['user_id' => $userId]);
    }
}
