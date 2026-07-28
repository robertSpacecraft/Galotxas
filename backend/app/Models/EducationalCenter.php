<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EducationalCenter extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'locality',
        'contact_name',
        'contact_phone',
        'contact_email',
        'is_active',
        'admin_notes',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where($query->qualifyColumn('is_active'), true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query
            ->orderBy($query->qualifyColumn('locality'))
            ->orderBy($query->qualifyColumn('name'))
            ->orderBy($query->qualifyColumn('id'));
    }

    public function activities(): HasMany
    {
        return $this->hasMany(EducationalActivity::class);
    }

    public function isInUse(): bool
    {
        return $this->activities()->exists();
    }
}
