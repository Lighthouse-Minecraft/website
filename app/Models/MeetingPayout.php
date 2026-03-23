<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MeetingPayout extends Model
{
    use HasFactory;

    protected $fillable = [
        'meeting_id',
        'user_id',
        'minecraft_account_id',
        'amount',
        'status',
        'skip_reason',
    ];

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function minecraftAccount(): BelongsTo
    {
        return $this->belongsTo(MinecraftAccount::class);
    }
}
