<?php

namespace App\Models;

use App\Enums\SchoolEnrollmentStatus;
use App\Services\SchoolEnrollmentAgeService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
        'privacy_notice_id',
        'privacy_notice_version',
    ];

    protected $casts = [
        'participant_birth_date' => 'immutable_date',
        'status' => SchoolEnrollmentStatus::class,
        'requested_at' => 'immutable_datetime',
        'activated_at' => 'immutable_datetime',
        'rejected_at' => 'immutable_datetime',
        'withdrawn_at' => 'immutable_datetime',
        'privacy_acknowledged_at' => 'immutable_datetime',
        'corrected_at' => 'immutable_datetime',
        'retention_until' => 'immutable_datetime',
        'retention_hold' => 'boolean',
        'retention_hold_placed_at' => 'immutable_datetime',
        'retention_hold_released_at' => 'immutable_datetime',
        'anonymized_at' => 'immutable_datetime',
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

    public function publicIdentityAuthorizations(): HasMany
    {
        return $this->hasMany(PublicIdentityAuthorization::class);
    }

    public function correctedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'corrected_by');
    }

    public function activatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'activated_by');
    }

    public function rejectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    public function withdrawnBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'withdrawn_by');
    }

    public function retentionHoldPlacedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'retention_hold_placed_by');
    }

    public function retentionHoldReleasedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'retention_hold_released_by');
    }

    public function wasMinorAtRequest(): ?bool
    {
        if ($this->participant_birth_date === null || $this->requested_at === null) {
            return null;
        }

        return SchoolEnrollmentAgeService::isMinor(
            $this->participant_birth_date,
            $this->requested_at
        );
    }

    public function isAnonymized(): bool
    {
        return $this->anonymized_at !== null;
    }
}
