<?php

namespace App\Console\Commands;

use App\Models\Photo;
use App\Services\PhotoFileManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Migra los archivos de fotos procesadas antes de la fase 04 (H1) del disco
 * público al privado. Las rutas relativas no cambian, así que la base de
 * datos no se modifica y PhotoFileController sirve la copia privada en
 * cuanto existe.
 *
 *   php artisan photos:move-to-private                  simulación (no cambia nada)
 *   php artisan photos:move-to-private --copy           copia y verifica (SHA-256); no borra
 *   php artisan photos:move-to-private --remove-public  borra las copias públicas ya verificadas
 *   php artisan photos:move-to-private --revert=<manifiesto>
 *                                                       restaura las copias públicas y quita las privadas
 *
 * Cada fase escribe un manifiesto JSON en storage/app/photo-migration/.
 * Los archivos de fotos que ya no existen en la base de datos (huérfanos)
 * solo se listan: no se copian ni se borran.
 */
class MovePhotosToPrivate extends Command
{
    protected $signature = 'photos:move-to-private
        {--copy : Copia los archivos al disco privado y verifica cada copia}
        {--remove-public : Borra las copias públicas cuya copia privada es idéntica}
        {--revert= : Manifiesto (nombre del archivo) de una fase anterior a deshacer}';

    protected $description = 'Mueve las fotos procesadas del disco público al privado de forma verificable y reversible';

    public const MANIFEST_DIRECTORY = 'photo-migration';

    public function handle(): int
    {
        $selected = array_filter([
            'copy' => $this->option('copy'),
            'remove-public' => $this->option('remove-public'),
            'revert' => $this->option('revert') !== null,
        ]);

        if (count($selected) > 1) {
            $this->error('Elige una sola fase: --copy, --remove-public o --revert.');

            return self::FAILURE;
        }

        if ($this->option('revert') !== null) {
            return $this->revert((string) $this->option('revert'));
        }

        $plan = $this->plan();

        $this->table(['estado', 'archivos'], collect($plan['counts'])->map(fn ($n, $k) => [$k, $n])->values()->all());

        if ($plan['orphans'] !== []) {
            $this->warn(count($plan['orphans']) . ' archivos públicos no pertenecen a ninguna foto (huérfanos): no se tocan.');
        }

        if ($this->option('copy')) {
            return $this->copy($plan['files']);
        }

        if ($this->option('remove-public')) {
            return $this->removePublic($plan['files']);
        }

        $this->info('Simulación: no se ha modificado nada.');

        return self::SUCCESS;
    }

    /**
     * Archivos referenciados por fotos listas, con su estado en cada disco.
     *
     * @return array{files: list<array{photo_id: int, path: string, state: string}>, counts: array<string, int>, orphans: list<string>}
     */
    private function plan(): array
    {
        $public = Storage::disk(PhotoFileManager::LEGACY_DISK);
        $private = Storage::disk(PhotoFileManager::DISK);

        $files = [];
        $referenced = [];

        Photo::query()->where('processing_status', 'ready')->orderBy('id')->each(function (Photo $photo) use (&$files, &$referenced, $public, $private) {
            foreach (array_unique(array_filter([$photo->path, $photo->thumbnail_path])) as $path) {
                if (! str_starts_with($path, PhotoFileManager::OUTPUT_DIRECTORY . "/{$photo->id}/") || str_contains($path, '..')) {
                    $files[] = ['photo_id' => $photo->id, 'path' => $path, 'state' => 'ruta no válida'];

                    continue;
                }

                $referenced[$path] = true;
                $inPublic = $public->exists($path);
                $inPrivate = $private->exists($path);

                $state = match (true) {
                    $inPrivate && $inPublic => $this->sameContent($path) ? 'copiado (pendiente de borrar el público)' : 'DIFERENTE en ambos discos',
                    $inPrivate => 'migrado',
                    $inPublic => 'pendiente de copiar',
                    default => 'sin archivo',
                };

                $files[] = ['photo_id' => $photo->id, 'path' => $path, 'state' => $state];
            }
        });

        $orphans = array_values(array_filter(
            $public->allFiles(PhotoFileManager::OUTPUT_DIRECTORY),
            fn (string $path) => ! isset($referenced[$path])
        ));

        return [
            'files' => $files,
            'counts' => collect($files)->countBy('state')->sortKeys()->all(),
            'orphans' => $orphans,
        ];
    }

