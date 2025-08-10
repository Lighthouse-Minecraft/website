<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Meeting extends Model
{
    use HasFactory;

    protected $fillable = ['title', 'day', 'scheduled_time', 'is_public'];

    protected $casts = [
        'day' => 'string',
        'scheduled_time' => 'datetime',
        'is_public' => 'boolean',
    ];
}
