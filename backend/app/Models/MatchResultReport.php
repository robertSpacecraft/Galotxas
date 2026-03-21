<?php

namespace App\Models;

use App\Enums\MatchResultReportSide;
use App\Enums\MatchResultReportStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MatchResultReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'game_match_id',
        'user_id',
        'player_id',
        'side',
        'home_score',
        'away_score',
        'status',
        'comment',
    ];

    protected $casts = [
        'home_score' => 'integer',
        'away_score' => 'integer',
        'side' => MatchResultReportSide::class,
        'status' => MatchResultReportStatus::class,
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

    public function isFromHomeSide(): bool
    {
        return $this->side === MatchResultReportSide::HOME;
    }

    public function isFromAwaySide(): bool
    {
        return $this->side === MatchResultReportSide::AWAY;
    }

    public function hasSameScoresAs(self $other): bool
    {
        return $this->home_score === $other->home_score
            && $this->away_score === $other->away_score;
    }
}
