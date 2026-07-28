<?php

namespace App\Models;

use App\Enums\EducationalActivityStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EducationalActivity extends Model
{
    use HasFactory;

    protected $fillable = [
        'educational_center_id',
        'school_location_id',
        'name',
        'activity_date',
        'starts_at',
        'ends_at',
        'expected_students',
        'admin_notes',
    ];

    protected $casts = [
        'activity_date' => 'immutable_date',
        'expected_students' => 'integer',
        'status' => EducationalActivityStatus::class,
    ];

    public function scopeWithStatus(
        Builder $query,
        EducationalActivityStatus|string $status
    ): Builder {
        return $query->where(
            $query->qualifyColumn('status'),
            $status instanceof EducationalActivityStatus ? $status->value : $status
        );
    }

    public function scopeForCenter(Builder $query, int $centerId): Builder
    {
        return $query->where(
            $query->qualifyColumn('educational_center_id'),
            $centerId
        );
    }

    public function scopeBetweenDates(
        Builder $query,
        ?string $dateFrom,
        ?string $dateTo
    ): Builder {
        return $query
            ->when(
                $dateFrom,
                fn (Builder $builder) => $builder->whereDate(
                    $builder->qualifyColumn('activity_date'),
                    '>=',
                    $dateFrom
                )
            )
            ->when(
                $dateTo,
                fn (Builder $builder) => $builder->whereDate(
                    $builder->qualifyColumn('activity_date'),
                    '<=',
                    $dateTo
                )
            );
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query
            ->orderByDesc($query->qualifyColumn('activity_date'))
            ->orderByDesc($query->qualifyColumn('id'));
    }

    public function startsAtLabel(): ?string
    {
        return $this->starts_at === null
            ? null
            : substr((string) $this->starts_at, 0, 5);
    }

    public function endsAtLabel(): ?string
    {
        return $this->ends_at === null
            ? null
            : substr((string) $this->ends_at, 0, 5);
    }

    public function center(): BelongsTo
    {
        return $this->belongsTo(EducationalCenter::class, 'educational_center_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(SchoolLocation::class, 'school_location_id');
    }
}
