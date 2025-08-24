<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PrayerCountryStat extends Model
{
    protected $fillable = ['prayer_country_id', 'year', 'count'];
}
