<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CmsController extends Controller
{
    /**
     * Display the CMS Banner management page with existing banner data.
     */
    public function banners(): Response
    {
        $heroBannersJson = Setting::where('key', 'hero_banners')->value('value');
        $heroBanners = $heroBannersJson ? json_decode($heroBannersJson, true) : [];

        $sideBannersJson = Setting::where('key', 'side_banners')->value('value');
        $sideBanners = $sideBannersJson ? json_decode($sideBannersJson, true) : [];

        $middleWideBannerJson = Setting::where('key', 'middle_wide_banner')->value('value');
        $middleWideBanner = $middleWideBannerJson ? json_decode($middleWideBannerJson, true) : null;

        return Inertia::render('Admin/Cms/Banners', [
            'heroBanners' => $heroBanners,
            'sideBanners' => $sideBanners,
            'middleWideBanner' => $middleWideBanner,
            'storefrontUrl' => url('/'),
        ]);
    }

    /**
     * Update the CMS banners and handle uploaded image files.
     */
    public function updateBanners(Request $request): RedirectResponse
    {
        $heroBanners = [];
        if ($request->has('hero_banners')) {
            foreach ($request->input('hero_banners') as $index => $banner) {
                $imagePath = $banner['image'] ?? '';

                // Handle file upload for this specific hero banner index
                if ($request->hasFile("hero_files.{$index}")) {
                    $file = $request->file("hero_files.{$index}");
                    $path = $file->store('banners', 'public');
                    $imagePath = '/storage/'.$path;
                }

                $heroBanners[] = [
                    'image' => $imagePath,
                    'alt' => $banner['alt'] ?? '',
                    'link' => $banner['link'] ?? '#',
                ];
            }
        }

        $sideBanners = [];
        if ($request->has('side_banners')) {
            foreach ($request->input('side_banners') as $index => $banner) {
                $imagePath = $banner['image'] ?? '';

                // Handle file upload for this specific side banner index
                if ($request->hasFile("side_files.{$index}")) {
                    $file = $request->file("side_files.{$index}");
                    $path = $file->store('banners', 'public');
                    $imagePath = '/storage/'.$path;
                }

                $sideBanners[] = [
                    'image' => $imagePath,
                    'alt' => $banner['alt'] ?? '',
                    'link' => $banner['link'] ?? '#',
                ];
            }
        }

        Setting::updateOrCreate(
            ['key' => 'hero_banners'],
            ['value' => json_encode($heroBanners)]
        );

        Setting::updateOrCreate(
            ['key' => 'side_banners'],
            ['value' => json_encode($sideBanners)]
        );

        if ($request->has('middle_wide_banner')) {
            $middleInput = $request->input('middle_wide_banner');
            $imagePath = $middleInput['image'] ?? '';

            if ($request->hasFile('middle_wide_file')) {
                $file = $request->file('middle_wide_file');
                $path = $file->store('banners', 'public');
                $imagePath = '/storage/'.$path;
            }

            $middleWideBanner = [
                'image' => $imagePath,
                'alt' => $middleInput['alt'] ?? '',
                'link' => $middleInput['link'] ?? '#',
            ];

            Setting::updateOrCreate(
                ['key' => 'middle_wide_banner'],
                ['value' => json_encode($middleWideBanner)]
            );
        }

        return redirect()->back()->with('success', 'Banner berhasil diperbarui.');
    }
}
