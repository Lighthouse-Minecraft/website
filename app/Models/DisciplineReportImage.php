<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class DisciplineReportImage extends Model
{
    use HasFactory;

    protected $fillable = [
        'discipline_report_id',
        'path',
        'original_filename',
    ];

    protected static function booted(): void
    {
        static::deleting(function (DisciplineReportImage $image) {
            Storage::disk(config('filesystems.public_disk'))->delete($image->path);
        });
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(DisciplineReport::class, 'discipline_report_id');
    }

    public function url(): string
    {
        return \App\Services\StorageService::publicUrl($this->path);
    }
}
