<?php

namespace App\Models;

use App\Enums\MatchRescheduleRequestStatus;
use App\Enums\MatchResultReportSide;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MatchRescheduleRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'game_match_id',
        'user_id',
        'player_id',
        'side',
        'requested_scheduled_date',
        'requested_venue_id',
        'status',
        'comment',
    ];

    protected $casts = [
        'side' => MatchResultReportSide::class,
        'requested_scheduled_date' => 'datetime',
        'status' => MatchRescheduleRequestStatus::class,
    ];

    public function gameMatch(): BelongsTo
    {
        return $this->belongsTo(GameMatch::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }

    public function requestedVenue(): BelongsTo
    {
        return $this->belongsTo(Venue::class, 'requested_venue_id');
    }

    public function isFromHomeSide(): bool
    {
        return $this->side === MatchResultReportSide::HOME;
    }

    public function isFromAwaySide(): bool
    {
        return $this->side === MatchResultReportSide::AWAY;
    }
}
