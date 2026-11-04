<?php

namespace App\Actions\Fortify;

use App\Models\User;
use App\Rules\ImageWithinPixelLimit;
use App\Services\AvatarImageService;
use App\Support\Registration;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * @param array<string, mixed> $input
     * @param bool $enforceLimit false solo para users:create (alta manual)
     *
     * @throws ValidationException
     */
    public function create(array $input, bool $enforceLimit = true): User
    {
        Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique(User::class),
            ],
            'password' => $this->passwordRules(),
            // Con las subidas desactivadas se rechaza también en el servidor.
            'avatar' => config('uploads.enabled') ? [
                'nullable',
                'image',
                'mimes:jpeg,jpg,png,webp',
                'max:5120',
                new ImageWithinPixelLimit(ImageWithinPixelLimit::AVATAR_MAX_PIXELS),
            ] : ['prohibited'],
        ])->validate();

        try {
            return $this->store($input, $enforceLimit);
        } catch (UniqueConstraintViolationException) {
            // Otra alta con el mismo correo se adelantó entre la validación y
            // el insert: mismo error que la regla unique, no un 500.
            throw ValidationException::withMessages([
                'email' => __('validation.unique', ['attribute' => 'email']),
            ]);
        }
    }

    private function store(array $input, bool $enforceLimit): User
    {
        return DB::transaction(function () use ($input, $enforceLimit) {
            // El middleware ya cierra el registro al llegar al máximo; aquí se
            // vuelve a contar con bloqueo para que dos altas simultáneas no lo
            // superen.
            if ($enforceLimit && Registration::isFull(User::query()->lockForUpdate()->count())) {
                throw ValidationException::withMessages([
                    'email' => 'No se admiten más registros por ahora.',
                ]);
            }

            $avatar = null;

            if (isset($input['avatar'])) {
                // Recodificado sin EXIF: nunca se publica el archivo original.
                $avatar = app(AvatarImageService::class)->store($input['avatar']);
            }

            return User::create([
                'name' => $input['name'],
                'email' => $input['email'],
                'password' => Hash::make($input['password']),
                'avatar' => $avatar,
            ]);
        });
    }
}
