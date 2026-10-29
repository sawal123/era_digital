<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\ExpenseAllocation;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Services\ProfitCalculationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;

class ExpenseController extends Controller
{
    public function index(ProfitCalculationService $profitCalculator)
    {
        $expenses = Expense::with([
            'transaction.customer',
            'allocations.transaction.customer',
            'allocations.transactionItem.printVendor',
        ])
            ->orderByDesc('date')
            ->latest()
            ->get()
            ->map(function (Expense $expense) use ($profitCalculator) {
                $expense->setAttribute('finance', $profitCalculator->expenseImpact($expense));

                return $expense;
            });

        $transactions = Transaction::select([
            'id',
            'invoice_number',
            'customer_name',
            'customer_id',
            'total_base_price',
            'total_price',
            'total_profit',
            'created_at',
        ])
            ->with([
                'customer:id,name',
                'items' => function ($query) {
                    $query->select([
                        'id',
                        'transaction_id',
                        'print_vendor_id',
                        'item_name',
                        'quantity',
                        'unit',
                        'subtotal_base',
                        'subtotal_price',
                        'profit',
                    ]);
                },
                'items.printVendor:id,name',
            ])
            ->latest()
            ->limit(200)
            ->get();

        return Inertia::render('Expenses/Index', [
            'expenses' => $expenses,
            'transactions' => $transactions,
        ]);
    }

    public function store(Request $request, ProfitCalculationService $profitCalculator)
    {
        DB::transaction(function () use ($request, $profitCalculator) {
            [$expenseData, $allocations] = $this->validateExpense($request, null, $profitCalculator);
            $expense = Expense::create($expenseData);
            $this->syncAllocations($expense, $allocations);
        });

        return redirect()->back()->with('success', 'Pengeluaran berhasil dicatat!');
    }

    public function update(Request $request, Expense $expense, ProfitCalculationService $profitCalculator)
    {
        DB::transaction(function () use ($request, $expense, $profitCalculator) {
            [$expenseData, $allocations] = $this->validateExpense($request, $expense, $profitCalculator);
            $expense->update($expenseData);
            $this->syncAllocations($expense, $allocations);
        });

        return redirect()->back()->with('success', 'Pengeluaran berhasil diperbarui!');
    }

    public function destroy(Expense $expense)
    {
        DB::transaction(function () use ($expense) {
            $expense->delete();
        });

        return redirect()->back()->with('success', 'Pengeluaran berhasil dihapus!');
    }

