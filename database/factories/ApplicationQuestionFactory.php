<?php

namespace Database\Factories;

use App\Enums\ApplicationQuestionCategory;
use App\Enums\ApplicationQuestionType;
use App\Models\ApplicationQuestion;
use App\Models\StaffPosition;
use Illuminate\Database\Eloquent\Factories\Factory;

class ApplicationQuestionFactory extends Factory
{
    protected $model = ApplicationQuestion::class;

    public function definition(): array
    {
        return [
            'question_text' => $this->faker->sentence().'?',
            'type' => ApplicationQuestionType::ShortText,
            'category' => ApplicationQuestionCategory::Core,
            'staff_position_id' => null,
            'select_options' => null,
            'sort_order' => 0,
            'is_active' => true,
        ];
    }

    public function officer(): self
    {
        return $this->state(fn () => ['category' => ApplicationQuestionCategory::Officer]);
    }

    public function crewMember(): self
    {
        return $this->state(fn () => ['category' => ApplicationQuestionCategory::CrewMember]);
    }

    public function positionSpecific(StaffPosition $position): self
    {
        return $this->state(fn () => [
            'category' => ApplicationQuestionCategory::PositionSpecific,
            'staff_position_id' => $position->id,
        ]);
    }

    public function longText(): self
    {
        return $this->state(fn () => ['type' => ApplicationQuestionType::LongText]);
    }

    public function yesNo(): self
    {
        return $this->state(fn () => ['type' => ApplicationQuestionType::YesNo]);
    }

    public function select(array $options): self
    {
        return $this->state(fn () => [
            'type' => ApplicationQuestionType::Select,
            'select_options' => $options,
        ]);
    }

    public function inactive(): self
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
