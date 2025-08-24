<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PrayerCountry extends Model
{
    use HasFactory;

    protected $fillable = ['day', 'name', 'operation_world_url', 'prayer_cast_url'];

    public function stats()
    {
        return $this->hasMany(PrayerCountryStat::class);
    }
}
