<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class CategoryEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'category_id',
        'entry_type',
        'player_id',
        'team_id',
        'status',
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function player()
    {
        return $this->belongsTo(Player::class);
    }

    public function team()
    {
        return $this->belongsTo(Team::class);
    }
}
