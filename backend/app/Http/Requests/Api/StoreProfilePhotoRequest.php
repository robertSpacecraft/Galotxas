<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class StoreProfilePhotoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'photo' => [
                'required',
                'file',
                'mimetypes:image/jpeg,image/png,image/webp',
                'max:3072',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'photo.required' => 'Selecciona una foto de perfil.',
            'photo.file' => 'La foto de perfil debe ser un archivo válido.',
            'photo.mimetypes' => 'La foto debe estar en formato JPEG, PNG o WebP.',
            'photo.max' => 'La foto no puede superar los 3 MB.',
        ];
    }
}
