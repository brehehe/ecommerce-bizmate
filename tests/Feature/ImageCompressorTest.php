<?php

use App\Helpers\ImageHelper;
use App\Services\ImageCompressorService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Laravel\Facades\Image;

test('image compressor service downscales and compresses high-resolution images', function () {
    $tempPath = sys_get_temp_dir().'/test_large_image.png';

    // Create a 2000x2000 GD test image
    $im = imagecreatetruecolor(2000, 2000);
    $bg = imagecolorallocate($im, 255, 100, 50);
    imagefill($im, 0, 0, $bg);
    imagepng($im, $tempPath);
    imagedestroy($im);

    $service = new ImageCompressorService;
    $res = $service->compressFile($tempPath, quality: 75, maxWidth: 800, maxHeight: 800);

    expect($res['success'])->toBeTrue();
    expect($res['saved_bytes'])->toBeGreaterThan(0);
    expect($res['new_size'])->toBeLessThan($res['original_size']);

    $compressedImage = Image::decodePath($tempPath);
    expect($compressedImage->width())->toBe(800);
    expect($compressedImage->height())->toBe(800);

    @unlink($tempPath);
});

test('storage compress images artisan command compresses storage directory images', function () {
    $storageDir = public_path('storage/test_compress_dir');
    if (! file_exists($storageDir)) {
        mkdir($storageDir, 0777, true);
    }

    $imagePath = $storageDir.'/test_photo.png';
    $im = imagecreatetruecolor(1500, 1500);
    $bg = imagecolorallocate($im, 50, 150, 250);
    imagefill($im, 0, 0, $bg);
    imagepng($im, $imagePath);
    imagedestroy($im);

    $origSize = filesize($imagePath);

    $this->artisan('storage:compress-images', [
        '--path' => 'test_compress_dir',
        '--quality' => 70,
        '--max-width' => 600,
        '--max-height' => 600,
    ])->assertExitCode(0);

    clearstatcache(true, $imagePath);
    $newSize = filesize($imagePath);

    expect($newSize)->toBeLessThan($origSize);

    @unlink($imagePath);
    @rmdir($storageDir);
});

test('image helper compress and store automatically downscales uploaded images', function () {
    Storage::fake('public');

    $file = UploadedFile::fake()->image('banner.jpg', 2400, 2400);

    $path = ImageHelper::compressAndStore($file, 'test-banners', 'public', quality: 75, maxWidth: 1000, maxHeight: 1000);

    expect(Storage::disk('public')->exists($path))->toBeTrue();

    $fullPath = Storage::disk('public')->path($path);
    $image = Image::decodePath($fullPath);

    expect($image->width())->toBeLessThanOrEqual(1000);
    expect($image->height())->toBeLessThanOrEqual(1000);
});
