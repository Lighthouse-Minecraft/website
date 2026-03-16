<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffApplicationNote extends Model
{
    use HasFactory;

    protected $fillable = [
        'staff_application_id',
        'user_id',
        'body',
    ];

    public function application(): BelongsTo
    {
        return $this->belongsTo(StaffApplication::class, 'staff_application_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
