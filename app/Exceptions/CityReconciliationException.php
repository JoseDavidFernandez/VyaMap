<?php

namespace App\Exceptions;

use RuntimeException;

class CityReconciliationException extends RuntimeException
{
    /**
     * @param  list<string>  $issues
     */
    public function __construct(public readonly array $issues)
    {
        parent::__construct(implode(' ', $issues));
    }
}
