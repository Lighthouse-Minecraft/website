<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReportCategory extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'color'];

    public function disciplineReports(): HasMany
    {
        return $this->hasMany(DisciplineReport::class);
    }
}
