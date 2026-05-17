<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Transaction;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;

class ReceivableController extends Controller
{
    /**
     * Display a listing of the receivables.
     */
    public function index()
    {
        $transactions = Transaction::with(['customer', 'cashier'])
            ->whereIn('status_bayar', ['dp', 'piutang'])
            ->orderBy('created_at', 'desc')
            ->get();

        return Inertia::render('Receivables/Index', [
            'transactions' => $transactions
        ]);
    }

    /**
     * Handle debt payment / settlement.
     */
    public function pay(Request $request, Transaction $transaction)
    {
        $request->validate([
            'bayar_nominal' => 'required|numeric|min:1',
        ]);

        DB::beginTransaction();

        try {
            $bayarNominal = $request->bayar_nominal;
            $newJumlahDibayar = $transaction->jumlah_dibayar + $bayarNominal;
            $totalPrice = $transaction->total_price;
            $sisaTagihan = max(0, $totalPrice - $newJumlahDibayar);

            if ($newJumlahDibayar >= $totalPrice) {
                $statusBayar = 'lunas';
                $sisaTagihan = 0;
                $newJumlahDibayar = $totalPrice; // cap at total price
                $paymentStatus = 'paid';
            } else {
                $statusBayar = 'dp';
                $paymentStatus = 'partial';
            }

            $transaction->update([
                'jumlah_dibayar' => $newJumlahDibayar,
                'sisa_tagihan' => $sisaTagihan,
                'status_bayar' => $statusBayar,
                'payment_status' => $paymentStatus,
                'keterangan' => $transaction->keterangan . "\n[Lunas Tambahan Rp " . number_format($bayarNominal, 0, ',', '.') . " pada " . now()->format('d/m/Y H:i') . "]"
            ]);

            // Record chronicled payment history
            $transaction->paymentHistories()->create([
                'jumlah_bayar' => $bayarNominal,
                'tanggal_bayar' => now(),
                'metode_bayar' => 'cash',
                'keterangan' => $statusBayar === 'lunas' ? 'Pelunasan Piutang' : 'Cicilan Piutang',
            ]);

            DB::commit();

            return redirect()->back()->with('success', 'Pembayaran cicilan / pelunasan piutang berhasil disimpan!');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Gagal memproses pelunasan: ' . $e->getMessage());
        }
    }
}
