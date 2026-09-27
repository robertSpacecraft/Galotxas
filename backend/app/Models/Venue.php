<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Venue extends Model
{
    use HasFactory;

    protected $fillable = [
        'court_number',
        'name',
        'location',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'court_number' => 'integer',
        ];
    }

    public function matches(): HasMany
    {
        return $this->hasMany(GameMatch::class);
    }

    public function rescheduleRequests(): HasMany
    {
        return $this->hasMany(MatchRescheduleRequest::class, 'requested_venue_id');
    }

    public function isInUse(): bool
    {
        return $this->matches()->exists() || $this->rescheduleRequests()->exists();
    }
}
