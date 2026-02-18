<?php

namespace App\Models;

use App\Enums\MinecraftAccountType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MinecraftAccount extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'username',
        'uuid',
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

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