    private function copy(array $files): int
    {
        $public = Storage::disk(PhotoFileManager::LEGACY_DISK);
        $private = Storage::disk(PhotoFileManager::DISK);
        $copied = [];

        foreach ($files as $file) {
            if ($file['state'] !== 'pendiente de copiar') {
                continue;
            }

            $private->put($file['path'], $public->get($file['path']));

            if (! $this->sameContent($file['path'])) {
                $private->delete($file['path']);
                $this->error("La copia de {$file['path']} no coincide; se ha descartado. Revisa antes de continuar.");
                $this->writeManifest('copy', $copied);

                return self::FAILURE;
            }

            $copied[] = ['photo_id' => $file['photo_id'], 'path' => $file['path'], 'sha256' => hash('sha256', $private->get($file['path']))];
        }

        $manifest = $this->writeManifest('copy', $copied);
        $this->info(count($copied) . " archivos copiados y verificados. Manifiesto: {$manifest}. Las copias públicas siguen intactas.");

        return self::SUCCESS;
    }

    private function removePublic(array $files): int
    {
        $ready = array_values(array_filter($files, fn ($file) => $file['state'] === 'copiado (pendiente de borrar el público)'));

        if ($ready === []) {
            $this->info('No hay copias públicas verificadas que borrar.');

            return self::SUCCESS;
        }

        if (! $this->confirm('¿Borrar ' . count($ready) . ' copias públicas? Las privadas son idénticas.', false)) {
            $this->warn('Cancelado.');

            return self::FAILURE;
        }

        $public = Storage::disk(PhotoFileManager::LEGACY_DISK);
        $removed = [];

        foreach ($ready as $file) {
            $removed[] = ['photo_id' => $file['photo_id'], 'path' => $file['path'], 'sha256' => hash('sha256', $public->get($file['path']))];
            $public->delete($file['path']);
        }

        $manifest = $this->writeManifest('remove-public', $removed);
        $this->info(count($removed) . " copias públicas borradas. Manifiesto: {$manifest}.");

        return self::SUCCESS;
    }

    /**
     * Deshace una fase: tras --remove-public restaura las copias públicas
     * desde las privadas; tras --copy quita las copias privadas creadas.
     */
    private function revert(string $name): int
    {
        $path = self::MANIFEST_DIRECTORY . '/' . basename($name);
        $local = Storage::disk('local');

        if (! $local->exists($path)) {
            $this->error("No existe el manifiesto {$path}.");

            return self::FAILURE;
        }

        $manifest = json_decode($local->get($path), true);
        $public = Storage::disk(PhotoFileManager::LEGACY_DISK);
        $private = Storage::disk(PhotoFileManager::DISK);
        $reverted = 0;

        foreach ($manifest['files'] ?? [] as $file) {
            $filePath = $file['path'];

            if (! str_starts_with($filePath, PhotoFileManager::OUTPUT_DIRECTORY . '/') || str_contains($filePath, '..')) {
                continue;
            }

            if ($manifest['phase'] === 'remove-public' && ! $public->exists($filePath) && $private->exists($filePath)) {
                $public->put($filePath, $private->get($filePath));
                $reverted++;
            }

            if ($manifest['phase'] === 'copy' && $public->exists($filePath) && $private->exists($filePath)) {
                $private->delete($filePath);
                $reverted++;
            }
        }

        $this->info("{$reverted} archivos revertidos ({$manifest['phase']}).");

        return self::SUCCESS;
    }

    private function sameContent(string $path): bool
    {
        $public = Storage::disk(PhotoFileManager::LEGACY_DISK);
        $private = Storage::disk(PhotoFileManager::DISK);

        return hash('sha256', (string) $public->get($path)) === hash('sha256', (string) $private->get($path));
    }

    private function writeManifest(string $phase, array $files): string
    {
        $name = sprintf('%s-%s.json', now()->format('Ymd\THis'), $phase);

        Storage::disk('local')->put(self::MANIFEST_DIRECTORY . '/' . $name, json_encode([
            'phase' => $phase,
            'created_at' => now()->toIso8601String(),
            'files' => $files,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return $name;
    }
}
