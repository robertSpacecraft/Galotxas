<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateGameMatchRequest extends FormRequest
{
    private const MINIMUM_SCHEDULED_DATE = '1000-01-01';

    private const MAXIMUM_FUTURE_YEARS = 2;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'scheduled_date' => [
                'required',
                'date_format:Y-m-d',
                'after_or_equal:'.self::MINIMUM_SCHEDULED_DATE,
                'before_or_equal:'.today()->addYearsNoOverflow(self::MAXIMUM_FUTURE_YEARS)->toDateString(),
            ],
            'scheduled_time' => ['required', 'date_format:H:i'],
            'venue_id' => ['required', 'exists:venues,id'],
            'status' => ['required', 'in:scheduled,submitted,validated,under_review,postponed,cancelled'],
            'home_score' => ['nullable', 'integer', 'min:0'],
            'away_score' => ['nullable', 'integer', 'min:0'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'scheduled_date.required' => 'La fecha del partido es obligatoria.',
            'scheduled_date.date_format' => 'La fecha del partido debe ser una fecha válida con formato AAAA-MM-DD.',
            'scheduled_date.after_or_equal' => 'La fecha del partido no puede ser anterior al 01/01/1000.',
            'scheduled_date.before_or_equal' => 'La fecha del partido no puede ser posterior a dos años desde hoy.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->has('status')) {
                return;
            }

            $hasScores = $this->filled('home_score') || $this->filled('away_score');
            $statusAcceptsScores = in_array(
                $this->string('status')->toString(),
                ['submitted', 'validated'],
                true
            );

            if ($hasScores && ! $statusAcceptsScores) {
                $validator->errors()->add(
                    'status',
                    'Los tanteos sólo pueden guardarse con estado submitted o validated.'
                );
            }
        });
    }
}
