<?php

namespace App\Models;

use App\Enums\StaffDepartment;
use App\Enums\StaffRank;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StaffPosition extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'department',
        'rank',
        'description',
        'responsibilities',
        'requirements',
        'user_id',
        'sort_order',
        'accepting_applications',
    ];

    protected function casts(): array
    {
        return [
            'department' => StaffDepartment::class,
            'rank' => StaffRank::class,
            'accepting_applications' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isVacant(): bool
    {
        return $this->user_id === null;
    }

    public function isFilled(): bool
    {
        return $this->user_id !== null;
    }

    public function scopeVacant($query)
    {
        return $query->whereNull('user_id');
    }

    public function scopeFilled($query)
    {
        return $query->whereNotNull('user_id');
    }

    public function scopeInDepartment($query, StaffDepartment $department)
    {
        return $query->where('department', $department);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('rank', 'desc')->orderBy('title');
    }

    public function applications(): HasMany
    {
        return $this->hasMany(StaffApplication::class);
    }

    public function applicationQuestions(): HasMany
    {
        return $this->hasMany(ApplicationQuestion::class);
    }

    public function isAcceptingApplications(): bool
    {
        return $this->accepting_applications;
    }

    public function scopeAcceptingApplications($query)
    {
        return $query->where('accepting_applications', true);
    }
}
