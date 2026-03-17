<?php

namespace App\Models;

use App\Enums\CategoryGender;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    use HasFactory;

    protected $fillable = [
        'championship_id',
        'name',
        'slug',
        'level',
        'gender',
        'description',
        'image_path',
        'status',
    ];

    protected $casts = [
        'gender' => CategoryGender::class,
    ];

    public function championship(): BelongsTo
    {
        return $this->belongsTo(Championship::class);
    }

    public function entries(): HasMany
    {
        return $this->hasMany(CategoryEntry::class);
    }

    public function rounds(): HasMany
    {
        return $this->hasMany(Round::class);
    }
}
