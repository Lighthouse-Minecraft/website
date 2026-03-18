<?php

namespace App\Models;

use App\Enums\ApplicationQuestionCategory;
use App\Enums\ApplicationQuestionType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ApplicationQuestion extends Model
{
    use HasFactory;

    protected $fillable = [
        'question_text',
        'type',
        'category',
        'staff_position_id',
        'select_options',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'type' => ApplicationQuestionType::class,
            'category' => ApplicationQuestionCategory::class,
            'select_options' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function staffPosition(): BelongsTo
    {
        return $this->belongsTo(StaffPosition::class);
    }

    public function answers(): HasMany
    {
        return $this->hasMany(StaffApplicationAnswer::class, 'application_question_id');
    }

    public function isPositionSpecific(): bool
    {
        return $this->category === ApplicationQuestionCategory::PositionSpecific;
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForCategory($query, ApplicationQuestionCategory $category)
    {
        return $query->where('category', $category);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order');
    }
}
