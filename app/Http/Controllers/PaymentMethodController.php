<?php

namespace App\Http\Controllers;

use App\Models\PaymentMethod;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class PaymentMethodController extends Controller
{
    public function index()
    {
        return Inertia::render('PaymentMethods/Index', [
            'paymentMethods' => PaymentMethod::withCount('transactions')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validatedData($request);

        if ($data['is_cash']) {
            PaymentMethod::where('is_cash', true)->update(['is_cash' => false]);
        }

        PaymentMethod::create($data);

        return redirect()->back()->with('success', 'Metode pembayaran berhasil ditambahkan!');
    }

    public function update(Request $request, PaymentMethod $paymentMethod)
    {
        $data = $this->validatedData($request, $paymentMethod);

        if ($data['is_cash']) {
            PaymentMethod::where('is_cash', true)
                ->where('id', '!=', $paymentMethod->id)
                ->update(['is_cash' => false]);
        }

        $paymentMethod->update($data);

        return redirect()->back()->with('success', 'Metode pembayaran berhasil diperbarui!');
    }

    public function destroy(PaymentMethod $paymentMethod)
    {
        if ($paymentMethod->transactions()->exists()) {
            return redirect()->back()->with('error', 'Metode pembayaran tidak bisa dihapus karena sudah digunakan pada transaksi.');
        }

        $paymentMethod->delete();

        return redirect()->back()->with('success', 'Metode pembayaran berhasil dihapus!');
    }

    private function validatedData(Request $request, ?PaymentMethod $paymentMethod = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => [
                'required',
                'string',
                'max:50',
                'alpha_dash',
                Rule::unique('payment_methods', 'code')->ignore($paymentMethod?->id),
            ],
            'is_cash' => ['required', 'boolean'],
            'is_active' => ['required', 'boolean'],
            'sort_order' => ['required', 'integer', 'min:0'],
        ], [
            'code.unique' => 'Kode metode pembayaran sudah digunakan.',
            'code.alpha_dash' => 'Kode hanya boleh berisi huruf, angka, strip, dan garis bawah.',
        ]);

        $data['code'] = strtolower($data['code']);

        return $data;
    }
}
