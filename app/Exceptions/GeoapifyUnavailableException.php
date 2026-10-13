<?php

namespace App\Exceptions;

use RuntimeException;

class GeoapifyUnavailableException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('El servicio de búsqueda de ciudades no está disponible.');
    }
}
