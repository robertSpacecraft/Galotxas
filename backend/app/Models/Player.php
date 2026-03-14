<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Player extends Model
{
    use HasFactory;
    protected $fillable = [
        'name',
        'slug',
        'active',
        'dni',
        'birth_date',
        'gender',
        'level'
    ];

    public function user()
    {
        return $this->hasOne(User::class);
    }

    public function teams()
    {
        return $this->belongsToMany(Team::class, 'team_members');
    }
}
