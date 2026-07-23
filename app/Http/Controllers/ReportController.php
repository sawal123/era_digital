<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\Transaction;
use App\Models\Product;
use Inertia\Inertia;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function index()
    {
        $transactions = Transaction::with(['items.product.category', 'items.printVendor', 'paymentHistories', 'customer'])->latest()->get();
        $expenses = Expense::orderByDesc('date')->latest()->get();

        return Inertia::render('Reports/Index', [
            'transactions' => $transactions,
            'expenses' => $expenses,
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

    public function updateInvoiceRecipient(Request $request, Transaction $transaction)
    {
        $validated = $request->validate([
            'customer_name' => 'nullable|string|max:255',
            'customer_phone' => 'nullable|string|max:30',
        ]);

        $transaction->update([
            'customer_name' => $validated['customer_name'] ?? null,
            'customer_phone' => $validated['customer_phone'] ?? null,
        ]);

        return redirect()->route('reports.index')
            ->with('success', "Penerima invoice {$transaction->invoice_number} berhasil diperbarui.");
    }
}
