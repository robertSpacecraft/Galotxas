<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateGameMatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'scheduled_date' => ['required', 'date'],
            'scheduled_time' => ['required', 'date_format:H:i'],
            'venue_id' => ['required', 'exists:venues,id'],
            'status' => ['required', 'in:scheduled,submitted,validated,under_review,postponed,cancelled'],
            'home_score' => ['nullable', 'integer', 'min:0'],
            'away_score' => ['nullable', 'integer', 'min:0'],
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
