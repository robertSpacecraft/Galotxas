<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SchoolLocation extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'locality',
        'address',
        'is_active',
        'sort_order',
        'admin_notes',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where($query->qualifyColumn('is_active'), true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query
            ->orderBy($query->qualifyColumn('sort_order'))
            ->orderBy($query->qualifyColumn('id'));
    }

    public function defaultForPrograms(): HasMany
    {
        return $this->hasMany(SchoolProgram::class, 'default_school_location_id');
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(SchoolSchedule::class);
    }

    public function isInUse(): bool
    {
        return $this->defaultForPrograms()->exists() || $this->schedules()->exists();
    }
}
