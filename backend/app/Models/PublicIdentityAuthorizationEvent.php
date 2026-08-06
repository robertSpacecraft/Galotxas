<?php

namespace App\Models;

use App\Enums\PublicIdentityAuthorizationEventType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PublicIdentityAuthorizationEvent extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $guarded = ['id'];

    protected $casts = [
        'type' => PublicIdentityAuthorizationEventType::class,
        'occurred_at' => 'immutable_datetime',
        'metadata' => 'array',
    ];

    public function authorization(): BelongsTo
    {
        return $this->belongsTo(PublicIdentityAuthorization::class, 'public_identity_authorization_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
