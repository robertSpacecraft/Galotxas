<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CategoryOfficialResultMatchSnapshot extends Model
{
    use HasFactory;

    protected $fillable = [
        'official_result_id',
        'source_game_match_id',
        'source_round_id',
        'stage',
        'home_entry_id',
        'away_entry_id',
        'home_score',
        'away_score',
        'winner_entry_id',
    ];

    protected $casts = [
        'source_game_match_id' => 'integer',
        'source_round_id' => 'integer',
        'home_entry_id' => 'integer',
        'away_entry_id' => 'integer',
        'home_score' => 'integer',
        'away_score' => 'integer',
        'winner_entry_id' => 'integer',
    ];

    public function officialResult(): BelongsTo
    {
        return $this->belongsTo(CategoryOfficialResult::class, 'official_result_id');
    }
}
