<?php

namespace App\Models;

use App\Enums\ChampionshipRegistrationPaymentStatus;
use App\Enums\ChampionshipRegistrationRequestStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChampionshipRegistrationRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'championship_id',
        'user_id',
        'player_id',
        'suggested_category_id',
        'status',
        'payment_status',
        'comment',
    ];

    protected $casts = [
        'status' => ChampionshipRegistrationRequestStatus::class,
        'payment_status' => ChampionshipRegistrationPaymentStatus::class,
    ];

    public function championship(): BelongsTo
    {
        return $this->belongsTo(Championship::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }

    public function suggestedCategory(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'suggested_category_id');
    }
}
