<?php

namespace Database\Factories;

use App\Models\{Announcement, Comments, User};
use Illuminate\Database\Eloquent\Factories\{Factory};

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Comment>
 */
class CommentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'content' => $this->faker->paragraph,
            'announcement_id' => Announcement::factory(),
            'user_id' => User::factory(),
            'status' => $this->faker->randomElement(['pending', 'approved', 'rejected']),
            'parent_id' => null, // For nested comments, set if needed
            'edited_at' => $this->faker->optional()->dateTime,
        ];
    }
}
