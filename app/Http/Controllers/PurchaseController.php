<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Purchase;
use App\Models\Expense;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class PurchaseController extends Controller
{
    /**
     * Display a listing of purchases.
     */
    public function index()
    {
        // Ambil produk fisik saja yang terdaftar di database
        $products = Product::with('category')
            ->whereHas('category', function ($q) {
                $q->where('type', 'fisik');
            })
            ->orderBy('name')
            ->get();

        // Ambil histori restock diurutkan paling baru
        $purchases = Purchase::with('product.category')
            ->orderBy('id', 'desc')
            ->get();

        return Inertia::render('Purchases/Index', [
            'products' => $products,
            'purchases' => $purchases,
        ]);
    }

    /**
     * Store a newly created purchase in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|numeric|min:0.01',
            'cost_price' => 'required|numeric|min:0',
            'purchase_date' => 'required|date',
            'note' => 'nullable|string',
        ]);

        DB::beginTransaction();

        try {
            $product = Product::findOrFail($request->product_id);
            $totalPrice = $request->quantity * $request->cost_price;

            // 1. Simpan Catatan Pembelian
            $purchase = Purchase::create([
                'product_id' => $request->product_id,
                'quantity' => $request->quantity,
                'cost_price' => $request->cost_price,
                'total_price' => $totalPrice,
                'purchase_date' => $request->purchase_date,
                'note' => $request->note,
            ]);

            // 2. Tambah Stok Produk & Update Harga Modal (base_price)
            $product->increment('stock', $request->quantity);
            $product->update(['base_price' => $request->cost_price]);

            // 3. Catat ke Stock Movements (type: 'in')
            StockMovement::create([
                'product_id' => $request->product_id,
                'type' => 'in',
                'quantity' => $request->quantity,
                'reference_id' => $purchase->id,
                'note' => 'Restock: ' . ($request->note ?? 'Stok masuk supplier')
            ]);

            // 4. Catat otomatis ke Tabel Pengeluaran (expenses) kategori pembelian stok
            Expense::create([
                'date' => $request->purchase_date,
                'name' => 'Belanja Stok: ' . $product->name,
                'amount' => $totalPrice,
                'category' => 'pembelian_stok',
                'hpp_status' => 'not_applicable',
                'note' => 'Jumlah: ' . parseFloatAsString($request->quantity) . ' ' . $product->unit . ' @ Rp ' . number_format($request->cost_price, 0, ',', '.') . '. ' . ($request->note ?? '')
            ]);

            DB::commit();

            return redirect()->back()->with('success', 'Belanja barang berhasil disimpan, stok & pengeluaran telah diperbarui!');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Gagal memproses pembelian: ' . $e->getMessage());
        }
    }
}

/**
 * Helper to strip trailing zeros for decimal quantities display.
 */
function parseFloatAsString($val) {
    return (float) $val;
}
