<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserRuleAgreement extends Model
{
    protected $fillable = ['user_id', 'rule_version_id', 'agreed_at', 'proxy_user_id'];

    protected $casts = [
        'agreed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function ruleVersion(): BelongsTo
    {
        return $this->belongsTo(RuleVersion::class);
    }

    public function proxyUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'proxy_user_id');
    }

    public function isProxy(): bool
    {
        return $this->proxy_user_id !== null;
    }
}
