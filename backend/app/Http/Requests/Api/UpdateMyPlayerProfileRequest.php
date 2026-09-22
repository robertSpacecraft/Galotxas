<?php

namespace App\Http\Requests\Api;

use App\Services\AccountProfileNoticeService;
use App\Services\PlayerProfileNormalizer;
use App\Services\ProfileDeclarationService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateMyPlayerProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $playerId = $this->user()?->player?->id;

        return [
            'nickname' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('players', 'nickname')->ignore($playerId),
            ],
            'dominant_hand' => [
                'nullable',
                'in:right,left,both',
            ],
            'license_number' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('players', 'license_number')->ignore($playerId),
            ],
            'birth_date' => [
                'nullable',
                'date_format:Y-m-d',
                'before:today',
            ],
            'notes' => [
                'nullable',
                'string',
                'max:2000',
            ],
            'profile_declaration_accepted' => [
                Rule::excludeIf(fn (): bool => ! $this->generalDeclarationRequired()),
                Rule::requiredIf(fn (): bool => $this->generalDeclarationRequired()),
                'accepted',
            ],
            'birth_date_confirmed' => [
                Rule::excludeIf(fn (): bool => ! $this->birthDateConfirmationRequired()),
                Rule::requiredIf(fn (): bool => $this->birthDateConfirmationRequired()),
                'accepted',
            ],
            'profile_notice_id' => [
                Rule::excludeIf(fn (): bool => ! $this->noticeRequired()),
                Rule::requiredIf(fn (): bool => $this->noticeRequired()),
                'string',
                'max:80',
            ],
            'profile_notice_version' => [
                Rule::excludeIf(fn (): bool => ! $this->noticeRequired()),
                Rule::requiredIf(fn (): bool => $this->noticeRequired()),
                'string',
                'max:20',
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $normalizer = app(PlayerProfileNormalizer::class);
        $normalized = [];

        if ($this->exists('nickname')) {
            $normalized['nickname'] = $normalizer->nickname($this->input('nickname'));
        }
        if ($this->exists('license_number')) {
            $normalized['license_number'] = $normalizer->licenseNumber($this->input('license_number'));
        }
        if ($this->exists('dominant_hand')) {
            $normalized['dominant_hand'] = $normalizer->optionalText($this->input('dominant_hand'));
        }
        if ($this->exists('notes')) {
            $normalized['notes'] = $normalizer->optionalText($this->input('notes'));
        }
        if ($this->exists('birth_date') && $this->input('birth_date') === '') {
            $normalized['birth_date'] = null;
        }

        $this->merge($normalized);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $allowedFields = [
                'nickname',
                'dominant_hand',
                'license_number',
                'birth_date',
                'notes',
                'profile_declaration_accepted',
                'birth_date_confirmed',
                'profile_notice_id',
                'profile_notice_version',
            ];

            if (array_diff(array_keys($this->all()), $allowedFields) !== []) {
                $validator->errors()->add('payload', 'La solicitud contiene campos no permitidos.');
            }

            if (
                $this->noticeRequired()
                && ! $validator->errors()->hasAny(['profile_notice_id', 'profile_notice_version'])
                && ! app(AccountProfileNoticeService::class)->recognizes(
                    (string) $this->input('profile_notice_id'),
                    (string) $this->input('profile_notice_version')
                )
            ) {
                $validator->errors()->add(
                    'profile_notice_version',
                    'La versión del aviso de cuenta y perfil no está vigente.'
                );
            }
        });
    }

    public function attributes(): array
    {
        return [
            'nickname' => 'apodo',
            'dominant_hand' => 'mano dominante',
            'license_number' => 'número de licencia',
            'birth_date' => 'fecha de nacimiento',
            'notes' => 'notas',
            'profile_declaration_accepted' => 'declaración de exactitud',
            'birth_date_confirmed' => 'confirmación de la fecha de nacimiento',
            'profile_notice_id' => 'aviso de cuenta y perfil',
            'profile_notice_version' => 'versión del aviso de cuenta y perfil',
        ];
    }

    private function generalDeclarationRequired(): bool
    {
        $user = $this->user();

        return $user?->player !== null
            && ! app(ProfileDeclarationService::class)->hasRecognizedGeneral($user);
    }

    private function birthDateConfirmationRequired(): bool
    {
        if (! $this->exists('birth_date') || $this->input('birth_date') === null) {
            return false;
        }

        return $this->input('birth_date') !== $this->user()?->player?->birth_date?->format('Y-m-d');
    }

    private function noticeRequired(): bool
    {
        return $this->generalDeclarationRequired() || $this->birthDateConfirmationRequired();
    }
}
