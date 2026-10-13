<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * El cliente solo indica qué resultado de la búsqueda ha elegido. Nombre,
 * país, región y coordenadas se obtienen en el servidor desde Geoapify:
 * el catálogo de ciudades y países es compartido por todos los usuarios.
 */
class StoreCityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'external_provider' => ['required', 'string', 'in:geoapify'],
            'external_id' => ['required', 'string', 'max:255'],
            'search_query' => ['required', 'string', 'min:2', 'max:100'],
        ];
    }
}
