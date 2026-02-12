<?php

namespace Database\Factories;

use App\Enums\MessageKind;
use App\Models\Thread;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Message>
 */
class MessageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'thread_id' => Thread::factory(),
            'user_id' => User::factory(),
            'body' => $this->faker->paragraph(),
            'kind' => MessageKind::Message,
        ];
    }

    public function system(): static
    {
        return $this->state(fn (array $attributes) => [
            'kind' => MessageKind::System,
        ]);
    }

    public function internalNote(): static
    {
        return $this->state(fn (array $attributes) => [
            'kind' => MessageKind::InternalNote,
        ]);
    }

    public function forThread(Thread $thread): static
    {
        return $this->state(fn (array $attributes) => [
            'thread_id' => $thread->id,
        ]);
    }

    public function byUser(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $user->id,
        ]);
    }
}
