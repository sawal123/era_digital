<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Expense;
use App\Models\Transaction;
use Inertia\Inertia;

class ExpenseController extends Controller
{
    public function index()
    {
        $expenses = Expense::with('transaction.customer')
            ->orderByDesc('date')
            ->latest()
            ->get();

        $transactions = Transaction::select([
                'id',
                'invoice_number',
                'customer_name',
                'customer_id',
                'total_base_price',
                'total_price',
                'created_at',
            ])
            ->with('customer:id,name')
            ->latest()
            ->limit(200)
            ->get();

        return Inertia::render('Expenses/Index', [
            'expenses' => $expenses,
            'transactions' => $transactions,
        ]);
    }

    public function store(Request $request)
    {
        Expense::create($this->validateExpense($request));

        return redirect()->back()->with('success', 'Pengeluaran berhasil dicatat!');
    }

    public function update(Request $request, Expense $expense)
    {
        $expense->update($this->validateExpense($request));

        return redirect()->back()->with('success', 'Pengeluaran berhasil diperbarui!');
    }

    public function destroy(Expense $expense)
    {
        $expense->delete();
        return redirect()->back()->with('success', 'Pengeluaran berhasil dihapus!');
    }

    private function validateExpense(Request $request): array
    {
        $validated = $request->validate([
            'date' => 'required|date',
            'name' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0',
            'category' => 'required|in:hpp_pesanan,pembelian_stok,operasional_rutin,aset_peralatan,pribadi_pemilik',
            'transaction_id' => 'nullable|exists:transactions,id',
            'hpp_status' => 'nullable|in:not_applicable,belum_masuk_hpp,sudah_masuk_hpp',
            'note' => 'nullable|string',
        ]);

        if ($validated['category'] !== 'hpp_pesanan') {
            $validated['transaction_id'] = null;
            $validated['hpp_status'] = 'not_applicable';
        } else {
            $validated['hpp_status'] = $validated['hpp_status'] ?? 'belum_masuk_hpp';
        }

        return $validated;
    }
}
