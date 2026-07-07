<?php

namespace App\Helpers;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class ImageHelper
{
    /**
     * Compress an image's binary data using the PHP GD library.
     *
     * @param  string  $binaryData  The raw binary content of the image.
     * @param  string  $extension  The file extension (e.g., jpg, png, webp, gif).
     * @param  int  $quality  Compression quality (0-100, default 75).
     * @return string The compressed binary content, or original if compression failed or is unsupported.
     */
    public static function compress(string $binaryData, string $extension, int $quality = 75): string
    {
        if (! extension_loaded('gd')) {
            return $binaryData;
        }

        try {
            $image = @imagecreatefromstring($binaryData);
            if (! $image) {
                return $binaryData;
            }

            // Preserve alpha channel information for PNG and WebP
            imagealphablending($image, false);
            imagesavealpha($image, true);

            ob_start();
            $extension = strtolower($extension);

            if ($extension === 'jpeg' || $extension === 'jpg') {
                imagejpeg($image, null, $quality);
            } elseif ($extension === 'webp') {
                imagewebp($image, null, $quality);
            } elseif ($extension === 'png') {
                // Map quality (0-100) to PNG compression level (0-9)
                $compressionLevel = (int) round((100 - $quality) / 11.11);
                $compressionLevel = max(0, min(9, $compressionLevel));
                imagepng($image, null, $compressionLevel);
            } elseif ($extension === 'gif') {
                imagegif($image, null);
            } else {
                imagedestroy($image);
                ob_end_clean();

                return $binaryData;
            }

            $compressedData = ob_get_clean();
            imagedestroy($image);

            return $compressedData ?: $binaryData;
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
     * @param  int  $quality  Compression quality (default 75).
     * @return string The stored file path relative to the disk.
     */
    public static function compressAndStore(UploadedFile $file, string $directory, string $disk = 'public', int $quality = 75): string
    {
        $extension = $file->getClientOriginalExtension() ?: 'jpg';
        $binaryData = file_get_contents($file->getRealPath());

        $isImage = str_starts_with($file->getMimeType(), 'image/');
        if ($isImage) {
            $compressedData = self::compress($binaryData, $extension, $quality);
        } else {
            $compressedData = $binaryData;
        }

        $filename = uniqid('img_', true).'.'.$extension;
        $path = $directory.'/'.$filename;

        Storage::disk($disk)->put($path, $compressedData);

        return $path;
    }
}
