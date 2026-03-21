<?php

namespace App\Models;

use App\Enums\PlayerGender;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Player extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'nickname',
        'slug',
        'dni',
        'birth_date',
        'gender',
        'level',
        'license_number',
        'dominant_hand',
        'notes',
        'active',
    ];

    protected $casts = [
        'birth_date' => 'date',
        'gender' => PlayerGender::class,
        'level' => 'integer',
        'active' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class, 'team_members')
            ->withPivot('role_in_team')
            ->withTimestamps();
    }

    public function entries(): HasMany
    {
        return $this->hasMany(CategoryEntry::class);
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(CategoryRegistration::class);
    }

    public function matchResultReports(): HasMany
    {
        return $this->hasMany(MatchResultReport::class);
    }
}
