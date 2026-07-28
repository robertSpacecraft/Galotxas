<?php

namespace App\Models;

use App\Enums\SchoolDayOfWeek;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SchoolSchedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'school_level_id',
        'school_location_id',
        'day_of_week',
        'starts_at',
        'ends_at',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'day_of_week' => SchoolDayOfWeek::class,
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function scopeEffectivelyPublic(Builder $query): Builder
    {
        return $query
            ->where($query->qualifyColumn('is_active'), true)
            ->whereHas(
                'level',
                fn (Builder $levelQuery) => $levelQuery->effectivelyPublic()
            )
            ->whereHas(
                'location',
                fn (Builder $locationQuery) => $locationQuery->active()
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
            && $this->level?->isEffectivelyPublic()
            && (bool) $this->location?->is_active;
    }

    public function startsAtLabel(): string
    {
        return substr((string) $this->starts_at, 0, 5);
    }

    public function endsAtLabel(): string
    {
        return substr((string) $this->ends_at, 0, 5);
    }

    public function level(): BelongsTo
    {
        return $this->belongsTo(SchoolLevel::class, 'school_level_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(SchoolLocation::class, 'school_location_id');
    }
}
