<?php

namespace App\Models;

use App\Enums\ContactRequestStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
        'consent_at' => 'immutable_datetime',
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
}
