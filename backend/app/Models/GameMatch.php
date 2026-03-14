<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class GameMatch extends Model
{
    use HasFactory;
    protected $fillable = [
        'round_id',
        'venue_id',
        'home_entry_id',
        'away_entry_id',
        'scheduled_date',
        'home_score',
        'away_score',
        'status',
        'submitted_by',
        'validated_by',
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

    public function submitter()
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function validator()
    {
        return $this->belongsTo(User::class, 'validated_by');
    }
}
