<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
    ];

    public function round()
    {
        return $this->belongsTo(Round::class);
    }

    public function venue()
    {
        return $this->belongsTo(Venue::class);
    }

    public function homeEntry()
    {
        return $this->belongsTo(CategoryEntry::class, 'home_entry_id');
    }

    public function awayEntry()
    {
        return $this->belongsTo(CategoryEntry::class, 'away_entry_id');
    }

    public function winnerEntry()
    {
        return $this->belongsTo(CategoryEntry::class, 'winner_entry_id');
    }

    public function submittedBy()
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function validatedBy()
    {
        return $this->belongsTo(User::class, 'validated_by');
    }
}
