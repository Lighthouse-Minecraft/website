<?php

namespace Database\Factories;

use App\Models\BoardMember;
use Illuminate\Database\Eloquent\Factories\Factory;

class BoardMemberFactory extends Factory
{
    protected $model = BoardMember::class;

    public function definition(): array
    {
        return [
            'display_name' => $this->faker->name(),
            'title' => null,
            'user_id' => null,
            'bio' => $this->faker->paragraph(),
            'photo_path' => null,
            'sort_order' => 0,
        ];
    }

    public function withTitle(string $title): self
    {
        return $this->state(fn () => ['title' => $title]);
    }

    public function linkedTo(int $userId): self
    {
        return $this->state(fn () => ['user_id' => $userId]);
    }
}
