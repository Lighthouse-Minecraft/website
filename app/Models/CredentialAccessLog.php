<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CredentialAccessLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'credential_id',
        'user_id',
        'action',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function credential(): BelongsTo
    {
        return $this->belongsTo(Credential::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
