<?php

namespace App\Models;

use App\Enums\SeasonStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Season extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'start_date',
        'end_date',
        'status',
    ];

    protected $casts = [
        'status' => SeasonStatus::class,
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public function championships()
    {
        return $this->hasMany(Championship::class);
    }
}
