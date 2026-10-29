<?php

namespace App\Http\Controllers;

use App\Models\CapitalAccount;
use App\Models\CapitalLedgerEntry;
use App\Models\StoreProfile;
use App\Services\CapitalLedgerService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CapitalLedgerController extends Controller
{
    public function index(CapitalLedgerService $ledger)
    {
        StoreProfile::query()->firstOrCreate([], [
            'store_name' => 'Era Digital',
            'address' => 'Jl. Raya Utama No. 45, Kebayoran Baru, Jakarta Selatan',
            'phone' => '0812-3456-7890',
            'signature_path' => null,
            'saldo_digital' => 350000.00,
        ]);
        $ledger->ensureAccounts();
        $digitalAccount = CapitalAccount::query()
            ->where('slug', CapitalLedgerService::DIGITAL_ACCOUNT_SLUG)
            ->first();

        return Inertia::render('Capital/Index', [
            'accounts' => CapitalAccount::query()
                ->where('is_active', true)
                ->orderBy('type')
                ->orderBy('name')
                ->get(),
            'entries' => CapitalLedgerEntry::with('account:id,name,slug')
                ->latest('entry_date')
                ->latest('id')
                ->limit(300)
                ->get(),
            'digitalSummary' => $ledger->digitalSummary($digitalAccount),
        ]);
    }

    public function store(Request $request, CapitalLedgerService $ledger)
    {
        $validated = $request->validate([
            'capital_account_id' => 'required|exists:capital_accounts,id',
            'direction' => 'required|in:credit,debit',
            'amount' => 'required|numeric|min:0.01',
            'entry_date' => 'required|date',
            'description' => 'nullable|string|max:1000',
        ]);

        $account = CapitalAccount::query()
            ->where('is_active', true)
            ->findOrFail($validated['capital_account_id']);

        $ledger->recordManual(
            $account,
            $validated['direction'],
            (float) $validated['amount'],
            $validated['entry_date'],
            $validated['description'] ?? null,
        );

        return redirect()->back()->with('success', 'Riwayat modal berhasil dicatat.');
    }

    public function destroy(CapitalLedgerEntry $entry, CapitalLedgerService $ledger)
    {
        $ledger->deleteManual($entry->load('account'));

        return redirect()->back()->with('success', 'Riwayat modal manual berhasil dihapus.');
    }
}
