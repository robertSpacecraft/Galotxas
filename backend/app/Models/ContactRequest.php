<?php

namespace App\Models;

use App\Enums\ContactNotificationStatus;
use App\Enums\ContactRequestStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ContactRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'email',
        'subject',
        'message',
    ];

    protected $casts = [
        'status' => ContactRequestStatus::class,
        'notification_status' => ContactNotificationStatus::class,
        'consent_at' => 'immutable_datetime',
        'closed_at' => 'immutable_datetime',
        'retention_until' => 'immutable_datetime',
        'notification_attempted_at' => 'immutable_datetime',
        'notification_sent_at' => 'immutable_datetime',
        'notification_failed_at' => 'immutable_datetime',
        'ip_hash_expires_at' => 'immutable_datetime',
        'retention_hold' => 'boolean',
        'retention_hold_placed_at' => 'immutable_datetime',
        'retention_hold_released_at' => 'immutable_datetime',
        'anonymized_at' => 'immutable_datetime',
    ];

    public function scopeWithStatus(
        Builder $query,
        ContactRequestStatus|string $status
    ): Builder {
        return $query->where(
            $query->qualifyColumn('status'),
            $status instanceof ContactRequestStatus ? $status->value : $status
        );
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query
            ->orderByDesc($query->qualifyColumn('created_at'))
            ->orderByDesc($query->qualifyColumn('id'));
    }

    public function scopeWithNotificationStatus(
        Builder $query,
        ContactNotificationStatus|string $status
    ): Builder {
        return $query->where(
            $query->qualifyColumn('notification_status'),
            $status instanceof ContactNotificationStatus ? $status->value : $status
        );
    }

    public function events(): HasMany
    {
        return $this->hasMany(ContactRequestEvent::class)->orderByDesc('occurred_at');
    }

    public function isLegacy(): bool
    {
        return $this->privacy_notice_id === null || $this->privacy_notice_version === null;
    }

    public function isAnonymized(): bool
    {
        return $this->anonymized_at !== null;
    }
}