    private function validateExpense(
        Request $request,
        ?Expense $currentExpense,
        ProfitCalculationService $profitCalculator,
    ): array {
        $validator = Validator::make($request->all(), [
            'date' => 'required|date',
            'name' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0.01',
            'category' => 'required|in:hpp_pesanan,pembelian_stok,operasional_rutin,aset_peralatan,pribadi_pemilik',
            'purchase_id' => 'nullable|exists:purchases,id',
            'transaction_id' => 'nullable|exists:transactions,id',
            'hpp_status' => 'nullable|in:not_applicable,belum_masuk_hpp,sudah_masuk_hpp',
            'allocations' => 'nullable|array',
            'allocations.*.transaction_id' => 'required_with:allocations|exists:transactions,id',
            'allocations.*.transaction_item_id' => 'nullable|exists:transaction_items,id',
            'allocations.*.amount' => 'required_with:allocations|numeric|min:0.01',
            'allocations.*.hpp_status' => 'required_with:allocations|in:belum_masuk_hpp,sudah_masuk_hpp',
            'allocations.*.note' => 'nullable|string',
            'note' => 'nullable|string',
        ]);

        $validator->after(function ($validator) use ($request, $currentExpense, $profitCalculator) {
            $category = $request->input('category');
            $allocations = collect($request->input('allocations', []))
                ->filter(fn ($allocation) => is_array($allocation))
                ->values();

            $purchaseId = $request->input('purchase_id') ?: $currentExpense?->purchase_id;
            if ($category === 'pembelian_stok') {
                if (! $purchaseId) {
                    $validator->errors()->add(
                        'category',
                        'Pembelian stok harus dicatat melalui menu Restock agar tidak salah memengaruhi laba.'
                    );
                } elseif (Expense::query()
                    ->where('purchase_id', $purchaseId)
                    ->when($currentExpense, fn ($query) => $query->where('id', '<>', $currentExpense->id))
                    ->exists()) {
                    $validator->errors()->add(
                        'purchase_id',
                        'Restock tersebut sudah memiliki catatan pengeluaran.'
                    );
                }
            }

            if ($category !== 'hpp_pesanan') {
                return;
            }

            if ($allocations->isEmpty() && $request->filled('transaction_id')) {
                $allocations->push([
                    'transaction_id' => $request->input('transaction_id'),
                    'transaction_item_id' => null,
                    'amount' => $request->input('amount'),
                ]);
            }

            $totalAllocation = 0.0;
            $keys = [];

            foreach ($allocations as $index => $allocation) {
                $transactionId = $allocation['transaction_id'] ?? null;
                $transactionItemId = $allocation['transaction_item_id'] ?? null;
                $amount = (float) ($allocation['amount'] ?? 0);

                $totalAllocation += $amount;

                if ($transactionItemId) {
                    $belongsToTransaction = TransactionItem::whereKey($transactionItemId)
                        ->where('transaction_id', $transactionId)
                        ->exists();

                    if (! $belongsToTransaction) {
                        $validator->errors()->add(
                            "allocations.{$index}.transaction_item_id",
                            'Item pekerjaan wajib berasal dari nota yang dipilih.'
                        );
                    }
                }

                $key = $transactionId.':'.($transactionItemId ?: 'nota');
                if (isset($keys[$key])) {
                    $validator->errors()->add(
                        "allocations.{$index}.transaction_id",
                        'Nota atau item pekerjaan yang sama tidak boleh dipilih dua kali.'
                    );
                }
                $keys[$key] = true;
            }

            if ($totalAllocation > ((float) $request->input('amount', 0)) + 0.00001) {
                $validator->errors()->add('allocations', 'Total alokasi tidak boleh lebih besar dari nominal pengeluaran.');
            }

            $this->validateAllocationCapacity($validator, $allocations, $currentExpense, $profitCalculator);
        });

        $validated = $validator->validate();

        $allocations = $validated['category'] === 'hpp_pesanan'
            ? collect($validated['allocations'] ?? [])->map(fn ($allocation) => [
                'transaction_id' => $allocation['transaction_id'],
                'transaction_item_id' => $allocation['transaction_item_id'] ?? null,
                'amount' => $allocation['amount'],
                'hpp_status' => $allocation['hpp_status'],
                'note' => $allocation['note'] ?? null,
            ])->values()->all()
            : [];

        if ($validated['category'] === 'hpp_pesanan' && empty($allocations) && ! empty($validated['transaction_id'])) {
            $allocations[] = [
                'transaction_id' => $validated['transaction_id'],
                'transaction_item_id' => null,
                'amount' => $validated['amount'],
                'hpp_status' => $validated['hpp_status'] ?? ExpenseAllocation::STATUS_BELUM_MASUK_HPP,
                'note' => $validated['note'] ?? null,
            ];
        }

        unset($validated['allocations']);

        if ($validated['category'] !== 'hpp_pesanan') {
            $validated['transaction_id'] = null;
            $validated['hpp_status'] = 'not_applicable';
        } else {
            $validated['hpp_status'] = $validated['hpp_status'] ?? 'belum_masuk_hpp';
        }

        if ($validated['category'] !== 'pembelian_stok') {
            $validated['purchase_id'] = null;
        } elseif (empty($validated['purchase_id']) && $currentExpense?->purchase_id) {
            $validated['purchase_id'] = $currentExpense->purchase_id;
        }

        return [$validated, $allocations];
    }

