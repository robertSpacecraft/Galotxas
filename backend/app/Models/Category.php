<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Category extends Model
{
    use HasFactory;
    protected $fillable = [
        'championship_id',
        'name',
        'slug',
        'level',
        'category_type',
        'status',
    ];

    public function championship()
    {
        return $this->belongsTo(Championship::class);
    }

    public function entries()
    {
        return $this->hasMany(CategoryEntry::class);
    }

    public function rounds()
    {
        return $this->hasMany(Round::class);
    }
}
