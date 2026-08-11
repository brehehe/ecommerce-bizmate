<?php

namespace App\Console\Commands;

use App\Services\ImageCompressorService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class CompressStorageImages extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'storage:compress-images
                            {--quality=80 : Compression quality (1-100, default 80)}
                            {--max-width=1200 : Maximum width threshold for resizing}
                            {--max-height=1200 : Maximum height threshold for resizing}
                            {--path= : Specific subdirectory under public/storage or absolute path}
                            {--webp : Convert PNG and JPG files to WebP format and update DB references}
                            {--dry-run : Simulate compression without overwriting files}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Compress and optimize image files in storage to reduce disk space and improve load speeds';

    /**
     * Execute the console command.
     */
    public function handle(ImageCompressorService $compressor): int
    {
        $quality = (int) $this->option('quality');
        $maxWidth = $this->option('max-width') ? (int) $this->option('max-width') : 1200;
        $maxHeight = $this->option('max-height') ? (int) $this->option('max-height') : 1200;
        $subPath = $this->option('path');
        $convertToWebp = (bool) $this->option('webp');
        $dryRun = (bool) $this->option('dry-run');

        $baseDirectory = public_path('storage');

        if ($subPath) {
            $targetPath = str_starts_with($subPath, '/') || str_contains($subPath, ':')
                ? $subPath
                : $baseDirectory.'/'.ltrim($subPath, '/');
        } else {
            $targetPath = $baseDirectory;
        }

        if (! File::exists($targetPath)) {
            $this->error("Directory or file does not exist: {$targetPath}");

            return self::FAILURE;
        }

        $files = [];

        if (File::isFile($targetPath)) {
            $files[] = $targetPath;
        } else {
            $allFiles = File::allFiles($targetPath);
            foreach ($allFiles as $file) {
                $ext = strtolower($file->getExtension());
                if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
                    $files[] = $file->getRealPath();
                }
            }
        }

        $totalFiles = count($files);

        if ($totalFiles === 0) {
            $this->info('No images found to process.');

            return self::SUCCESS;
        }

        $this->info("Found {$totalFiles} image(s) to process.");
        if ($dryRun) {
            $this->warn('DRY RUN MODE ENABLED - No files will be overwritten.');
        }

        $totalOriginalSize = 0;
        $totalNewSize = 0;
        $processedCount = 0;
        $skippedCount = 0;
        $convertedPaths = [];

        $bar = $this->output->createProgressBar($totalFiles);
        $bar->start();

        foreach ($files as $filePath) {
            $origSize = filesize($filePath);
            $totalOriginalSize += $origSize;

            if ($dryRun) {
                $tempPath = sys_get_temp_dir().'/compressed_'.basename($filePath);
                $res = $compressor->compressFile($filePath, $quality, $maxWidth, $maxHeight, $tempPath, $convertToWebp);

                if ($res['success']) {
                    $totalNewSize += $res['new_size'];
                    $processedCount++;
                } else {
                    $totalNewSize += $origSize;
                    $skippedCount++;
                }

                if (file_exists($tempPath)) {
                    @unlink($tempPath);
                }
            } else {
                $res = $compressor->compressFile($filePath, $quality, $maxWidth, $maxHeight, null, $convertToWebp);

                if ($res['success']) {
                    $totalNewSize += $res['new_size'];
                    $processedCount++;

                    if ($convertToWebp && isset($res['target_path']) && $res['target_path'] !== $filePath) {
                        $convertedPaths[$filePath] = $res['target_path'];
                    }
                } else {
                    $totalNewSize += $origSize;
                    $skippedCount++;
                }
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        // Update database references if webp conversion took place
        if ($convertToWebp && ! $dryRun && count($convertedPaths) > 0) {
            $this->info('Updating database image paths to WebP references...');
            $this->updateDatabaseImageReferences($convertedPaths);
        }

        $savedBytes = max(0, $totalOriginalSize - $totalNewSize);
        $origMb = round($totalOriginalSize / (1024 * 1024), 2);
        $newMb = round($totalNewSize / (1024 * 1024), 2);
        $savedMb = round($savedBytes / (1024 * 1024), 2);
        $savedPercent = $totalOriginalSize > 0 ? round(($savedBytes / $totalOriginalSize) * 100, 2) : 0;

        $this->table(
            ['Metric', 'Value'],
            [
                ['Total Images', $totalFiles],
                ['Processed Successfully', $processedCount],
                ['Skipped / Errors', $skippedCount],
                ['Original Total Size', "{$origMb} MB"],
                ['Compressed Total Size', "{$newMb} MB"],
                ['Total Space Saved', "{$savedMb} MB ({$savedPercent}%)"],
            ]
        );

        $this->info($dryRun ? 'Dry run completed!' : 'Image compression completed successfully!');

        return self::SUCCESS;
    }

    /**
     * Update database table references when files are converted to WebP.
     *
     * @param  array<string, string>  $convertedPaths  Map of old path -> new webp path
     */
    protected function updateDatabaseImageReferences(array $convertedPaths): void
    {
        $replacements = [];
        $publicPath = public_path();

        foreach ($convertedPaths as $oldAbsPath => $newAbsPath) {
            $oldRel = ltrim(str_replace($publicPath, '', $oldAbsPath), '/');
            $newRel = ltrim(str_replace($publicPath, '', $newAbsPath), '/');

            // Format variations (e.g. storage/products/xyz.png, /storage/products/xyz.png, products/xyz.png)
            $replacements[$oldRel] = $newRel;
            $replacements['/'.$oldRel] = '/'.$newRel;

            if (str_starts_with($oldRel, 'storage/')) {
                $shortOld = substr($oldRel, strlen('storage/'));
                $shortNew = substr($newRel, strlen('storage/'));
                $replacements[$shortOld] = $shortNew;
                $replacements['/'.$shortOld] = '/'.$shortNew;
            }
        }

        // 1. Update database tables
        $hasProductsImage = DB::getSchemaBuilder()->hasColumn('products', 'image');
        $hasProductImagesPath = DB::getSchemaBuilder()->hasTable('product_images') && DB::getSchemaBuilder()->hasColumn('product_images', 'path');
        $hasCategoriesImage = DB::getSchemaBuilder()->hasTable('categories') && DB::getSchemaBuilder()->hasColumn('categories', 'image');
        $hasBrandsImage = DB::getSchemaBuilder()->hasTable('brands') && DB::getSchemaBuilder()->hasColumn('brands', 'image');

        foreach ($replacements as $oldPath => $newPath) {
            if ($hasProductsImage) {
                DB::table('products')->where('image', $oldPath)->update(['image' => $newPath]);
            }
            if ($hasProductImagesPath) {
                DB::table('product_images')->where('path', $oldPath)->update(['path' => $newPath]);
            }
            if ($hasCategoriesImage) {
                DB::table('categories')->where('image', $oldPath)->update(['image' => $newPath]);
            }
            if ($hasBrandsImage) {
                DB::table('brands')->where('image', $oldPath)->update(['image' => $newPath]);
            }
        }


        // 2. Update settings table JSON values
        $settingKeys = ['hero_banners', 'side_banners', 'middle_wide_banner', 'popup_banner', 'store_logo', 'store_icon'];
        $settings = DB::table('settings')->whereIn('key', $settingKeys)->get();

        foreach ($settings as $setting) {
            $val = $setting->value;
            if (! $val) {
                continue;
            }

            $updated = false;
            foreach ($replacements as $oldPath => $newPath) {
                if (str_contains($val, $oldPath)) {
                    $val = str_replace($oldPath, $newPath, $val);
                    $updated = true;
                }
            }

            if ($updated) {
                DB::table('settings')->where('id', $setting->id)->update(['value' => $val]);
            }
        }
    }
}
