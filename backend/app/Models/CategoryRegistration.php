<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CategoryRegistration extends Model
{
    use HasFactory;

    protected $fillable = [
        'category_id',
        'player_id',
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
}
