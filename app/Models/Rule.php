<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Rule extends Model
{
    protected $fillable = [
        'rule_category_id',
        'title',
        'description',
        'status',
        'supersedes_rule_id',
        'created_by_user_id',
    ];

    public function ruleCategory(): BelongsTo
    {
        return $this->belongsTo(RuleCategory::class);
    }

    public function supersedes(): BelongsTo
    {
        return $this->belongsTo(Rule::class, 'supersedes_rule_id');
    }

    public function ruleVersions(): BelongsToMany
    {
        return $this->belongsToMany(RuleVersion::class, 'rule_version_rules')
            ->withPivot('deactivate_on_publish');
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
