<?php

namespace App\Models;

use App\Enums\ChampionshipType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Enums\ChampionshipRegistrationStatus;

class Championship extends Model
{
    use HasFactory;

    protected $fillable = [
        'season_id',
        'name',
        'slug',
        'description',
        'type',
        'start_date',
        'end_date',
        'image_path',
        'status',
        'registration_status',
        'registration_starts_at',
        'registration_ends_at',
    ];

    protected $casts = [
        'type' => ChampionshipType::class,
        'start_date' => 'date',
        'end_date' => 'date',
        'registration_status' => ChampionshipRegistrationStatus::class,
        'registration_starts_at' => 'datetime',
        'registration_ends_at' => 'datetime',
    ];

    public function season()
    {
        return $this->belongsTo(Season::class);
    }

    public function categories()
    {
        return $this->hasMany(Category::class);
    }

    public function registrationIsOpen(): bool
    {
        if ($this->registration_status !== ChampionshipRegistrationStatus::OPEN) {
            return false;
        }

        $now = now();

        if ($this->registration_starts_at && $now->lt($this->registration_starts_at)) {
            return false;
        }

        if ($this->registration_ends_at && $now->gt($this->registration_ends_at)) {
            return false;
        }

        return true;
    }

    public function registrationWindowLabel(): string
    {
        if (!$this->registration_starts_at && !$this->registration_ends_at) {
            return 'Sin fechas definidas';
        }

        $start = $this->registration_starts_at?->format('d/m/Y H:i') ?? '-';
        $end = $this->registration_ends_at?->format('d/m/Y H:i') ?? '-';

        return "{$start} → {$end}";
    }

    public function registrationRequests()
    {
        return $this->hasMany(ChampionshipRegistrationRequest::class);
    }
}
