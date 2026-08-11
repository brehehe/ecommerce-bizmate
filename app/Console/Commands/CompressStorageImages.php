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
    /**
     * Update database table references when files are converted to WebP.
     *
     * @param  array<string, string>  $convertedPaths  Map of old path -> new webp path
     */
    protected function updateDatabaseImageReferences(array $convertedPaths): void
    {
        // 1. Update products table 'image' column
        if (DB::getSchemaBuilder()->hasColumn('products', 'image')) {
            foreach (DB::table('products')->whereNotNull('image')->get() as $p) {
                $newPath = preg_replace('/\.(png|jpg|jpeg)$/i', '.webp', $p->image);
                if ($newPath !== $p->image) {
                    DB::table('products')->where('id', $p->id)->update(['image' => $newPath]);
                }
            }
        }

        // 2. Update product_images table 'path' column
        if (DB::getSchemaBuilder()->hasTable('product_images') && DB::getSchemaBuilder()->hasColumn('product_images', 'path')) {
            foreach (DB::table('product_images')->whereNotNull('path')->get() as $pi) {
                $newPath = preg_replace('/\.(png|jpg|jpeg)$/i', '.webp', $pi->path);
                if ($newPath !== $pi->path) {
                    DB::table('product_images')->where('id', $pi->id)->update(['path' => $newPath]);
                }
            }
        }

        // 3. Update categories table 'image' column
        if (DB::getSchemaBuilder()->hasTable('categories') && DB::getSchemaBuilder()->hasColumn('categories', 'image')) {
            foreach (DB::table('categories')->whereNotNull('image')->get() as $c) {
                $newPath = preg_replace('/\.(png|jpg|jpeg)$/i', '.webp', $c->image);
                if ($newPath !== $c->image) {
                    DB::table('categories')->where('id', $c->id)->update(['image' => $newPath]);
                }
            }
        }

        // 4. Update brands table 'image' column if exists
        if (DB::getSchemaBuilder()->hasTable('brands') && DB::getSchemaBuilder()->hasColumn('brands', 'image')) {
            foreach (DB::table('brands')->whereNotNull('image')->get() as $b) {
                $newPath = preg_replace('/\.(png|jpg|jpeg)$/i', '.webp', $b->image);
                if ($newPath !== $b->image) {
                    DB::table('brands')->where('id', $b->id)->update(['image' => $newPath]);
                }
            }
        }

        // 5. Update settings table JSON values
        $settingKeys = ['hero_banners', 'side_banners', 'middle_wide_banner', 'popup_banner', 'store_logo', 'store_icon'];
        $settings = DB::table('settings')->whereIn('key', $settingKeys)->get();

        foreach ($settings as $setting) {
            $val = $setting->value;
            if (! $val) {
                continue;
            }

            $newVal = preg_replace('/\.(png|jpg|jpeg)/i', '.webp', $val);
            if ($newVal !== $val) {
                DB::table('settings')->where('id', $setting->id)->update(['value' => $newVal]);
            }
        }
    }
}
