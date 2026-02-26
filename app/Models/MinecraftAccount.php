<?php

namespace App\Models;

use App\Enums\MinecraftAccountStatus;
use App\Enums\MinecraftAccountType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MinecraftAccount extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'username',
        'uuid',
        'avatar_url',
        'account_type',
        'status',
        'verified_at',
        'last_username_check_at',
    ];

    protected function casts(): array
    {
        return [
            'account_type' => MinecraftAccountType::class,
            'status' => MinecraftAccountStatus::class,
            'verified_at' => 'datetime',
            'last_username_check_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function rewards(): HasMany
    {
        return $this->hasMany(MinecraftReward::class);
    }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    public function scopeWhereNormalizedUuid(Builder $query, string $uuid): void
    {
        $normalized = str_replace('-', '', $uuid);
        $query->whereRaw("REPLACE(uuid, '-', '') = ?", [$normalized]);
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('status', MinecraftAccountStatus::Active);
    }

    public function scopeVerifying(Builder $query): void
    {
        $query->where('status', MinecraftAccountStatus::Verifying);
    }

    public function scopeCancelled(Builder $query): void
    {
        $query->where('status', MinecraftAccountStatus::Cancelled);
    }

    public function scopeRemoved(Builder $query): void
    {
        $query->where('status', MinecraftAccountStatus::Removed);
    }

    /**
     * Accounts that count toward the user's max-account limit.
     * Active, Verifying, and Banned count. Removed and Cancelled do not.
     */
    public function scopeCountingTowardLimit(Builder $query): void
    {
        $query->whereIn('status', [
            MinecraftAccountStatus::Active,
            MinecraftAccountStatus::Verifying,
            MinecraftAccountStatus::Banned,
        ]);
    }

    // ─── RCON Command Helpers ─────────────────────────────────────────────────

    /**
     * Returns the correct whitelist-add command for this account's type.
     * Java:    "whitelist add <username>"
     * Bedrock: "fwhitelist add <floodgate_uuid>"
     */
    public function whitelistAddCommand(): string
    {
        return match ($this->account_type) {
            MinecraftAccountType::Java => "whitelist add {$this->username}",
            MinecraftAccountType::Bedrock => "fwhitelist add {$this->uuid}",
        };
    }

    /**
     * Returns the correct whitelist-remove command for this account's type.
     * Java:    "whitelist remove <username>"
     * Bedrock: "fwhitelist remove <floodgate_uuid>"
     */
    public function whitelistRemoveCommand(): string
    {
        return match ($this->account_type) {
            MinecraftAccountType::Java => "whitelist remove {$this->username}",
            MinecraftAccountType::Bedrock => "fwhitelist remove {$this->uuid}",
        };
    }
}
