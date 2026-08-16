<?php

namespace App\Models;

use App\Enums\SponsorEffectiveState;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Sponsor extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'logo_key',
        'logo_width',
        'logo_height',
        'website_url',
        'sort_order',
        'is_active',
        'starts_at',
        'ends_at',
    ];

    protected $hidden = [
        'logo_key',
    ];

    protected $casts = [
        'logo_width' => 'integer',
        'logo_height' => 'integer',
        'sort_order' => 'integer',
        'is_active' => 'boolean',
        'starts_at' => 'immutable_datetime',
        'ends_at' => 'immutable_datetime',
    ];

    public function scopeEffectivelyVisible(
        Builder $query,
        ?CarbonInterface $at = null
    ): Builder {
        $at ??= now();

        return $query
            ->where($query->qualifyColumn('is_active'), true)
            ->where(function (Builder $query) use ($at): void {
                $query
                    ->whereNull($query->qualifyColumn('starts_at'))
                    ->orWhere($query->qualifyColumn('starts_at'), '<=', $at);
            })
            ->where(function (Builder $query) use ($at): void {
                $query
                    ->whereNull($query->qualifyColumn('ends_at'))
                    ->orWhere($query->qualifyColumn('ends_at'), '>', $at);
            });
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query
            ->orderBy($query->qualifyColumn('sort_order'))
            ->orderBy($query->qualifyColumn('id'));
    }

    public function isEffectivelyVisible(?CarbonInterface $at = null): bool
    {
        return $this->effectiveState($at) === SponsorEffectiveState::ACTIVE;
    }

    public function effectiveState(?CarbonInterface $at = null): SponsorEffectiveState
    {
        $at ??= now();

        if (! $this->is_active) {
            return SponsorEffectiveState::INACTIVE;
        }

        if ($this->starts_at !== null && $this->starts_at->isAfter($at)) {
            return SponsorEffectiveState::SCHEDULED;
        }

        if ($this->ends_at !== null && ! $this->ends_at->isAfter($at)) {
            return SponsorEffectiveState::EXPIRED;
        }

        return SponsorEffectiveState::ACTIVE;
    }
}
