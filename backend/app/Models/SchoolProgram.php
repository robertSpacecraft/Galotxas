<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SchoolProgram extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'is_public',
        'enrollments_open',
        'default_school_location_id',
        'contact_phone',
        'contact_email',
        'sort_order',
    ];

    protected $hidden = [
        'public_slot',
    ];

    protected $casts = [
        'is_public' => 'boolean',
        'enrollments_open' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function scopeEffectivelyPublic(Builder $query): Builder
    {
        return $query->where($query->qualifyColumn('is_public'), true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query
            ->orderBy($query->qualifyColumn('sort_order'))
            ->orderBy($query->qualifyColumn('id'));
    }

    public function isEffectivelyPublic(): bool
    {
        return $this->is_public;
    }

    public function acceptsPublicEnrollments(): bool
    {
        return $this->is_public && $this->enrollments_open;
    }

    public function defaultLocation(): BelongsTo
    {
        return $this->belongsTo(SchoolLocation::class, 'default_school_location_id');
    }

    public function levels(): HasMany
    {
        return $this->hasMany(SchoolLevel::class);
    }

    public function isInUse(): bool
    {
        return $this->levels()->exists();
    }
}
