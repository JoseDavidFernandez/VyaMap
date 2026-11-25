<?php

namespace App\Exceptions;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });

        // Un modelo que no existe (route model binding, findOrFail) es un 404
        // sin detalles. Laravel lo convierte en NotFoundHttpException con el
        // mensaje «No query results for model [App\Models\Trip] 1», y las
        // respuestas JSON incluyen el mensaje de las HttpException aunque
        // APP_DEBUG=false. Así no se ven nombres internos ni ids.
        $this->map(ModelNotFoundException::class, fn (ModelNotFoundException $e) => new NotFoundHttpException('Not Found', $e));
    }
}
