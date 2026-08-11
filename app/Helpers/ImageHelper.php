<?php

namespace App\Helpers;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Laravel\Facades\Image;

class ImageHelper
{
    /**
     * Compress an image's binary data using Intervention Image / GD library.
     *
     * @param  string  $binaryData  The raw binary content of the image.
     * @param  string  $extension  The file extension (e.g., jpg, png, webp, gif).
     * @param  int  $quality  Compression quality (0-100, default 80).
     * @param  int  $maxWidth  Maximum width threshold (default 1200).
     * @param  int  $maxHeight  Maximum height threshold (default 1200).
     * @return string The compressed binary content, or original if compression failed or is unsupported.
     */
    public static function compress(
        string $binaryData,
        string $extension,
        int $quality = 80,
        int $maxWidth = 1200,
        int $maxHeight = 1200
    ): string {
        try {
            $image = Image::decodeBinary($binaryData);

            if ($maxWidth > 0 || $maxHeight > 0) {
                $image->scaleDown($maxWidth, $maxHeight);
            }

            $ext = strtolower($extension);
            if (in_array($ext, ['jpeg', 'jpg'])) {
                $encoded = $image->encodeUsingFileExtension('jpg', quality: $quality);
            } elseif ($ext === 'webp') {
                $encoded = $image->encodeUsingFileExtension('webp', quality: $quality);
            } elseif ($ext === 'png') {
                $encoded = $image->encodeUsingFileExtension('png', quality: $quality);
            } else {
                return $binaryData;
            }

            return (string) $encoded;
        } catch (\Throwable $e) {
            return $binaryData;
        }
    }

    /**
     * Compress an UploadedFile instance and store it on disk.
     *
     * @param  UploadedFile  $file  The uploaded file.
     * @param  string  $directory  The target directory path within the storage disk.
     * @param  string  $disk  The storage disk (default 'public').
     * @param  int  $quality  Compression quality (default 80).
     * @param  int  $maxWidth  Maximum width constraint (default 1200).
     * @param  int  $maxHeight  Maximum height constraint (default 1200).
     * @return string The stored file path relative to the disk.
     */
    public static function compressAndStore(
        UploadedFile $file,
        string $directory,
        string $disk = 'public',
        int $quality = 80,
        int $maxWidth = 1200,
        int $maxHeight = 1200
    ): string {
        $extension = $file->getClientOriginalExtension() ?: 'jpg';
        $binaryData = file_get_contents($file->getRealPath());

        $isImage = str_starts_with($file->getMimeType() ?? '', 'image/');
        if ($isImage) {
            $compressedData = self::compress($binaryData, $extension, $quality, $maxWidth, $maxHeight);
        } else {
            $compressedData = $binaryData;
        }

        $filename = uniqid('img_', true).'.'.$extension;
        $path = $directory.'/'.$filename;

        Storage::disk($disk)->put($path, $compressedData);

        return $path;
    }
}
