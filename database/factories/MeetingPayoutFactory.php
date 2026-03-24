<?php

namespace Database\Factories;

use App\Models\Meeting;
use App\Models\MeetingPayout;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\MeetingPayout>
 */
class MeetingPayoutFactory extends Factory
{
    protected $model = MeetingPayout::class;

    public function definition(): array
    {
        return [
            'meeting_id' => Meeting::factory(),
            'user_id' => User::factory(),
            'minecraft_account_id' => null,
            'amount' => 0,
            'status' => 'skipped',
            'skip_reason' => null,
        ];
    }

    public function paid(int $amount = 50): static
    {
        return $this->state(fn (array $attributes) => [
            'amount' => $amount,
            'status' => 'paid',
            'skip_reason' => null,
        ]);
    }

    public function skipped(string $reason = 'Form not submitted'): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'skipped',
            'skip_reason' => $reason,
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'failed',
            'skip_reason' => null,
        ]);
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
            'skip_reason' => null,
        ]);
    }
}
