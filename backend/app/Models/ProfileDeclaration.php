<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProfileDeclaration extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected $casts = [
        'declared_at' => 'immutable_datetime',
    ];

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    public function subjectPlayer(): BelongsTo
    {
        return $this->belongsTo(Player::class, 'subject_player_id');
    }
}
