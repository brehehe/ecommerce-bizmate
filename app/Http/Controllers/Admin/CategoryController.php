<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\ImageHelper;
use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class CategoryController extends Controller
{
    public function index()
    {
        $categories = Category::with('children')->whereNull('parent_id')->orderBy('order')->get();

        return Inertia::render('Admin/Categories/Index', [
            'categories' => $categories,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', Rule::unique('categories', 'slug')->whereNull('deleted_at')],
            'media_type' => ['required', 'in:icon,image'],
            'icon' => ['nullable', 'string', 'max:255'],
            'image' => ['nullable', 'image', 'max:2048'],
            'parent_id' => ['nullable', 'exists:categories,id'],
        ]);

        if ($request->hasFile('image')) {
            $path = ImageHelper::compressAndStore($request->file('image'), 'categories', 'public');
            $validated['image'] = '/storage/'.$path;
        }

        if ($request->media_type === 'icon') {
            $validated['image'] = null;
        } else {
            $validated['icon'] = null;
        }
        unset($validated['media_type']);

        Category::create($validated);

        return back()->with('success', 'Kategori berhasil ditambahkan.');
    }

    public function update(Request $request, Category $category)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', Rule::unique('categories', 'slug')->ignore($category->id)->whereNull('deleted_at')],
            'media_type' => ['required', 'in:icon,image'],
            'icon' => ['nullable', 'string', 'max:255'],
            'image' => ['nullable', 'image', 'max:2048'],
            'parent_id' => ['nullable', 'exists:categories,id'],
        ]);

        if ($request->hasFile('image')) {
            $path = ImageHelper::compressAndStore($request->file('image'), 'categories', 'public');
            $validated['image'] = '/storage/'.$path;
        } else {
            // Keep existing image if not uploading a new one, unless changing to icon
            $validated['image'] = $category->image;
        }

        if ($request->media_type === 'icon') {
            $validated['image'] = null;
        } else {
            $validated['icon'] = null;
        }
        unset($validated['media_type']);

        $category->update($validated);

        return back()->with('success', 'Kategori berhasil diperbarui.');
    }

    public function destroy(Category $category)
    {
        $category->delete();

        return back()->with('success', 'Kategori berhasil dihapus.');
    }

    public function reorder(Request $request)
    {
        $request->validate([
            'categories' => ['required', 'array'],
            'categories.*.id' => ['required', 'exists:categories,id'],
            'categories.*.parent_id' => ['nullable', 'exists:categories,id'],
            'categories.*.order' => ['required', 'integer'],
        ]);

        foreach ($request->categories as $categoryData) {
            Category::where('id', $categoryData['id'])->update([
                'parent_id' => $categoryData['parent_id'] ?? null,
                'order' => $categoryData['order'],
            ]);
        }

        return back()->with('success', 'Urutan kategori berhasil diperbarui.');
    }
}
