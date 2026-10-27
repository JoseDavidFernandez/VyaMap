<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use App\Models\City;

class Country extends Model
{
    use HasFactory;

    private const SLUG_MAP = 'vyamap.country-slugs';

    protected $fillable = [
        'name',
        'iso_code',
    ];

    protected static function booted(): void
    {
        // El mapa de slugs se calcula una vez por petición; cualquier cambio en
        // el catálogo lo invalida.
        static::saved(fn () => app()->forgetInstance(self::SLUG_MAP));
        static::deleted(fn () => app()->forgetInstance(self::SLUG_MAP));
    }

    public function cities(): HasMany
    {
        return $this->hasMany(City::class);
    }

    /*
     * URL legible: /countries/italy, derivada del nombre (no se guarda). Si dos
     * países dieran el mismo slug, ambos llevan su código ISO (congo-cg). Se
     * acepta también el id numérico de las URL anteriores, que la página
     * redirige a la canónica.
     */
    public function getRouteKey(): string
    {
        return self::slugMap()[$this->getKey()] ?? (string) $this->getKey();
    }

    public function resolveRouteBinding($value, $field = null)
    {
        if ($field !== null) {
            return parent::resolveRouteBinding($value, $field);
        }

        $value = (string) $value;

        if (ctype_digit($value)) {
            return $this->newQuery()->find((int) $value);
        }

        $id = array_search($value, self::slugMap(), true);

        return $id === false ? null : $this->newQuery()->find($id);
    }

    /**
     * Slug de cada país por id.
     *
     * @return array<int, string>
     */
    public static function slugMap(): array
    {
        $app = app();

        if (! $app->bound(self::SLUG_MAP)) {
            $countries = static::query()->get(['id', 'name', 'iso_code']);
            $base = $countries->mapWithKeys(fn (Country $country) => [$country->id => Str::slug($country->name)]);
            $repeated = $base->countBy()->filter(fn (int $count) => $count > 1);

            $app->instance(self::SLUG_MAP, $countries->mapWithKeys(function (Country $country) use ($base, $repeated) {
                $slug = $base[$country->id];

                if ($slug === '' || $repeated->has($slug)) {
                    $slug = trim($slug . '-' . Str::lower($country->iso_code), '-');
                }

                return [$country->id => $slug];
            })->all());
        }

        return $app->make(self::SLUG_MAP);
    }
}
