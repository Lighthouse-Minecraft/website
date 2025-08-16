<?php

namespace App\Models;

use App\Enums\TaskStatus;
use Illuminate\Database\Eloquent\Model;

class Task extends Model
{
    protected $fillable = ['name', 'assigned_meeting_id', 'section_key', 'status'];

    protected $casts = [
        'status' => TaskStatus::class,
    ];
}
