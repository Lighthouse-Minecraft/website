<?php

namespace Database\Factories;

use App\Enums\StaffDepartment;
use App\Enums\ThreadStatus;
use App\Enums\ThreadSubtype;
use App\Enums\ThreadType;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Thread>
 */
class ThreadFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => ThreadType::Ticket,
            'subtype' => ThreadSubtype::Support,
            'department' => $this->faker->randomElement(StaffDepartment::cases()),
            'subject' => $this->faker->sentence(),
            'status' => ThreadStatus::Open,
            'created_by_user_id' => User::factory(),
            'assigned_to_user_id' => null,
            'is_flagged' => false,
            'has_open_flags' => false,
            'last_message_at' => now(),
        ];
    }

    public function support(): static
    {
        return $this->state(fn (array $attributes) => [
            'subtype' => ThreadSubtype::Support,
        ]);
    }

    public function adminAction(): static
    {
        return $this->state(fn (array $attributes) => [
            'subtype' => ThreadSubtype::AdminAction,
        ]);
    }

    public function moderationFlag(): static
    {
        return $this->state(fn (array $attributes) => [
            'subtype' => ThreadSubtype::ModerationFlag,
            'department' => StaffDepartment::Quartermaster,
        ]);
    }

    public function withDepartment(StaffDepartment $department): static
    {
        return $this->state(fn (array $attributes) => [
            'department' => $department,
        ]);
    }

    public function withStatus(ThreadStatus $status): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => $status,
        ]);
    }

    public function assigned(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'assigned_to_user_id' => $user->id,
        ]);
    }

    public function flagged(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_flagged' => true,
        ]);
    }

    public function withOpenFlags(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_flagged' => true,
            'has_open_flags' => true,
        ]);
    }
}
