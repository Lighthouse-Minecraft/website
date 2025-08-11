<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\MeetingNote>
 */
class MeetingNoteFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'created_by' => \App\Models\User::factory(),
            'section_key' => $this->faker->word,
            'meeting_id' => \App\Models\Meeting::factory(),
            'content' => $this->faker->paragraph(),
            'locked_by' => null,
            'locked_at' => null,
            'lock_updated_at' => null,
        ];
    }

    public function withContent(string $content): Factory
    {
        return $this->state(function (array $attributes) use ($content) {
            return [
                'content' => $content,
            ];
        });
    }

    public function withSectionKey(string $sectionKey): Factory
    {
        return $this->state(function (array $attributes) use ($sectionKey) {
            return [
                'section_key' => $sectionKey,
            ];
        });
    }

    public function withMeeting(\App\Models\Meeting $meeting): Factory
    {
        return $this->state(function (array $attributes) use ($meeting) {
            return [
                'meeting_id' => $meeting->id,
            ];
        });
    }

    public function withCreator(\App\Models\User $user): Factory
    {
        return $this->state(function (array $attributes) use ($user) {
            return [
                'created_by' => $user->id,
            ];
        });
    }

    public function withLock(\App\Models\User $user): Factory
    {
        return $this->state(function (array $attributes) use ($user) {
            return [
                'locked_by' => $user->id,
                'locked_at' => now(),
                'lock_updated_at' => now(),
            ];
        });
    }
}
