<?php

namespace App\Models;

use App\Enums\ChampionshipType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Championship extends Model
{
    use HasFactory;

    protected $fillable = [
        'season_id',
        'name',
        'description',
        'type',
        'status',
    ];

    protected $casts = [
        'type' => ChampionshipType::class,
    ];

    public function season()
    {
        return $this->belongsTo(Season::class);
    }

    public function categories()
    {
        return $this->hasMany(Category::class);
    }
}
