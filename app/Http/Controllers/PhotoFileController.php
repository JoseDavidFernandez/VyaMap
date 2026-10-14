<?php

namespace App\Http\Controllers;

use App\Models\Photo;
use App\Services\PhotoFileManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sirve la imagen optimizada o la miniatura de una foto a su propietario.
 *
 * - Foto ajena, inexistente, sin procesar o sin archivo: 404 (no se revela
 *   si el ID existe).
 * - Cache-Control privado: el navegador puede guardarla, los proxies no.
 * - Nombre genérico en Content-Disposition; la ruta en disco no sale nunca.
 */
class PhotoFileController extends Controller
{
    public const MAX_AGE = 3600;

    public function __invoke(Request $request, Photo $photo, string $variant, PhotoFileManager $files): Response
    {
        abort_unless($request->user()->can('view', $photo), 404);
        abort_unless($photo->processing_status === 'ready', 404);

        $path = $photo->pathFor($variant);
        $disk = $files->locate($photo->id, $path);

        abort_if($disk === null, 404);

        $storage = Storage::disk($disk);

        $response = new BinaryFileResponse(
            $storage->path($path),
            200,
            [
                'Content-Type' => $storage->mimeType($path) ?: 'application/octet-stream',
                'X-Content-Type-Options' => 'nosniff',
            ],
            false,
            'inline',
            true,
            true,
        );

        $response->setContentDisposition('inline', "photo-{$photo->id}-{$variant}.webp");
        $response->setPrivate();
        $response->setMaxAge(self::MAX_AGE);
        $response->isNotModified($request);

        return $response;
    }
}
