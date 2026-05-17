<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Expense;
use Inertia\Inertia;

class ExpenseController extends Controller
{
    public function index()
    {
        $expenses = Expense::latest()->get();
        return Inertia::render('Expenses/Index', [
            'expenses' => $expenses
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'date' => 'required|date',
            'name' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0',
            'category' => 'required|in:stok,operasional,lainnya',
            'note' => 'nullable|string',
        ]);

        Expense::create($request->all());

        return redirect()->back()->with('success', 'Pengeluaran berhasil dicatat!');
    }

    public function destroy(Expense $expense)
    {
        $expense->delete();
        return redirect()->back()->with('success', 'Pengeluaran berhasil dihapus!');
    }
}
