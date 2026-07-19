<?php

namespace App\Models;

use App\Enums\SeasonStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Season extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'start_date',
        'end_date',
        'status',
    ];

    protected $hidden = [
        'is_public',
    ];

    protected $casts = [
        'status' => SeasonStatus::class,
        'is_public' => 'boolean',
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public function scopeEffectivelyPublic(Builder $query): Builder
    {
        return $query->where($query->qualifyColumn('is_public'), true);
    }

    public function isEffectivelyPublic(): bool
    {
        return $this->exists
            && self::query()->whereKey($this->getKey())->effectivelyPublic()->exists();
    }

    public function championships()
    {
        return $this->hasMany(Championship::class);
    }
}
