<?php

namespace App\Http\Requests\Api\Admin;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Request shape only: the identity, modality and duplicate rules belong to
 * CategoryEntryService, which stays authoritative.
 */
class StoreCategoryEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null
            && $user->role === UserRole::ADMIN->value
            && $user->active;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'entry_type' => ['required', 'string', 'in:player,team'],
            'player_id' => [
                'nullable',
                'integer',
                'exists:players,id',
                'required_if:entry_type,player',
                'prohibited_if:entry_type,team',
            ],
            'team_id' => [
                'nullable',
                'integer',
                'exists:teams,id',
                'required_if:entry_type,team',
                'prohibited_if:entry_type,player',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'entry_type.required' => 'Indica si el participante es un jugador o un equipo.',
            'entry_type.in' => 'El tipo de participante debe ser player o team.',
            'player_id.required_if' => 'Un participante de tipo jugador requiere player_id.',
            'player_id.prohibited_if' => 'Un participante de tipo equipo no puede indicar player_id.',
            'player_id.exists' => 'El jugador indicado no existe.',
            'player_id.integer' => 'El identificador del jugador debe ser un número entero.',
            'team_id.required_if' => 'Un participante de tipo equipo requiere team_id.',
            'team_id.prohibited_if' => 'Un participante de tipo jugador no puede indicar team_id.',
            'team_id.exists' => 'El equipo indicado no existe.',
            'team_id.integer' => 'El identificador del equipo debe ser un número entero.',
        ];
    }
}
