<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Product;
use App\Models\Category;
use Inertia\Inertia;

class ProductController extends Controller
{
    public function index()
    {
        $products = Product::with('category')->latest()->get();
        $categories = Category::all();

        return Inertia::render('Product/Index', [
            'products' => $products,
            'categories' => $categories
        ]);
    }

    public function store(Request $request)
    {
        $category = Category::findOrFail($request->category_id);

        $rules = [
            'category_id' => 'required|exists:categories,id',
            'name' => 'required|string|max:255|unique:products,name',
            'sku' => 'nullable|string|max:100|unique:products,sku',
            'unit' => 'nullable|string|max:50',
            'base_price' => 'required|numeric|min:0',
            'selling_price' => 'required|numeric|min:0',
            'admin_fee' => 'nullable|numeric|min:0',
            'is_active' => 'required|boolean',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
        ];

        // Conditional validation for stock based on category type
        if ($category->type === 'fisik') {
            $rules['stock'] = 'required|numeric|min:0';
        } else {
            $rules['stock'] = 'nullable';
        }

        $request->validate($rules, [
            'name.unique' => 'Nama produk sudah digunakan.',
            'name.required' => 'Nama produk wajib diisi.',
            'sku.unique' => 'SKU sudah digunakan.',
            'base_price.required' => 'Harga modal wajib diisi.',
            'selling_price.required' => 'Harga jual wajib diisi.',
            'stock.required' => 'Stok wajib diisi untuk barang fisik.',
            'image.image' => 'File harus berupa gambar.',
            'image.max' => 'Ukuran gambar maksimal adalah 2MB.',
        ]);

        $imagePath = null;
        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('products', 'public');
            $imagePath = '/storage/' . $path;
        }

        Product::create([
            'category_id' => $request->category_id,
            'sku' => $request->sku,
            'name' => $request->name,
            'image_path' => $imagePath,
            'unit' => $request->unit ?? 'pcs',
            'base_price' => $request->base_price,
            'selling_price' => $request->selling_price,
            'admin_fee' => $request->admin_fee ?? 0,
            'stock' => $category->type === 'fisik' ? $request->stock : null,
            'is_active' => $request->is_active,
        ]);

        return redirect()->back()->with('success', 'Produk berhasil ditambahkan!');
    }

    public function update(Request $request, Product $product)
    {
        $category = Category::findOrFail($request->category_id);

        $rules = [
            'category_id' => 'required|exists:categories,id',
            'name' => 'required|string|max:255|unique:products,name,' . $product->id,
            'sku' => 'nullable|string|max:100|unique:products,sku,' . $product->id,
            'unit' => 'nullable|string|max:50',
            'base_price' => 'required|numeric|min:0',
            'selling_price' => 'required|numeric|min:0',
            'admin_fee' => 'nullable|numeric|min:0',
            'is_active' => 'required|boolean',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
        ];

        if ($category->type === 'fisik') {
            $rules['stock'] = 'required|numeric|min:0';
        } else {
            $rules['stock'] = 'nullable';
        }

        $request->validate($rules, [
            'name.unique' => 'Nama produk sudah digunakan.',
            'name.required' => 'Nama produk wajib diisi.',
            'sku.unique' => 'SKU sudah digunakan.',
            'base_price.required' => 'Harga modal wajib diisi.',
            'selling_price.required' => 'Harga jual wajib diisi.',
            'stock.required' => 'Stok wajib diisi untuk barang fisik.',
            'image.image' => 'File harus berupa gambar.',
            'image.max' => 'Ukuran gambar maksimal adalah 2MB.',
        ]);

        $imagePath = $product->image_path;
        if ($request->hasFile('image')) {
            // Delete old image if exists
            if ($product->image_path) {
                $oldPath = str_replace('/storage/', '', $product->image_path);
                \Storage::disk('public')->delete($oldPath);
            }
            $path = $request->file('image')->store('products', 'public');
            $imagePath = '/storage/' . $path;
        }

        $product->update([
            'category_id' => $request->category_id,
            'sku' => $request->sku,
            'name' => $request->name,
            'image_path' => $imagePath,
            'unit' => $request->unit ?? 'pcs',
            'base_price' => $request->base_price,
            'selling_price' => $request->selling_price,
            'admin_fee' => $request->admin_fee ?? 0,
            'stock' => $category->type === 'fisik' ? $request->stock : null,
            'is_active' => $request->is_active,
        ]);

        return redirect()->back()->with('success', 'Produk berhasil diperbarui!');
    }

    public function destroy(Product $product)
    {
        // Prevent deletion if the product has transaction items (historical data protection)
        $hasTransactions = \DB::table('transaction_items')->where('product_id', $product->id)->exists();
        
        if ($hasTransactions) {
            return redirect()->back()->with('error', 'Produk tidak bisa dihapus karena sudah memiliki riwayat transaksi. Silakan nonaktifkan saja.');
        }

        // Delete associated image
        if ($product->image_path) {
            $oldPath = str_replace('/storage/', '', $product->image_path);
            \Storage::disk('public')->delete($oldPath);
        }

        $product->delete();

        return redirect()->back()->with('success', 'Produk berhasil dihapus!');
    }
}
