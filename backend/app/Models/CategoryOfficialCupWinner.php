<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CategoryOfficialCupWinner extends Model
{
    use HasFactory;

    protected $fillable = [
        'official_result_id',
        'source_entry_id',
        'source_player_id',
        'source_team_id',
        'entry_type',
        'source_final_match_id',
        'identity_projection',
        'display_name_snapshot',
        'public_display_name',
        'public_anonymized_at',
    ];

    protected $casts = [
        'source_entry_id' => 'integer',
        'source_player_id' => 'integer',
        'source_team_id' => 'integer',
        'source_final_match_id' => 'integer',
        'public_anonymized_at' => 'immutable_datetime',
    ];

    public function officialResult(): BelongsTo
    {
        return $this->belongsTo(CategoryOfficialResult::class, 'official_result_id');
    }
}
