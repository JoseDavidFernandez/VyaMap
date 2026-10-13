<?php

namespace App\Support;

use Illuminate\Support\Str;

class CityAliasNormalizer
{
    public static function normalize(string $value): string
    {
        return Str::of($value)
            ->trim()
            ->lower()
            ->ascii()
            ->toString();
    }
}
