<?php

namespace App\Http\Requests\Api;

use App\Enums\PlayerGender;
use App\Rules\AdultRequiresDni;
use App\Services\AccountProfileNoticeService;
use App\Services\PlayerProfileNormalizer;
use App\Services\ProfileDeclarationService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class CreateMyPlayerProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nickname' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('players', 'nickname'),
            ],
            'dni' => [
                'nullable',
                'string',
                'max:20',
                'unique:players,dni',
                new AdultRequiresDni,
            ],
            'birth_date' => [
                'nullable',
                'date_format:Y-m-d',
                'before:today',
            ],
            'gender' => [
                'nullable',
                Rule::in(PlayerGender::values()),
            ],
            'level' => [
                'required',
                'integer',
                'min:1',
                'max:10',
            ],
            'license_number' => [
                'nullable',
                'string',
                'max:50',
                'unique:players,license_number',
            ],
            'dominant_hand' => [
                'nullable',
                Rule::in(['right', 'left', 'both']),
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
                Rule::excludeIf(fn (): bool => ! $this->filled('birth_date')),
                Rule::requiredIf(fn (): bool => $this->filled('birth_date')),
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

        $this->merge([
            'nickname' => $normalizer->nickname($this->input('nickname')),
            'dni' => $this->filled('dni') ? strtoupper(trim((string) $this->dni)) : null,
            'license_number' => $normalizer->licenseNumber($this->input('license_number')),
            'dominant_hand' => $this->filled('dominant_hand') ? trim((string) $this->dominant_hand) : null,
            'notes' => $normalizer->optionalText($this->input('notes')),
        ]);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (! $this->noticeRequired()) {
                return;
            }

            if (
                ! $validator->errors()->hasAny(['profile_notice_id', 'profile_notice_version'])
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
            'dni' => 'DNI',
            'birth_date' => 'fecha de nacimiento',
            'gender' => 'género',
            'level' => 'nivel',
            'license_number' => 'número de licencia',
            'dominant_hand' => 'mano dominante',
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

        return $user === null
            || ! app(ProfileDeclarationService::class)->hasRecognizedGeneral($user);
    }

    private function noticeRequired(): bool
    {
        return $this->generalDeclarationRequired() || $this->filled('birth_date');
    }
}
