<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\CustomerDigitalAccount;
use App\Models\Transaction;
use App\Services\ProfitCalculationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class CustomerController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(ProfitCalculationService $profitCalculator)
    {
        $customers = Customer::with([
            'digitalAccounts',
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

        $customers->each(function (Customer $customer) use ($profitCalculator) {
            $customer->setAttribute(
                'total_profit',
                (float) $customer->transactions->sum(
                    fn (Transaction $transaction) => $profitCalculator->transactionProfit($transaction)
                )
            );
        });

        $duplicateGroups = $customers
            ->groupBy('name_key')
            ->filter(fn ($group) => $group->count() > 1 && $group->first()->name_key)
            ->map(fn ($group) => [
                'name_key' => $group->first()->name_key,
                'name' => $group->first()->name,
                'customers' => $group->map(fn (Customer $customer) => [
                    'id' => $customer->id,
                    'name' => $customer->name,
                    'customer_type' => $customer->customer_type,
                    'phone' => $customer->phone,
                    'transactions_count' => $customer->transactions_count,
                    'total_spent' => (float) $customer->total_spent,
                    'total_profit' => (float) $customer->total_profit,
                    'digital_accounts' => $customer->digitalAccounts,
                ])->values(),
            ])->values();

        return Inertia::render('Customers/Index', [
            'customers' => $customers,
            'duplicateGroups' => $duplicateGroups,
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

    public function merge(Request $request, Customer $customer)
    {
        $validated = $request->validate([
            'source_customer_ids' => 'required|array|min:1',
            'source_customer_ids.*' => 'integer|distinct|exists:customers,id',
        ]);

        abort_if(in_array($customer->id, $validated['source_customer_ids'], true), 422, 'Customer utama tidak boleh ikut digabung.');

        $sources = Customer::query()
            ->with('digitalAccounts')
            ->whereIn('id', $validated['source_customer_ids'])
            ->get();

        abort_if($sources->count() !== count($validated['source_customer_ids']), 422, 'Data customer duplikat tidak lengkap.');

        DB::transaction(function () use ($customer, $sources) {
            foreach ($sources as $source) {
                $source->transactions()->update(['customer_id' => $customer->id]);

                foreach ($source->digitalAccounts as $account) {
                    $duplicate = $customer->digitalAccounts()
                        ->where('type', $account->type)
                        ->where('account_number', $account->account_number)
                        ->exists();

                    if ($duplicate) {
                        $account->delete();
                    } else {
                        $account->update(['customer_id' => $customer->id]);
                    }
                }

                if (! $customer->phone && $source->phone) {
                    $customer->phone = $source->phone;
                }

                if (! $customer->address && $source->address) {
                    $customer->address = $source->address;
                }

                $source->delete();
            }

            $customer->save();
        });

        return redirect()->back()->with('success', 'Customer duplikat berhasil digabung dan akun digital dipindahkan.');
    }

    public function storeDigitalAccount(Request $request, Customer $customer)
    {
        $validated = $request->validate([
            'type' => 'required|in:token,phone',
            'account_number' => 'required|string|max:50',
            'account_name' => 'required|string|max:255',
        ]);
        $validated['account_number'] = CustomerDigitalAccount::normalizeNumber($validated['account_number']);

        $this->assertDigitalAccountAvailable($validated, null, $customer);
        $customer->digitalAccounts()->create($validated);

        return redirect()->back()->with('success', 'Akun digital berhasil ditambahkan.');
    }

    public function updateDigitalAccount(Request $request, Customer $customer, CustomerDigitalAccount $account)
    {
        abort_unless($account->customer_id === $customer->id, 404);

        $validated = $request->validate([
            'type' => 'required|in:token,phone',
            'account_number' => 'required|string|max:50',
            'account_name' => 'required|string|max:255',
        ]);
        $validated['account_number'] = CustomerDigitalAccount::normalizeNumber($validated['account_number']);

        $this->assertDigitalAccountAvailable($validated, $account, $customer);
        $account->update($validated);

        return redirect()->back()->with('success', 'Akun digital berhasil diperbarui.');
    }

    public function destroyDigitalAccount(Customer $customer, CustomerDigitalAccount $account)
    {
        abort_unless($account->customer_id === $customer->id, 404);
        $account->delete();

        return redirect()->back()->with('success', 'Akun digital berhasil dihapus.');
    }

    private function assertDigitalAccountAvailable(array $data, ?CustomerDigitalAccount $current, Customer $customer): void
    {
        $query = CustomerDigitalAccount::query()
            ->where('type', $data['type'])
            ->where('account_number', $data['account_number']);

        if ($current) {
            $query->where('id', '<>', $current->id);
        }

        $existing = $query->first();

        abort_if($existing && $existing->customer_id !== $customer->id, 422, 'Nomor digital sudah terhubung ke customer lain.');
        abort_if($existing, 422, 'Akun digital tersebut sudah tercatat pada customer ini.');
    }
}
