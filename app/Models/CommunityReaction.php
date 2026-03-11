<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommunityReaction extends Model
{
    protected $fillable = [
        'community_response_id',
        'user_id',
        'emoji',
    ];

    public function response(): BelongsTo
    {
        return $this->belongsTo(CommunityResponse::class, 'community_response_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
