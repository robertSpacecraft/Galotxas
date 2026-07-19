<?php

namespace App\Models;

use App\Enums\CategoryGender;
use Illuminate\Database\Eloquent\Builder;
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

    protected $hidden = [
        'is_public',
    ];

    protected $casts = [
        'gender' => CategoryGender::class,
        'is_public' => 'boolean',
    ];

    public function scopeEffectivelyPublic(Builder $query): Builder
    {
        return $query
            ->where($query->qualifyColumn('is_public'), true)
            ->whereHas(
                'championship',
                fn (Builder $championshipQuery) => $championshipQuery->effectivelyPublic()
            );
    }

    public function isEffectivelyPublic(): bool
    {
        return $this->exists
            && self::query()->whereKey($this->getKey())->effectivelyPublic()->exists();
    }

    public function championship(): BelongsTo
    {
        return $this->belongsTo(Championship::class);
    }

    public function entries(): HasMany
    {
        return $this->hasMany(CategoryEntry::class);
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(CategoryRegistration::class);
    }

    public function rounds(): HasMany
    {
        return $this->hasMany(Round::class);
    }

    public function teams(): HasMany
    {
        return $this->hasMany(Team::class);
    }
}
