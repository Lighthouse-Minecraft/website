<?php

namespace Database\Factories;

use App\Enums\TaskStatus;
use App\Models\Meeting;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Task>
 */
class TaskFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->sentence(3),
            'section_key' => 'command',
            'assigned_meeting_id' => Meeting::factory(),
            'status' => TaskStatus::Pending,
            'created_by' => User::factory(),
        ];
    }

    public function withDepartment(string $department): static
    {
        return $this->state(fn (array $attributes) => [
            'section_key' => $department,
        ]);
    }

    public function withCreator(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'created_by' => $user->id,
        ]);
    }

    public function withMeeting(Meeting $meeting): static
    {
        return $this->state(fn (array $attributes) => [
            'assigned_meeting_id' => $meeting->id,
        ]);
    }
}
