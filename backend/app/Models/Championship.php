<?php

namespace App\Models;

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

    public function season()
    {
        return $this->belongsTo(Season::class);
    }

    public function categories()
    {
        return $this->hasMany(Category::class);
    }
}
