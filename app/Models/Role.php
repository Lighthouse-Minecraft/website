<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Role extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'description', 'color', 'icon'];

    public function staffPositions(): BelongsToMany
    {
        return $this->belongsToMany(StaffPosition::class);
    }

    /**
     * Get the feature group prefix from the role name (e.g., "Ticket" from "Ticket - User").
     */
    public function getGroupAttribute(): string
    {
        if (str_contains($this->name, ' - ')) {
            return explode(' - ', $this->name, 2)[0];
        }

        return 'Other';
    }
}
