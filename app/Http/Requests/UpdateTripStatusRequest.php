<?php

namespace App\Http\Requests;

use App\Models\Trip;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Cambio manual del estado de un viaje. Transiciones permitidas:
 *
 * - planned → completed: solo cuando el viaje ya ha empezado. Nunca se
 *   completa automáticamente por la fecha.
 * - planned → cancelled, cancelled → planned (cancelar y restaurar).
 * - completed → planned (deshacer).
 *
 * Cualquier otra (por ejemplo completed → cancelled o cancelled →
 * completed) se rechaza.
 */
class UpdateTripStatusRequest extends FormRequest
{
    private const TRANSITIONS = [
        Trip::PLANNED => [Trip::COMPLETED, Trip::CANCELLED],
        Trip::CANCELLED => [Trip::PLANNED],
        Trip::COMPLETED => [Trip::PLANNED],
    ];

    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('trip'));
    }

    public function rules(): array
    {
        return [
            'status' => ['required', 'string', Rule::in(Trip::STATUSES)],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->has('status')) {
                    return;
                }

                $trip = $this->route('trip');
                $target = $this->input('status');

                if (! in_array($target, self::TRANSITIONS[$trip->status] ?? [], true)) {
                    $validator->errors()->add('status', "A {$trip->status} trip cannot be changed to {$target}.");

                    return;
                }

                if ($target === Trip::COMPLETED && ! $trip->hasStarted()) {
                    $validator->errors()->add('status', 'A trip can only be marked as completed once it has started.');
                }
            },
        ];
    }
}
