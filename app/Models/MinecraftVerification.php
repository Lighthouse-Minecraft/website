<?php

namespace App\Models;

use App\Enums\MinecraftAccountType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MinecraftVerification extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'code',
        'account_type',
        'minecraft_username',
        'minecraft_uuid',
        'status',
        'expires_at',
        'whitelisted_at',
    ];

    protected function casts(): array
    {
        return [
            'account_type' => MinecraftAccountType::class,
            'expires_at' => 'datetime',
            'whitelisted_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeExpired($query)
    {
        return $query->where('expires_at', '<', now());
    }
}
