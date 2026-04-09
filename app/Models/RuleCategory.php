<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RuleCategory extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'sort_order'];

    public function rules(): HasMany
    {
        return $this->hasMany(Rule::class)->orderBy('sort_order');
    }
}
