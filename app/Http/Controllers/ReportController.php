<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Models\Product;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function index()
    {
        $transactions = Transaction::with(['items.product', 'paymentHistories', 'customer'])->latest()->get();
        return Inertia::render('Reports/Index', [
            'transactions' => $transactions
        ]);
    }
    

    public function destroy(Transaction $transaction)
    {
        DB::beginTransaction();
        try {
            // Kembalikan stok produk fisik yang terlibat
            foreach ($transaction->items as $item) {
                if ($item->type === 'fisik' && $item->product_id) {
                    $product = Product::find($item->product_id);
                    if ($product && $product->category?->type === 'fisik') {
                        $product->increment('stock', $item->quantity);
                    }
                }
            }

            // Hapus transaksi (cascade ke items & payment_histories via FK)
            $transaction->delete();

            DB::commit();

            return redirect()->route('reports.index')
                ->with('success', "Transaksi {$transaction->invoice_number} berhasil dihapus.");
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->route('reports.index')
                ->with('error', 'Gagal menghapus transaksi: ' . $e->getMessage());
        }
    }
}
