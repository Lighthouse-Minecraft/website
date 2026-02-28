<?php

namespace App\Models;

use App\Enums\DiscordAccountStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DiscordAccount extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'discord_user_id',
        'username',
        'global_name',
        'avatar_hash',
        'access_token',
        'refresh_token',
        'token_expires_at',
        'status',
        'verified_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => DiscordAccountStatus::class,
            'access_token' => 'encrypted',
            'refresh_token' => 'encrypted',
            'token_expires_at' => 'datetime',
            'verified_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeActive($query): void
    {
        $query->where('status', DiscordAccountStatus::Active);
    }

    public function avatarUrl(): string
    {
        if ($this->avatar_hash) {
            return "https://cdn.discordapp.com/avatars/{$this->discord_user_id}/{$this->avatar_hash}.png?size=64";
        }

        $index = intval($this->discord_user_id) % 5;

        return "https://cdn.discordapp.com/embed/avatars/{$index}.png";
    }

    public function displayName(): string
    {
        return $this->global_name ?? $this->username;
    }
}
