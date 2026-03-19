<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Team extends Model
{
    use HasFactory;

    protected $fillable = [
        'category_id',
        'name',
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function players()
    {
        return $this->belongsToMany(Player::class, 'team_members')
            ->withPivot('role_in_team')
            ->withTimestamps();
    }

    public function entries()
    {
        return $this->hasMany(CategoryEntry::class);
    }
}