    private function validateAllocationCapacity(
        $validator,
        $allocations,
        ?Expense $currentExpense,
        ProfitCalculationService $profitCalculator,
    ): void {
        $transactionIds = $allocations
            ->pluck('transaction_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($transactionIds->isEmpty()) {
            return;
        }

        $transactions = Transaction::query()
            ->with('items')
            ->whereIn('id', $transactionIds)
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        $existingAllocations = ExpenseAllocation::query()
            ->whereIn('transaction_id', $transactionIds)
            ->when($currentExpense, fn ($query) => $query->where('expense_id', '<>', $currentExpense->id))
            ->orderBy('id')
            ->lockForUpdate()
            ->get(['transaction_id', 'transaction_item_id', 'amount']);

        $existingByTransaction = $existingAllocations->groupBy('transaction_id')->map(
            fn ($rows) => (float) $rows->sum(fn ($row) => (float) $row->amount)
        );
        $existingByItem = $existingAllocations
            ->filter(fn ($row) => $row->transaction_item_id)
            ->groupBy('transaction_item_id')
            ->map(fn ($rows) => (float) $rows->sum(fn ($row) => (float) $row->amount));
        $requestedByTransaction = $allocations->groupBy('transaction_id')->map(
            fn ($rows) => (float) $rows->sum(fn ($row) => (float) ($row['amount'] ?? 0))
        );
        $requestedByItem = $allocations
            ->filter(fn ($allocation) => ! empty($allocation['transaction_item_id']))
            ->groupBy('transaction_item_id')
            ->map(fn ($rows) => (float) $rows->sum(fn ($row) => (float) ($row['amount'] ?? 0)));

        foreach ($transactions as $transactionId => $transaction) {
            $transactionBase = $profitCalculator->transactionBase($transaction);
            $allocatedTransaction = (float) ($existingByTransaction[$transactionId] ?? 0)
                + (float) ($requestedByTransaction[$transactionId] ?? 0);

            if ($allocatedTransaction > $transactionBase + 0.00001) {
                $validator->errors()->add(
                    'allocations',
                    'Total alokasi untuk nota '.$transaction->invoice_number
                        .' melebihi HPP yang tersedia (Rp '.number_format($transactionBase, 2, ',', '.').').'
                );
            }
        }

        foreach ($requestedByItem as $itemId => $requestedAmount) {
            $item = $transactions
                ->flatMap(fn (Transaction $transaction) => $transaction->items)
                ->firstWhere('id', (int) $itemId);

            if (! $item) {
                continue;
            }

            $itemBase = $profitCalculator->itemBase($item);
            $allocatedItem = (float) ($existingByItem[$itemId] ?? 0) + $requestedAmount;

            if ($allocatedItem > $itemBase + 0.00001) {
                $validator->errors()->add(
                    'allocations',
                    'Total alokasi untuk item '.$item->item_name
                        .' melebihi HPP item yang tersedia (Rp '.number_format($itemBase, 2, ',', '.').').'
                );
            }
        }
    }

    private function syncAllocations(Expense $expense, array $allocations): void
    {
        if ($expense->category !== 'hpp_pesanan') {
            $expense->allocations()->delete();
            $expense->forceFill([
                'transaction_id' => null,
                'hpp_status' => 'not_applicable',
            ])->save();

            return;
        }

        $expense->allocations()->delete();

        foreach ($allocations as $allocation) {
            $expense->allocations()->create($allocation);
        }

        $this->syncLegacyHppColumns($expense, $allocations);
    }

    private function syncLegacyHppColumns(Expense $expense, array $allocations): void
    {
        if (count($allocations) === 1) {
            $expense->forceFill([
                'transaction_id' => $allocations[0]['transaction_id'],
                'hpp_status' => $allocations[0]['hpp_status'],
            ])->save();

            return;
        }

        $expense->forceFill([
            'transaction_id' => null,
            'hpp_status' => ExpenseAllocation::STATUS_BELUM_MASUK_HPP,
        ])->save();
    }
}
