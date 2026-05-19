<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Models\Expense;
use App\Models\Product;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index()
    {
        $today = now()->toDateString();

        // 1. KARTU STATISTIK (HARI INI)
        $totalOmset = (float) Transaction::whereDate('created_at', $today)->sum('total_price');
        $totalModal = (float) Transaction::whereDate('created_at', $today)->sum('total_base_price');
        $totalPengeluaran = (float) Expense::whereDate('date', $today)->sum('amount');
        
        $totalProfitToday = (float) Transaction::whereDate('created_at', $today)->sum('total_profit');
        $keuntunganBersih = $totalProfitToday - $totalPengeluaran;

        // Keuntungan dari Biaya Admin PPOB hari ini
        $keuntunganPpob = (float) TransactionItem::where('type', 'ppob')
            ->whereHas('transaction', function ($q) use ($today) {
                $q->whereDate('created_at', $today);
            })
            ->sum('profit');

        // 2. GRAFIK PERFORMA (7 HARI TERAKHIR)
        $chartData = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $dateString = $date->toDateString();
            
            $profit = Transaction::whereDate('created_at', $dateString)->sum('total_profit');
            $expense = Expense::whereDate('date', $dateString)->sum('amount');
            $netProfit = $profit - $expense;

            $ppobProfit = (float) TransactionItem::where('type', 'ppob')
                ->whereHas('transaction', function ($q) use ($dateString) {
                    $q->whereDate('created_at', $dateString);
                })
                ->sum('profit');

            $dayName = '';
            switch ($date->dayOfWeek) {
                case 0: $dayName = 'Minggu'; break;
                case 1: $dayName = 'Senin'; break;
                case 2: $dayName = 'Selasa'; break;
                case 3: $dayName = 'Rabu'; break;
                case 4: $dayName = 'Kamis'; break;
                case 5: $dayName = 'Jumat'; break;
                case 6: $dayName = 'Sabtu'; break;
            }

            $chartData[] = [
                'day'         => $dayName,
                'date'        => $date->format('d M'),
                'profit'      => (float) $netProfit,
                'ppob_profit' => $ppobProfit,
            ];
        }

        // 3. WIDGET PERINGATAN STOK (STOK KRITIS <= min_stock)
        $criticalProducts = Product::with('category')
            ->whereHas('category', function($q) {
                $q->where('type', 'fisik');
            })
            ->whereColumn('stock', '<=', 'min_stock')
            ->limit(5)
            ->get();

        return Inertia::render('Dashboard', [
            'stats' => [
                'total_omset'        => $totalOmset,
                'total_modal'        => $totalModal,
                'total_pengeluaran'  => $totalPengeluaran,
                'keuntungan_bersih'  => $keuntunganBersih,
                'keuntungan_ppob'    => $keuntunganPpob,
            ],
            'chartData'        => $chartData,
            'criticalProducts' => $criticalProducts,
        ]);
    }
}
