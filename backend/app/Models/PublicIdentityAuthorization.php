<?php

namespace App\Models;

use App\Enums\PublicIdentityAuthorizationMode;
use App\Enums\PublicIdentityAuthorizationState;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PublicIdentityAuthorization extends Model
{
    use HasFactory;

    public const SCOPE = 'public_competition_identity';

    protected $guarded = ['id', 'approval_slot'];

    protected $hidden = ['confirmation_token_hash'];

    protected $casts = [
        'mode' => PublicIdentityAuthorizationMode::class,
        'state' => PublicIdentityAuthorizationState::class,
        'approval_slot' => 'integer',
        'guardian_authority_declared_at' => 'immutable_datetime',
        'requested_at' => 'immutable_datetime',
        'guardian_confirmed_at' => 'immutable_datetime',
        'guardian_denied_at' => 'immutable_datetime',
        'minor_assent_recorded_at' => 'immutable_datetime',
        'reviewed_at' => 'immutable_datetime',
        'approved_at' => 'immutable_datetime',
        'denied_at' => 'immutable_datetime',
        'revoked_at' => 'immutable_datetime',
        'expires_at' => 'immutable_datetime',
        'confirmation_token_expires_at' => 'immutable_datetime',
        'confirmation_token_used_at' => 'immutable_datetime',
    ];

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderByDesc('requested_at')->orderByDesc('id');
    }

    public function schoolEnrollment(): BelongsTo
    {
        return $this->belongsTo(SchoolEnrollment::class);
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }

    public function minorAssentRecorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'minor_assent_recorded_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function revoker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by');
    }

    public function events(): HasMany
    {
        return $this->hasMany(PublicIdentityAuthorizationEvent::class)
            ->orderBy('occurred_at')
            ->orderBy('id');
    }
}
