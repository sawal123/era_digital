<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

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
            'transactions' => $transactions,
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
            $transaction = Transaction::query()
                ->lockForUpdate()
                ->findOrFail($transaction->id);

            if (! in_array($transaction->status_bayar, ['dp', 'piutang'], true)) {
                throw ValidationException::withMessages([
                    'bayar_nominal' => 'Transaksi ini sudah lunas atau tidak lagi memiliki tagihan.',
                ]);
            }

            $bayarNominal = round((float) $request->bayar_nominal, 2);
            $totalPrice = (float) $transaction->total_price;
            $sisaTagihanSaatIni = max(0, $totalPrice - (float) $transaction->jumlah_dibayar);

            if ($bayarNominal > $sisaTagihanSaatIni + 0.00001) {
                throw ValidationException::withMessages([
                    'bayar_nominal' => 'Nominal pembayaran tidak boleh melebihi sisa tagihan Rp '.number_format($sisaTagihanSaatIni, 0, ',', '.').'.',
                ]);
            }

            $newJumlahDibayar = round((float) $transaction->jumlah_dibayar + $bayarNominal, 2);
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
                'keterangan' => trim(($transaction->keterangan ?? '')."\n[Pembayaran Tambahan Rp ".number_format($bayarNominal, 0, ',', '.').' pada '.now()->format('d/m/Y H:i').']'),
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
        } catch (ValidationException $e) {
            DB::rollBack();
            throw $e;
        } catch (\Exception $e) {
            DB::rollBack();

            return redirect()->back()->with('error', 'Gagal memproses pelunasan: '.$e->getMessage());
        }
    }
}
