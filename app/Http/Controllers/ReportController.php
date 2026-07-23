<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\Transaction;
use App\Models\Product;
use App\Models\Customer;
use App\Services\ProfitCalculationService;
use Inertia\Inertia;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function index(ProfitCalculationService $profitCalculator)
    {
        $transactions = Transaction::with(['items.product.category', 'items.printVendor', 'paymentHistories', 'customer'])
            ->withCount('expenseAllocations')
            ->latest()
            ->get();

        $expenses = Expense::with([
                'transaction.customer',
                'allocations.transaction.customer',
                'allocations.transactionItem.product.category',
                'allocations.transactionItem.printVendor',
            ])
            ->orderByDesc('date')
            ->latest()
            ->get()
            ->map(function (Expense $expense) use ($profitCalculator) {
                $expense->setAttribute('finance', $profitCalculator->expenseImpact($expense));

                return $expense;
            });
        $customers = Customer::orderBy('name')->get(['id', 'name', 'phone', 'customer_type']);

        return Inertia::render('Reports/Index', [
            'transactions' => $transactions,
            'expenses' => $expenses,
            'customers' => $customers,
        ]);
    }
    

    public function destroy(Transaction $transaction)
    {
        if ($transaction->expenseAllocations()->exists()) {
            return redirect()->route('reports.index')
                ->withErrors([
                    'error' => 'Transaksi tidak dapat dihapus karena sudah terhubung dengan pembayaran vendor. Lepaskan alokasi pengeluaran terlebih dahulu.',
                ]);
        }

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

    public function updateCustomer(Request $request, Transaction $transaction)
    {
        $validated = $request->validate([
            'customer_id' => 'nullable|exists:customers,id',
            'customer_name' => 'nullable|string|max:255',
            'customer_phone' => 'nullable|string|max:30',
        ]);

        if (!empty($validated['customer_id'])) {
            $customer = Customer::findOrFail($validated['customer_id']);

            $transaction->update([
                'customer_id' => $customer->id,
                'customer_name' => $customer->name,
                'customer_phone' => $customer->phone,
            ]);
        } else {
            $transaction->update([
                'customer_id' => null,
                'customer_name' => $validated['customer_name'] ?? null,
                'customer_phone' => $validated['customer_phone'] ?? null,
            ]);
        }

        return redirect()->route('reports.index')
            ->with('success', "Customer nota {$transaction->invoice_number} berhasil diperbarui.");
    }
}
