<?php

namespace Database\Factories;

use App\Models\ParentChildLink;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ParentChildLinkFactory extends Factory
{
    protected $model = ParentChildLink::class;

    public function definition(): array
    {
        return [
            'parent_user_id' => User::factory(),
            'child_user_id' => User::factory(),
        ];
    }
}
