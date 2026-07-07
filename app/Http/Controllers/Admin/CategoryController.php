<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\ImageHelper;
use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
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
            'image' => [
                Rule::requiredIf(fn () => $request->media_type === 'image'),
                'nullable',
                'image',
                'max:2048',
                function ($attribute, $value, $fail) {
                    if ($value instanceof UploadedFile) {
                        $size = getimagesize($value->getRealPath());
                        if ($size) {
                            $width = $size[0];
                            $height = $size[1];
                            if ($height < $width) {
                                $fail('Gambar harus memiliki rasio 1:1 (persegi) atau portrait (tinggi lebih besar atau sama dengan lebar).');
                            }
                        } else {
                            $fail('File yang diupload bukan gambar yang valid.');
                        }
                    }
                },
            ],
            'parent_id' => ['nullable', 'exists:categories,id'],
        ], [
            'image.required_if' => 'Gambar wajib diunggah saat tipe media visual adalah gambar.',
            'image.image' => 'File harus berupa gambar.',
            'image.max' => 'Ukuran gambar tidak boleh lebih dari 2MB.',
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
            'image' => [
                Rule::requiredIf(fn () => $request->media_type === 'image' && ! $category->image),
                'nullable',
                'image',
                'max:2048',
                function ($attribute, $value, $fail) {
                    if ($value instanceof UploadedFile) {
                        $size = getimagesize($value->getRealPath());
                        if ($size) {
                            $width = $size[0];
                            $height = $size[1];
                            if ($height < $width) {
                                $fail('Gambar harus memiliki rasio 1:1 (persegi) atau portrait (tinggi lebih besar atau sama dengan lebar).');
                            }
                        } else {
                            $fail('File yang diupload bukan gambar yang valid.');
                        }
                    }
                },
            ],
            'parent_id' => ['nullable', 'exists:categories,id'],
        ], [
            'image.required_if' => 'Gambar wajib diunggah saat tipe media visual adalah gambar.',
            'image.image' => 'File harus berupa gambar.',
            'image.max' => 'Ukuran gambar tidak boleh lebih dari 2MB.',
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
        \DB::transaction(function () use ($category) {
            $category->children()->delete();
            $category->delete();
        });

        return back()->with('success', 'Kategori berhasil dihapus.');
    }

    public function bulkDelete(Request $request)
    {
        $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'exists:categories,id',
        ]);

        $ids = $request->input('ids');

        \DB::transaction(function () use ($ids) {
            foreach ($ids as $id) {
                $category = Category::find($id);
                if ($category) {
                    $category->children()->delete();
                    $category->delete();
                }
            }
        });

        return redirect()->back()->with('success', 'Kategori terpilih berhasil dihapus.');
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
