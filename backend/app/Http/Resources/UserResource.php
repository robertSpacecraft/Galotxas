<?php

namespace App\Http\Resources;

use App\Services\ProfileDeclarationService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'lastname' => $this->lastname,
            'email' => $this->email,
            'role' => $this->role,
            'active' => $this->active,
            'has_player' => $this->relationLoaded('player') && $this->player !== null,
            'profile_photo' => ProfilePhotoResource::forUser($this->resource),
            'profile_declaration_required' => ! app(ProfileDeclarationService::class)
                ->hasRecognizedGeneral($this->resource),
        ];
    }
}
