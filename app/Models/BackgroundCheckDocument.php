<?php

namespace App\Models;

use App\Services\StorageService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class BackgroundCheckDocument extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'background_check_id',
        'path',
        'original_filename',
        'uploaded_by_user_id',
    ];

    public function url(): string
    {
        return StorageService::publicUrl($this->path);
    }

    public function backgroundCheck(): BelongsTo
    {
        return $this->belongsTo(BackgroundCheck::class);
    }

    public function uploadedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }
}
