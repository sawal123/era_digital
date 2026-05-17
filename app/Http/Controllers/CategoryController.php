<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Category;
use Inertia\Inertia;
use Illuminate\Support\Str;

class CategoryController extends Controller
{
    public function index()
    {
        $categories = Category::withCount('products')->get();
        return Inertia::render('Category/Index', [
            'categories' => $categories
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:categories,name',
            'type' => 'required|in:fisik,jasa,ppob',
        ], [
            'name.unique' => 'Nama kategori sudah digunakan.',
            'name.required' => 'Nama kategori wajib diisi.',
            'type.required' => 'Tipe kategori wajib dipilih.',
        ]);

        Category::create([
            'name' => $request->name,
            'slug' => Str::slug($request->name),
            'type' => $request->type,
        ]);

        return redirect()->back()->with('success', 'Kategori berhasil ditambahkan!');
    }

    public function update(Request $request, Category $category)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:categories,name,' . $category->id,
            'type' => 'required|in:fisik,jasa,ppob',
        ], [
            'name.unique' => 'Nama kategori sudah digunakan.',
            'name.required' => 'Nama kategori wajib diisi.',
            'type.required' => 'Tipe kategori wajib dipilih.',
        ]);

        $category->update([
            'name' => $request->name,
            'slug' => Str::slug($request->name),
            'type' => $request->type,
        ]);

        return redirect()->back()->with('success', 'Kategori berhasil diperbarui!');
    }

    public function destroy(Category $category)
    {
        if ($category->products()->count() > 0) {
            return redirect()->back()->with('error', 'Kategori tidak bisa dihapus karena memiliki produk terkait.');
        }

        $category->delete();

        return redirect()->back()->with('success', 'Kategori berhasil dihapus!');
    }
}
