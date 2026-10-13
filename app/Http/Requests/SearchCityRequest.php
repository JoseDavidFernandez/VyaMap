<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SearchCityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'q' => ['required', 'string', 'min:2', 'max:100'],
            // "Buscar en todo el mundo": consulta Geoapify aunque haya coincidencias exactas locales.
            'external' => ['sometimes', 'boolean'],
        ];
    }
}