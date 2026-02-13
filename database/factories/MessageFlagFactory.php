<?php

namespace Database\Factories;

use App\Enums\MessageFlagStatus;
use App\Models\Message;
use App\Models\Thread;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\MessageFlag>
 */
class MessageFlagFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $message = Message::factory()->create();

        return [
            'message_id' => $message->id,
            'thread_id' => $message->thread_id,
            'flagged_by_user_id' => User::factory(),
            'note' => $this->faker->paragraph(),
            'status' => MessageFlagStatus::New,
            'reviewed_by_user_id' => null,
            'reviewed_at' => null,
            'staff_notes' => null,
            'flag_review_ticket_id' => null,
        ];
    }

    public function acknowledged(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => MessageFlagStatus::Acknowledged,
            'reviewed_by_user_id' => User::factory(),
            'reviewed_at' => now(),
            'staff_notes' => $this->faker->paragraph(),
        ]);
    }

    public function forMessage(Message $message): static
    {
        return $this->state(fn (array $attributes) => [
            'message_id' => $message->id,
            'thread_id' => $message->thread_id,
        ]);
    }

    public function withReviewTicket(Thread $thread): static
    {
        return $this->state(fn (array $attributes) => [
            'flag_review_ticket_id' => $thread->id,
        ]);
    }
}
