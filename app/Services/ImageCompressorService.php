<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Intervention\Image\Laravel\Facades\Image;
use Throwable;

class ImageCompressorService
{
    /**
     * Compress an image file in-place or output to target path.
     *
     * @param  string  $filePath  Absolute path to the image file
     * @param  int  $quality  Compression quality (1-100)
     * @param  int|null  $maxWidth  Maximum width constraint
     * @param  int|null  $maxHeight  Maximum height constraint
     * @param  string|null  $outputPath  Optional output path (defaults to overwriting input file)
     * @return array{success: bool, original_size: int, new_size: int, saved_bytes: int, saved_percent: float, error?: string}
     */
    public function compressFile(
        string $filePath,
        int $quality = 80,
        ?int $maxWidth = 1200,
        ?int $maxHeight = 1200,
        ?string $outputPath = null,
        bool $convertToWebp = false
    ): array {
        if (! file_exists($filePath) || ! is_readable($filePath)) {
            return [
                'success' => false,
                'original_size' => 0,
                'new_size' => 0,
                'saved_bytes' => 0,
                'saved_percent' => 0.0,
                'error' => 'File does not exist or is not readable: '.$filePath,
            ];
        }

        $originalSize = filesize($filePath);

        if ($originalSize === false || $originalSize === 0) {
            return [
                'success' => false,
                'original_size' => 0,
                'new_size' => 0,
                'saved_bytes' => 0,
                'saved_percent' => 0.0,
                'error' => 'File size is zero',
            ];
        }

        if ($convertToWebp) {
            $pathInfo = pathinfo($filePath);
            $targetPath = $outputPath ?? ($pathInfo['dirname'].'/'.$pathInfo['filename'].'.webp');
        } else {
            $targetPath = $outputPath ?? $filePath;
        }

        try {
            $image = Image::decodePath($filePath);

            // Downscale if image dimensions exceed threshold
            if ($maxWidth !== null || $maxHeight !== null) {
                $image->scaleDown($maxWidth, $maxHeight);
            }

            // Save compressed image (format auto-detected from target extension or webp)
            if ($convertToWebp && ! str_ends_with(strtolower($targetPath), '.webp')) {
                $targetPath = $pathInfo['dirname'].'/'.$pathInfo['filename'].'.webp';
            }

            $image->save($targetPath, quality: $quality);

            // If converted to webp and replacing original non-webp file, remove original
            if ($convertToWebp && $targetPath !== $filePath && file_exists($filePath)) {
                @unlink($filePath);
            }

            // Clear stat cache to read accurate new file size
            clearstatcache(true, $targetPath);
            $newSize = filesize($targetPath);

            if ($newSize === false) {
                $newSize = $originalSize;
            }

            $savedBytes = max(0, $originalSize - $newSize);
            $savedPercent = $originalSize > 0 ? round(($savedBytes / $originalSize) * 100, 2) : 0.0;

            return [
                'success' => true,
                'original_size' => $originalSize,
                'new_size' => $newSize,
                'saved_bytes' => $savedBytes,
                'saved_percent' => $savedPercent,
                'target_path' => $targetPath,
            ];
        } catch (Throwable $e) {

            Log::error('Image compression failed', [
                'file' => $filePath,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'original_size' => $originalSize,
                'new_size' => $originalSize,
                'saved_bytes' => 0,
                'saved_percent' => 0.0,
                'error' => $e->getMessage(),
            ];
        }
    }
}
