<?php

namespace App\Console\Commands;

use App\Actions\Fortify\CreateNewUser;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

/**
 * Alta de cuentas cuando el registro público está desactivado.
 *
 *   php artisan users:create "Nombre" correo@example.com
 *
 * La contraseña se pide de forma oculta y pasa por las mismas reglas que
 * el formulario de registro (CreateNewUser).
 */
class CreateUser extends Command
{
    protected $signature = 'users:create {name : Nombre visible} {email : Correo de acceso}';

    protected $description = 'Crea una cuenta con las mismas reglas que el registro';

    public function handle(CreateNewUser $creator): int
    {
        $password = (string) $this->secret('Contraseña');
        $confirmation = (string) $this->secret('Repite la contraseña');

        try {
            $user = $creator->create([
                'name' => $this->argument('name'),
                'email' => $this->argument('email'),
                'password' => $password,
                'password_confirmation' => $confirmation,
            ]);
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $messages) {
                foreach ($messages as $message) {
                    $this->error($message);
                }
            }

            return self::FAILURE;
        }

        $this->info("Cuenta creada (id {$user->id}).");

        return self::SUCCESS;
    }
}
