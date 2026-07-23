<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\ExpenseAllocation;
use App\Models\Transaction;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

class ProfitCalculationService
{
    public function summarizePeriod(CarbonInterface|string $startDate, CarbonInterface|string|null $endDate = null): array
    {
        $start = (string) ($startDate instanceof CarbonInterface ? $startDate->toDateString() : $startDate);
        $end = (string) (($endDate ?? $startDate) instanceof CarbonInterface ? ($endDate ?? $startDate)->toDateString() : ($endDate ?? $startDate));

        $transactions = Transaction::query()
            ->whereDate('created_at', '>=', $start)
            ->whereDate('created_at', '<=', $end)
            ->get(['id', 'total_base_price', 'total_price', 'total_profit']);

        $expenses = Expense::query()
            ->with('allocations')
            ->whereDate('date', '>=', $start)
            ->whereDate('date', '<=', $end)
            ->get();

        return $this->summarizeCollections($transactions, $expenses);
    }

    public function summarizeCollections(iterable $transactions, iterable $expenses): array
    {
        $totalSales = $this->sum($transactions, 'total_price');
        $totalBase = $this->sum($transactions, 'total_base_price');
        $grossProfit = $this->sum($transactions, 'total_profit');
        $expenseRows = collect($expenses)->map(fn (Expense $expense) => $this->expenseImpact($expense));
        $additionalHpp = (float) $expenseRows->sum('additional_hpp');
        $operationalExpenses = (float) $expenseRows->sum('operational_expense');

        return [
            'total_sales' => $totalSales,
            'total_base' => $totalBase,
            'gross_profit' => $grossProfit,
            'additional_hpp' => $additionalHpp,
            'unallocated_hpp' => (float) $expenseRows->sum('unallocated_hpp'),
            'operational_expenses' => $operationalExpenses,
            'profit_deducting_costs' => $additionalHpp + $operationalExpenses,
            'cash_out' => (float) $expenseRows->sum('cash_out'),
            'net_profit' => $grossProfit - $additionalHpp - $operationalExpenses,
            'transaction_count' => collect($transactions)->count(),
            'expense_count' => $expenseRows->count(),
        ];
    }

    public function expenseImpact(Expense $expense): array
    {
        $amount = (float) $expense->amount;
        $allocated = $this->allocatedAmount($expense);
        $unallocated = max(0, $amount - $allocated);
        $unallocatedHpp = $expense->category === 'hpp_pesanan' ? $unallocated : 0.0;
        $notInHpp = $expense->category === 'hpp_pesanan'
            ? $this->allocationAmountByStatus($expense, ExpenseAllocation::STATUS_BELUM_MASUK_HPP)
            : 0.0;

        return [
            'allocated_amount' => $allocated,
            'unallocated_amount' => $unallocated,
            'allocation_status' => $this->allocationStatus($amount, $allocated),
            'additional_hpp' => $notInHpp + $unallocatedHpp,
            'unallocated_hpp' => $unallocatedHpp,
            'operational_expense' => $expense->category === 'operasional_rutin' ? $amount : 0.0,
            'cash_out' => $amount,
        ];
    }

    public function allocationStatus(float $amount, float $allocated): string
    {
        if ($allocated <= 0) {
            return 'belum_dialokasikan';
        }

        return $allocated >= $amount ? 'penuh' : 'sebagian';
    }

    private function allocatedAmount(Expense $expense): float
    {
        if ($expense->relationLoaded('allocations')) {
            return (float) $expense->allocations->sum(fn (ExpenseAllocation $allocation) => (float) $allocation->amount);
        }

        return (float) $expense->allocations()->sum('amount');
    }

    private function allocationAmountByStatus(Expense $expense, string $status): float
    {
        if ($expense->relationLoaded('allocations')) {
            return (float) $expense->allocations
                ->where('hpp_status', $status)
                ->sum(fn (ExpenseAllocation $allocation) => (float) $allocation->amount);
        }

        return (float) $expense->allocations()
            ->where('hpp_status', $status)
            ->sum('amount');
    }

    private function sum(iterable $rows, string $key): float
    {
        if ($rows instanceof Collection || $rows instanceof EloquentCollection) {
            return (float) $rows->sum(fn ($row) => (float) ($row->{$key} ?? 0));
        }

        return collect($rows)->sum(fn ($row) => (float) ($row->{$key} ?? 0));
    }
}
