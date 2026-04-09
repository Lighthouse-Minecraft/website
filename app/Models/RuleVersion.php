<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class RuleVersion extends Model
{
    protected $fillable = [
        'version_number',
        'status',
        'created_by_user_id',
        'approved_by_user_id',
        'rejection_note',
        'published_at',
    ];

    protected $casts = [
        'published_at' => 'datetime',
    ];

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }

    public function rules(): BelongsToMany
    {
        return $this->belongsToMany(Rule::class, 'rule_version_rules')
            ->withPivot('deactivate_on_publish');
    }

    public function activeRules(): BelongsToMany
    {
        return $this->belongsToMany(Rule::class, 'rule_version_rules')
            ->withPivot('deactivate_on_publish')
            ->wherePivot('deactivate_on_publish', false);
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }

    public static function currentPublished(): ?static
    {
        return static::where('status', 'published')->orderByDesc('version_number')->first();
    }

    public static function currentDraft(): ?static
    {
        return static::whereIn('status', ['draft', 'submitted'])->orderByDesc('version_number')->first();
    }
}
