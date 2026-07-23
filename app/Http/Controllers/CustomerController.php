<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CustomerController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $customers = Customer::with([
                'transactions' => function ($query) {
                    $query
                        ->with(['items.product'])
                        ->latest();
                },
            ])
            ->withCount('transactions')
            ->withSum('transactions as total_spent', 'total_price')
            ->orderBy('name')
            ->get();

        return Inertia::render('Customers/Index', [
            'customers' => $customers
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'customer_type' => 'required|in:general,token,operator',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string',
        ]);

        Customer::create($request->only('name', 'customer_type', 'phone', 'address'));

        return redirect()->back()->with('success', 'Customer berhasil ditambahkan!');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Customer $customer)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'customer_type' => 'required|in:general,token,operator',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string',
        ]);

        $customer->update($request->only('name', 'customer_type', 'phone', 'address'));

        return redirect()->back()->with('success', 'Data Customer berhasil diperbarui!');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Customer $customer)
    {
        if ($customer->transactions()->exists()) {
            return redirect()
                ->back()
                ->withErrors(['error' => 'Customer tidak bisa dihapus karena sudah memiliki riwayat transaksi.']);
        }

        $customer->delete();

        return redirect()->back()->with('success', 'Customer berhasil dihapus!');
    }
}
