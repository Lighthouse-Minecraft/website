<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BoardMember extends Model
{
    use HasFactory;

    protected $fillable = [
        'display_name',
        'title',
        'user_id',
        'bio',
        'photo_path',
        'sort_order',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isLinked(): bool
    {
        return $this->user_id !== null;
    }

    public function isUnlinked(): bool
    {
        return $this->user_id === null;
    }

    public function effectiveName(): string
    {
        if ($this->isLinked() && $this->user && $this->user->staff_first_name) {
            $name = $this->user->staff_first_name;
            if ($this->user->staff_last_initial) {
                $name .= ' '.$this->user->staff_last_initial.'.';
            }

            return $name;
        }

        return $this->display_name;
    }

    public function effectiveBio(): ?string
    {
        if ($this->isLinked() && $this->user) {
            return $this->user->staff_bio;
        }

        return $this->bio;
    }

    public function effectivePhotoUrl(): ?string
    {
        if ($this->isLinked() && $this->user) {
            return $this->user->staffPhotoUrl() ?? $this->user->avatarUrl();
        }

        if ($this->photo_path) {
            return asset('storage/'.$this->photo_path);
        }

        return null;
    }

    public function scopeOrdered(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->orderBy('sort_order')->orderBy('display_name');
    }
}
