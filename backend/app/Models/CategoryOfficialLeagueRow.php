<?php

namespace App\Models;

use App\Enums\OfficialIdentityProjection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CategoryOfficialLeagueRow extends Model
{
    use HasFactory;

    protected $fillable = [
        'official_result_id',
        'position',
        'source_entry_id',
        'source_player_id',
        'source_team_id',
        'entry_type',
        'identity_projection',
        'display_name_snapshot',
        'public_display_name',
        'public_anonymized_at',
        'played',
        'wins',
        'losses',
        'points',
        'games_for',
        'games_against',
        'games_diff',
    ];

    protected $casts = [
        'position' => 'integer',
        'source_entry_id' => 'integer',
        'source_player_id' => 'integer',
        'source_team_id' => 'integer',
        'identity_projection' => OfficialIdentityProjection::class,
        'public_anonymized_at' => 'immutable_datetime',
        'played' => 'integer',
        'wins' => 'integer',
        'losses' => 'integer',
        'points' => 'integer',
        'games_for' => 'integer',
        'games_against' => 'integer',
        'games_diff' => 'integer',
    ];

    public function officialResult(): BelongsTo
    {
        return $this->belongsTo(CategoryOfficialResult::class, 'official_result_id');
    }
}
