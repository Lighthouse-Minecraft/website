<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PrayerCountryStat extends Model
{
    use HasFactory;

    protected $fillable = ['prayer_country_id', 'year', 'count'];

    public function prayerCountry()
    {
        return $this->belongsTo(PrayerCountry::class);
    }
}
