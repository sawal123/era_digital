<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\StockMovement;
use App\Services\CapitalLedgerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
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
            $product = Product::with('category')->findOrFail($request->product_id);
            $this->assertPhysicalProduct($product);
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
                'reference_type' => 'purchase',
                'reference_id' => $purchase->id,
                'note' => 'Restock: '.($request->note ?? 'Stok masuk supplier'),
            ]);

            // 4. Catat otomatis ke Tabel Pengeluaran (expenses) kategori pembelian stok
            Expense::create([
                'date' => $request->purchase_date,
                'name' => 'Belanja Stok: '.$product->name,
                'amount' => $totalPrice,
                'category' => 'pembelian_stok',
                'purchase_id' => $purchase->id,
                'hpp_status' => 'not_applicable',
                'note' => 'Jumlah: '.parseFloatAsString($request->quantity).' '.$product->unit.' @ Rp '.number_format($request->cost_price, 0, ',', '.').'. '.($request->note ?? ''),
            ]);

            app(CapitalLedgerService::class)->syncPurchase($purchase->fresh('product.category'));

            DB::commit();

            return redirect()->back()->with('success', 'Belanja barang berhasil disimpan, stok & pengeluaran telah diperbarui!');
        } catch (\Exception $e) {
            DB::rollBack();

            return redirect()->back()->with('error', 'Gagal memproses pembelian: '.$e->getMessage());
        }
    }

    /**
     * Update the specified purchase in storage.
     */
    public function update(Request $request, Purchase $purchase)
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
            $oldProduct = Product::with('category')->findOrFail($purchase->product_id);
            $product = Product::with('category')->findOrFail($request->product_id);
            $this->assertPhysicalProduct($product);
            $oldQuantity = (float) $purchase->quantity;
            $newQuantity = (float) $request->quantity;
            $oldTotal = $purchase->total_price;
            $oldPurchaseDate = $purchase->purchase_date;
            $expense = $purchase->expense;

            if (! $expense) {
                $legacyExpenses = Expense::where('category', 'pembelian_stok')
                    ->whereNull('purchase_id')
                    ->where('name', 'Belanja Stok: '.$oldProduct->name)
                    ->where('amount', $oldTotal)
                    ->whereDate('date', $oldPurchaseDate)
                    ->get();
                $expense = $legacyExpenses->count() === 1 ? $legacyExpenses->first() : null;
            }

            $newTotal = $request->quantity * $request->cost_price;
            $stockChanged = $oldProduct->id !== $product->id
                || abs($newQuantity - $oldQuantity) >= 0.01;

            // Update purchase record
            $purchase->update([
                'product_id' => $request->product_id,
                'quantity' => $request->quantity,
                'cost_price' => $request->cost_price,
                'total_price' => $newTotal,
                'purchase_date' => $request->purchase_date,
                'note' => $request->note,
            ]);

            // Adjust stock safely, including the case where the product changes.
            if ($oldProduct->id === $product->id) {
                $diff = round($newQuantity - $oldQuantity, 2);
                if ($diff < 0) {
                    $this->assertStockAvailable($product, abs($diff));
                    $product->decrement('stock', abs($diff));
                } elseif ($diff > 0) {
                    $product->increment('stock', $diff);
                }
            } else {
                $this->assertStockAvailable($oldProduct, $oldQuantity);
                $oldProduct->decrement('stock', $oldQuantity);
                $product->increment('stock', $newQuantity);
            }

            $this->refreshProductBasePrice($oldProduct);
            $this->refreshProductBasePrice($product, (float) $request->cost_price);

            if ($stockChanged) {
                StockMovement::create([
                    'product_id' => $oldProduct->id,
                    'type' => 'out',
                    'quantity' => $oldQuantity,
                    'reference_type' => 'purchase_edit_reversal',
                    'reference_id' => $purchase->id,
                    'note' => 'Pembalik restock sebelum edit: '.$purchase->id,
                ]);
                StockMovement::create([
                    'product_id' => $product->id,
                    'type' => 'in',
                    'quantity' => $newQuantity,
                    'reference_type' => 'purchase_edit',
                    'reference_id' => $purchase->id,
                    'note' => 'Restock setelah edit: '.($request->note ?? 'Stok masuk supplier'),
                ]);
            }

            if ($expense) {
                $expense->update([
                    'purchase_id' => $purchase->id,
                    'name' => 'Belanja Stok: '.$product->name,
                    'date' => $request->purchase_date,
                    'amount' => $newTotal,
                    'note' => 'Jumlah: '.parseFloatAsString($request->quantity).' '.$product->unit.' @ Rp '.number_format($request->cost_price, 0, ',', '.').'. '.($request->note ?? ''),
                ]);
            } else {
                Expense::create([
                    'date' => $request->purchase_date,
                    'name' => 'Belanja Stok: '.$product->name,
                    'amount' => $newTotal,
                    'category' => 'pembelian_stok',
                    'purchase_id' => $purchase->id,
                    'hpp_status' => 'not_applicable',
                    'note' => 'Jumlah: '.parseFloatAsString($request->quantity).' '.$product->unit.' @ Rp '.number_format($request->cost_price, 0, ',', '.').'. '.($request->note ?? ''),
                ]);
            }

            app(CapitalLedgerService::class)->syncPurchase($purchase->fresh('product.category'));

            DB::commit();

            return redirect()->back()->with('success', 'Pembelian berhasil diperbarui.');
        } catch (\Exception $e) {
            DB::rollBack();

            return redirect()->back()->with('error', 'Gagal memperbarui pembelian: '.$e->getMessage());
        }
    }

    /**
     * Remove the specified purchase from storage.
     */
    public function destroy(Purchase $purchase)
    {
        DB::beginTransaction();

        try {
            $product = $purchase->product()->with('category')->first();

            if ($product) {
                $this->assertPhysicalProduct($product);
                $this->assertStockAvailable($product, (float) $purchase->quantity);
            }

            app(CapitalLedgerService::class)->deleteReference('purchase', $purchase->id);

            // Revert stock
            if ($product) {
                $product->decrement('stock', $purchase->quantity);
            }

            if ($product) {
                StockMovement::create([
                    'product_id' => $product->id,
                    'type' => 'out',
                    'quantity' => $purchase->quantity,
                    'reference_type' => 'purchase_reversal',
                    'reference_id' => $purchase->id,
                    'note' => 'Pembatalan restock: '.$purchase->id,
                ]);
            }

            Expense::where('purchase_id', $purchase->id)->delete();

            // Delete purchase
            $purchase->delete();

            if ($product) {
                $this->refreshProductBasePrice($product);
            }

            DB::commit();

            return redirect()->back()->with('success', 'Pembelian berhasil dihapus dan stok telah disesuaikan.');
        } catch (\Exception $e) {
            DB::rollBack();

            return redirect()->back()->with('error', 'Gagal menghapus pembelian: '.$e->getMessage());
        }
    }

    private function assertPhysicalProduct(Product $product): void
    {
        if ($product->category?->type !== 'fisik') {
            throw ValidationException::withMessages([
                'product_id' => 'Restock hanya dapat dilakukan untuk produk fisik.',
            ]);
        }
    }

    private function assertStockAvailable(Product $product, float $quantity): void
    {
        if ((float) ($product->stock ?? 0) + 0.00001 < $quantity) {
            throw ValidationException::withMessages([
                'quantity' => "Stok {$product->name} tidak cukup untuk membatalkan atau mengurangi restock ini.",
            ]);
        }
    }

    private function refreshProductBasePrice(Product $product, ?float $fallback = null): void
    {
        $latestCost = Purchase::query()
            ->where('product_id', $product->id)
            ->orderByDesc('purchase_date')
            ->orderByDesc('id')
            ->value('cost_price');

        $product->update([
            'base_price' => $latestCost !== null ? $latestCost : ($fallback ?? $product->base_price),
        ]);
    }
}

/**
 * Helper to strip trailing zeros for decimal quantities display.
 */
function parseFloatAsString($val)
{
    return (float) $val;
}
