<?php

namespace App\Http\Controllers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;

class Controller extends BaseController
{
    use AuthorizesRequests, ValidatesRequests;

    /**
     * Redirección permanente a la URL canónica de una página (GET) cuando se
     * llega con una clave antigua o desactualizada (/trips/386, /countries/1147).
     * Se llama después de autorizar: la URL canónica puede incluir el nombre.
     */
    protected function canonicalRedirect(Request $request, string $parameter, Model $model): ?RedirectResponse
    {
        $route = $request->route();

        if (! $request->isMethod('GET') || $route->originalParameter($parameter) === (string) $model->getRouteKey()) {
            return null;
        }

        $url = route($route->getName(), [...$route->parameters(), $parameter => $model]);
        $query = $request->getQueryString();

        return redirect()->to($query ? "{$url}?{$query}" : $url, 301);
    }
}
