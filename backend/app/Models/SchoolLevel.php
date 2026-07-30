<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SchoolLevel extends Model
{
    use HasFactory;

    protected $fillable = [
        'school_program_id',
        'name',
        'minimum_age',
        'maximum_age',
        'is_active',
        'is_public',
        'sort_order',
    ];

    protected $casts = [
        'minimum_age' => 'integer',
        'maximum_age' => 'integer',
        'is_active' => 'boolean',
        'is_public' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function scopeEffectivelyPublic(Builder $query): Builder
    {
        return $query
            ->where($query->qualifyColumn('is_active'), true)
            ->where($query->qualifyColumn('is_public'), true)
            ->whereHas(
                'program',
                fn (Builder $programQuery) => $programQuery->effectivelyPublic()
            );
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query
            ->orderBy($query->qualifyColumn('sort_order'))
            ->orderBy($query->qualifyColumn('id'));
    }

    public function isEffectivelyPublic(): bool
    {
        return $this->is_active
            && $this->is_public
            && (bool) $this->program?->is_public;
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(SchoolProgram::class, 'school_program_id');
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(SchoolSchedule::class);
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(SchoolEnrollment::class);
    }

    public function isInUse(): bool
    {
        return $this->schedules()->exists() || $this->enrollments()->exists();
    }
}
