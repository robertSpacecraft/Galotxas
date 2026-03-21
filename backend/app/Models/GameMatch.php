<?php

namespace App\Models;

use App\Enums\GameMatchStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GameMatch extends Model
{
    use HasFactory;

    protected $fillable = [
        'round_id',
        'venue_id',
        'home_entry_id',
        'away_entry_id',
        'scheduled_date',
        'status',
        'home_score',
        'away_score',
        'winner_entry_id',
        'submitted_by',
        'validated_by',
    ];

    protected $casts = [
        'scheduled_date' => 'datetime',
        'status' => GameMatchStatus::class,
        'home_score' => 'integer',
        'away_score' => 'integer',
    ];

    public function round(): BelongsTo
    {
        return $this->belongsTo(Round::class);
    }

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    public function homeEntry(): BelongsTo
    {
        return $this->belongsTo(CategoryEntry::class, 'home_entry_id');
    }

    public function awayEntry(): BelongsTo
    {
        return $this->belongsTo(CategoryEntry::class, 'away_entry_id');
    }

    public function winnerEntry(): BelongsTo
    {
        return $this->belongsTo(CategoryEntry::class, 'winner_entry_id');
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function validatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validated_by');
    }

    public function resultReports(): HasMany
    {
        return $this->hasMany(MatchResultReport::class);
    }
}
