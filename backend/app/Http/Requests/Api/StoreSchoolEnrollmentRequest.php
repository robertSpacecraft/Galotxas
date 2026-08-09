<?php

namespace App\Http\Requests\Api;

use App\Http\Requests\SchoolEnrollmentDataRequest;
use App\Models\SchoolLevel;
use App\Models\SchoolProgram;
use App\Services\PublicIdentityNoticeService;
use App\Services\SchoolEnrollmentNoticeService;
use App\Services\SchoolEnrollmentService;
use Carbon\CarbonImmutable;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreSchoolEnrollmentRequest extends SchoolEnrollmentDataRequest
{
    private CarbonImmutable $requestDate;

    protected function prepareForValidation(): void
    {
        $this->requestDate = CarbonImmutable::now();

        parent::prepareForValidation();

        $this->merge([
            'school_level_id' => $this->input('school_level_id') === ''
                ? null
                : $this->input('school_level_id'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...$this->participantRules(),
            'school_level_id' => ['nullable', 'integer'],
            'privacy_acknowledged' => ['required', 'accepted'],
            'privacy_notice_id' => ['required', 'string', 'max:80'],
            'privacy_notice_version' => ['required', 'string', 'max:20'],
            'website' => ['nullable', 'string', 'max:255'],
            'public_identity_authorization' => [
                Rule::prohibitedIf(! config('public_identity.authorization_enabled')),
                'nullable',
                'array:mode,notice_version,guardian_authority_declared',
            ],
            'public_identity_authorization.mode' => [
                'required_with:public_identity_authorization',
                Rule::in(['alias', 'name_initial', 'anonymous']),
            ],
            'public_identity_authorization.notice_version' => [
                'required_with:public_identity_authorization',
                'string',
                'max:20',
            ],
            'public_identity_authorization.guardian_authority_declared' => [
                Rule::excludeIf(fn (): bool => ! is_array($this->input(
                    'public_identity_authorization'
                )) || $this->input('public_identity_authorization.mode') === 'anonymous'),
                Rule::requiredIf(fn (): bool => in_array(
                    $this->input('public_identity_authorization.mode'),
                    ['alias', 'name_initial'],
                    true
                )),
                'accepted',
            ],
            'school_program_id' => ['prohibited'],
            'user_id' => ['prohibited'],
            'status' => ['prohibited'],
            'requested_at' => ['prohibited'],
            'activated_at' => ['prohibited'],
            'rejected_at' => ['prohibited'],
            'withdrawn_at' => ['prohibited'],
            'admin_notes' => ['prohibited'],
            'is_public' => ['prohibited'],
            'enrollments_open' => ['prohibited'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $allowedFields = [
                'participant_name',
                'participant_birth_date',
                'contact_phone',
                'contact_email',
                'guardian_name',
                'guardian_relationship',
                'school_level_id',
                'privacy_acknowledged',
                'privacy_notice_id',
                'privacy_notice_version',
                'website',
                'public_identity_authorization',
            ];
            $unexpectedFields = array_diff(array_keys($this->all()), $allowedFields);

            if ($unexpectedFields !== []) {
                $validator->errors()->add(
                    'payload',
                    'La solicitud contiene campos no permitidos.'
                );

                return;
            }

            if (
                ! $validator->errors()->hasAny([
                    'privacy_notice_id',
                    'privacy_notice_version',
                ])
                && ! app(SchoolEnrollmentNoticeService::class)->recognizes(
                    (string) $this->input('privacy_notice_id'),
                    (string) $this->input('privacy_notice_version')
                )
            ) {
                $validator->errors()->add(
                    'privacy_notice_version',
                    'La versión del aviso de privacidad de Escuela no está vigente.'
                );
            }

            $authorization = $this->input('public_identity_authorization');
            if (is_array($authorization)) {
                if ($this->participantWasMinor() !== true) {
                    $validator->errors()->add(
                        'public_identity_authorization',
                        'La autorización de representante sólo está disponible para participantes menores.'
                    );
                } else {
                    $notice = app(PublicIdentityNoticeService::class)->current();
                    if (($authorization['notice_version'] ?? null) !== $notice['version']) {
                        $validator->errors()->add(
                            'public_identity_authorization.notice_version',
                            'La versión del aviso de autorización no está vigente.'
                        );
                    }
                }
            }

            if (
                $validator->errors()->has('school_level_id')
                || ! $this->filled('school_level_id')
            ) {
                return;
            }

            $program = SchoolProgram::query()
                ->effectivelyPublic()
                ->first();

            if ($program === null) {
                return;
            }

            $levelIsAvailable = SchoolLevel::query()
                ->whereKey($this->integer('school_level_id'))
                ->where('school_program_id', $program->id)
                ->effectivelyPublic()
                ->exists();

            if (! $levelIsAvailable) {
                $validator->errors()->add(
                    'school_level_id',
                    SchoolEnrollmentService::PUBLIC_LEVEL_ERROR
                );
            }
        });
    }

    protected function referenceDate(): CarbonImmutable
    {
        return $this->requestDate;
    }
}
