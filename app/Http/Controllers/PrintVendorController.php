<?php

namespace App\Http\Controllers;

use App\Models\PrintVendor;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PrintVendorController extends Controller
{
    public function index()
    {
        return Inertia::render('PrintVendors/Index', [
            'vendors' => PrintVendor::withCount('transactionItems')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        PrintVendor::create($this->validatedData($request));

        return redirect()->back()->with('success', 'Mitra percetakan berhasil ditambahkan!');
    }

    public function update(Request $request, PrintVendor $printVendor)
    {
        $printVendor->update($this->validatedData($request));

        return redirect()->back()->with('success', 'Mitra percetakan berhasil diperbarui!');
    }

    public function destroy(PrintVendor $printVendor)
    {
        if ($printVendor->transactionItems()->exists()) {
            return redirect()->back()->with('error', 'Mitra tidak bisa dihapus karena sudah digunakan pada transaksi.');
        }

        $printVendor->delete();

        return redirect()->back()->with('success', 'Mitra percetakan berhasil dihapus!');
    }

    private function validatedData(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string'],
            'is_active' => ['required', 'boolean'],
        ]);
    }
}
