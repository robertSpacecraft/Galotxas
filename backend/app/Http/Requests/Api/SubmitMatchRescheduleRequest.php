<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class SubmitMatchRescheduleRequest extends FormRequest
{
    private const MINIMUM_SCHEDULED_DATE = '1000-01-01';

    private const MAXIMUM_FUTURE_YEARS = 2;

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
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
            'venue_id' => ['required', 'integer', 'exists:venues,id'],
            'comment' => ['nullable', 'string', 'max:2000'],
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
            'scheduled_time.required' => 'La hora del partido es obligatoria.',
            'scheduled_time.date_format' => 'La hora del partido debe ser una hora válida con formato HH:MM.',
            'venue_id.required' => 'La pista del partido es obligatoria.',
            'venue_id.integer' => 'La pista seleccionada no es válida.',
            'venue_id.exists' => 'La pista seleccionada no existe.',
            'comment.string' => 'El comentario debe ser un texto válido.',
            'comment.max' => 'El comentario no puede superar los 2.000 caracteres.',
        ];
    }
}
