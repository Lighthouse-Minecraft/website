<?php

namespace Database\Factories;

use App\Models\Thread;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ThreadParticipant>
 */
class ThreadParticipantFactory extends Factory
{
    /**
     * Provide the factory's default attributes for a ThreadParticipant model.
     *
     * @return array<string, mixed> The model attributes and their default values.
     */
    public function definition(): array
    {
        return [
            'thread_id' => Thread::factory(),
            'user_id' => User::factory(),
            'last_read_at' => null,
            'is_viewer' => false,
        ];
    }

    public function forThread(Thread $thread): static
    {
        return $this->state(fn (array $attributes) => [
            'thread_id' => $thread->id,
        ]);
    }

    public function forUser(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $user->id,
        ]);
    }

    /**
     * Mark the participant as having read the thread by setting `last_read_at` to the current timestamp.
     *
     * @return static The factory instance with `last_read_at` set to now().
     */
    public function read(): static
    {
        return $this->state(fn (array $attributes) => [
            'last_read_at' => now(),
        ]);
    }

    /**
     * Configure the factory state to mark the participant as a viewer.
     *
     * @return static The factory instance with `is_viewer` set to `true`.
     */
    public function viewer(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_viewer' => true,
        ]);
    }
}
