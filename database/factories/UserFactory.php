<?php

namespace Database\Factories;

use App\Enums\MembershipLevel;
use App\Enums\StaffDepartment;
use App\Enums\StaffRank;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use App\Models\Role;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'membership_level' => MembershipLevel::Resident,
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function admin(): self
    {
        return $this->afterCreating(function ($user) {
            $role = \App\Models\Role::firstOrCreate(['name' => 'Admin']);
            $user->roles()->attach($role);
        });
    }

    public function withRole(string $roleName): self
    {
        return $this->afterCreating(function ($user) use ($roleName) {
            $role = Role::firstOrCreate(['name' => $roleName]);
            $user->roles()->attach($role);
        });
    }

    public function withMembershipLevel(MembershipLevel $level): self
    {
        return $this->state(fn (array $attributes) => [
            'membership_level' => $level,
        ]);
    }

    public function withStaffPosition(StaffDepartment $department, StaffRank $rank, ?string $title = null): self
    {
        return $this->afterCreating(function ($user) use ($department, $rank, $title) {
            $user->staff_department = $department;
            $user->staff_rank = $rank;
            $user->staff_title = $title;
            $user->save();
        });
    }
}
