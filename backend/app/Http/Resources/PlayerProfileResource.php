<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlayerProfileResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nickname' => $this->nickname,
            'slug' => $this->slug,
            'dni' => $this->dni,
            'birth_date' => $this->birth_date?->format('Y-m-d'),
            'gender' => $this->gender?->value ?? $this->gender,
            'level' => $this->level,
            'license_number' => $this->license_number,
            'dominant_hand' => $this->dominant_hand,
            'notes' => $this->notes,
            'active' => $this->active,

            'user' => $this->whenLoaded('user', function () {
                return [
                    'id' => $this->user?->id,
                    'name' => $this->user?->name,
                    'lastname' => $this->user?->lastname,
                    'email' => $this->user?->email,
                    'role' => $this->user?->role,
                    'active' => $this->user?->active,
                    'profile_photo_path' => $this->user?->profile_photo_path,
                ];
            }),

            'display_name' => $this->nickname ?: trim(($this->user?->name ?? '') . ' ' . ($this->user?->lastname ?? '')),
            'full_name' => trim(($this->user?->name ?? '') . ' ' . ($this->user?->lastname ?? '')),

            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
