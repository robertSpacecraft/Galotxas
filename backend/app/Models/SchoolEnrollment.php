<?php

namespace App\Models;

use App\Enums\SchoolEnrollmentStatus;
use App\Services\SchoolEnrollmentAgeService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SchoolEnrollment extends Model
{
    use HasFactory;

    protected $fillable = [
        'participant_name',
        'participant_birth_date',
        'contact_phone',
        'contact_email',
        'guardian_name',
        'guardian_relationship',
    ];

    protected $casts = [
        'participant_birth_date' => 'immutable_date',
        'status' => SchoolEnrollmentStatus::class,
        'requested_at' => 'immutable_datetime',
        'activated_at' => 'immutable_datetime',
        'rejected_at' => 'immutable_datetime',
        'withdrawn_at' => 'immutable_datetime',
    ];

    public function scopeWithStatus(
        Builder $query,
        SchoolEnrollmentStatus|string $status
    ): Builder {
        return $query->where(
            $query->qualifyColumn('status'),
            $status instanceof SchoolEnrollmentStatus ? $status->value : $status
        );
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->withStatus(SchoolEnrollmentStatus::PENDING);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->withStatus(SchoolEnrollmentStatus::ACTIVE);
    }

    public function scopeRejected(Builder $query): Builder
    {
        return $query->withStatus(SchoolEnrollmentStatus::REJECTED);
    }

    public function scopeWithdrawn(Builder $query): Builder
    {
        return $query->withStatus(SchoolEnrollmentStatus::WITHDRAWN);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query
            ->orderByDesc($query->qualifyColumn('requested_at'))
            ->orderByDesc($query->qualifyColumn('id'));
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(SchoolProgram::class, 'school_program_id');
    }

    public function level(): BelongsTo
    {
        return $this->belongsTo(SchoolLevel::class, 'school_level_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function wasMinorAtRequest(): bool
    {
        return SchoolEnrollmentAgeService::isMinor(
            $this->participant_birth_date,
            $this->requested_at
        );
    }
}
