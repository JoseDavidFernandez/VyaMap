<?php

namespace App\Jobs;

use App\Models\Photo;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\ImageManager;
use Throwable;
use App\Services\PhotoExifService;

class ProcessPhoto implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $photoId,
        public string $temporaryPath,
    ) {
    }

    public function handle(): void
    {
        $photo = Photo::findOrFail($this->photoId);

        $photo->update([
            'processing_status' => 'processing',
        ]);

        try {
            $sourceDisk = Storage::disk('local');
            $publicDisk = Storage::disk('public');

            $sourcePath = $sourceDisk->path($this->temporaryPath);

            $exif = app(PhotoExifService::class)->extract($sourcePath);

            $photo->update([
                'taken_at' => $exif['taken_at'],
                'latitude' => $exif['latitude'],
                'longitude' => $exif['longitude'],
            ]);

            $manager = new ImageManager(new Driver());

            $image = $manager->decodePath($sourcePath);

            $width = $image->width();
            $height = $image->height();

            $directory = 'photos/' . $photo->id;

            $filename = uniqid('photo_', true) . '.webp';
            $thumbnailFilename = pathinfo($filename, PATHINFO_FILENAME) . '_thumb.webp';

            $image->scaleDown(width: 1600);

            $encodedImage = $image->encode(
                new WebpEncoder(85)
            );

            $publicDisk->put(
                $directory . '/' . $filename,
                $encodedImage
            );

            $thumbnail = $manager
                ->decodePath($sourcePath)
                ->scaleDown(width: 400);

            $encodedThumbnail = $thumbnail->encode(
                new WebpEncoder(80)
            );

            $publicDisk->put(
                $directory . '/' . $thumbnailFilename,
                $encodedThumbnail
            );

            $photo->update([
                'path' => $directory . '/' . $filename,
                'thumbnail_path' => $directory . '/' . $thumbnailFilename,
                'size' => $publicDisk->size($directory . '/' . $filename),
                'width' => $width,
                'height' => $height,
                'processing_status' => 'ready',
            ]);

            $sourceDisk->delete($this->temporaryPath);
        } catch (Throwable $exception) {
            $photo->update([
                'processing_status' => 'failed',
                'metadata' => [
                    'processing_error' => $exception->getMessage(),
                ],
            ]);

            throw $exception;
        }
    }
}