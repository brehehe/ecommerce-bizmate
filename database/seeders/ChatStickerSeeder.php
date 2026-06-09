<?php

namespace Database\Seeders;

use App\Models\ChatSticker;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

class ChatStickerSeeder extends Seeder
{
    /**
     * @var array<int, array{name: string, category: string, file: string}>
     */
    private array $stickers = [
        ['name' => 'Halo!',        'category' => 'Sapaan',   'file' => 'hello.png'],
        ['name' => 'Cinta',        'category' => 'Ekspresi', 'file' => 'love.png'],
        ['name' => 'Terima Kasih', 'category' => 'Sapaan',   'file' => 'thanks.png'],
        ['name' => 'Menangis',     'category' => 'Ekspresi', 'file' => 'cry.png'],
    ];

    public function run(): void
    {
        // Remove old SVG dummy stickers
        ChatSticker::whereRaw("image_path LIKE 'chat-stickers/dummy-sticker-%'")->each(function (ChatSticker $sticker) {
            Storage::disk('public')->delete($sticker->image_path);
            $sticker->delete();
        });

        Storage::disk('public')->makeDirectory('chat-stickers');

        $sourceDir = public_path('stickers');

        foreach ($this->stickers as $index => $data) {
            $dest = 'chat-stickers/'.$data['file'];
            $sourcePath = $sourceDir.'/'.$data['file'];

            if (file_exists($sourcePath)) {
                Storage::disk('public')->put($dest, file_get_contents($sourcePath));
            }

            ChatSticker::updateOrCreate(
                ['name' => $data['name']],
                [
                    'category'  => $data['category'],
                    'image_path' => $dest,
                    'order'     => $index + 1,
                    'is_active' => true,
                ]
            );
        }
    }
}
