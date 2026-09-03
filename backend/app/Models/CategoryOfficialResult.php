<?php

namespace App\Models;

use App\Enums\OfficialResultCompetitionPart;
use App\Enums\OfficialResultStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class CategoryOfficialResult extends Model
{
    use HasFactory;

    protected $fillable = [
        'category_id',
        'competition_part',
        'version',
        'status',
        'officialized_at',
        'officialized_by_user_id',
        'officialized_by_name_snapshot',
        'reopened_at',
        'reopened_by_user_id',
        'reopened_by_name_snapshot',
        'reopen_reason',
        'source_digest',
    ];

    protected $hidden = [
        'current_slot',
    ];

    protected $casts = [
        'competition_part' => OfficialResultCompetitionPart::class,
        'version' => 'integer',
        'status' => OfficialResultStatus::class,
        'officialized_at' => 'immutable_datetime',
        'reopened_at' => 'immutable_datetime',
        'current_slot' => 'integer',
    ];

    public function scopeLeague(Builder $query): Builder
    {
        return $query->where(
            'competition_part',
            OfficialResultCompetitionPart::LEAGUE->value
        );
    }

    public function scopeCup(Builder $query): Builder
    {
        return $query->where(
            'competition_part',
            OfficialResultCompetitionPart::CUP->value
        );
    }

    public function scopeOfficial(Builder $query): Builder
    {
        return $query->where('status', OfficialResultStatus::OFFICIAL->value);
    }

    public function scopeReopened(Builder $query): Builder
    {
        return $query->where('status', OfficialResultStatus::REOPENED->value);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function leagueRows(): HasMany
    {
        return $this->hasMany(CategoryOfficialLeagueRow::class, 'official_result_id');
    }

    public function cupWinner(): HasOne
    {
        return $this->hasOne(CategoryOfficialCupWinner::class, 'official_result_id');
    }

    public function matchSnapshots(): HasMany
    {
        return $this->hasMany(
            CategoryOfficialResultMatchSnapshot::class,
            'official_result_id'
        );
    }

    public function officializedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'officialized_by_user_id');
    }

    public function reopenedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reopened_by_user_id');
    }
}
