<?php

namespace App\Models;

use App\Enums\MinecraftAccountType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MinecraftAccount extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'username',
        'uuid',
        'avatar_url',
        'account_type',
        'verified_at',
        'last_username_check_at',
    ];

    protected function casts(): array
    {
        return [
            'account_type' => MinecraftAccountType::class,
            'verified_at' => 'datetime',
            'last_username_check_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeWhereNormalizedUuid(Builder $query, string $uuid): void
    {
        $normalized = str_replace('-', '', $uuid);
        $query->whereRaw("REPLACE(uuid, '-', '') = ?", [$normalized]);
    }
}
