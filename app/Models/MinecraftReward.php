<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MinecraftReward extends Model
{
    protected $fillable = [
        'user_id',
        'minecraft_account_id',
        'reward_name',
        'reward_description',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function minecraftAccount(): BelongsTo
    {
        return $this->belongsTo(MinecraftAccount::class);
    }
}
