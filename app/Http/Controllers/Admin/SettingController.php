<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SettingController extends Controller
{
    public function edit()
    {
        // Get all settings and format as key => value pair
        $settings = Setting::pluck('value', 'key')->toArray();

        return Inertia::render('Admin/Settings/Index', [
            'settings' => $settings,
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->except(['_token', 'store_logo']);

        // Handle File Uploads (like Logo)
        if ($request->hasFile('store_logo')) {
            $path = $request->file('store_logo')->store('logos', 'public');
            Setting::updateOrCreate(
                ['key' => 'store_logo'],
                ['value' => '/storage/'.$path]
            );
        }

        // Handle other key-value pairs
        foreach ($data as $key => $value) {
            Setting::updateOrCreate(
                ['key' => $key],
                ['value' => $value]
            );
        }

        return redirect()->back()->with('success', 'Pengaturan berhasil disimpan.');
    }
}
