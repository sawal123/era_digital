<?php

namespace App\Services;

use App\Models\CapitalAccount;
use App\Models\CapitalLedgerEntry;
use App\Models\Category;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\StoreProfile;
use App\Models\TransactionItem;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class CapitalLedgerService
{
    public const DIGITAL_ACCOUNT_SLUG = 'saldo-digital';

    public function ensureAccounts(): Collection
    {
        $accounts = collect();

        $digitalAccount = CapitalAccount::firstOrCreate(
            ['slug' => self::DIGITAL_ACCOUNT_SLUG],
            [
                'name' => 'Saldo Digital',
                'type' => 'digital',
                'current_balance' => 0,
                'contributed_capital' => 0,
            ]
        );
        $this->seedDigitalAccount($digitalAccount);
        $accounts->push($digitalAccount->fresh());

        Category::query()
            ->where('type', 'fisik')
            ->orderBy('name')
            ->get()
            ->each(function (Category $category) use ($accounts) {
                $account = CapitalAccount::firstOrCreate(
                    ['slug' => 'category-'.$category->id],
                    [
                        'name' => 'Modal '.$category->name,
                        'type' => 'inventory',
                        'current_balance' => 0,
                    ]
                );

                $this->seedInventoryAccount($account, $category->id);
                $accounts->push($account->fresh());
            });

        return $accounts;
    }

    public function recordManual(
        CapitalAccount $account,
        string $direction,
        float $amount,
        string $date,
        ?string $description = null
    ): CapitalLedgerEntry {
        if ($direction === 'debit' && (float) $account->current_balance + 0.00001 < $amount) {
            throw ValidationException::withMessages([
                'amount' => 'Saldo akun modal tidak mencukupi untuk debit ini.',
            ]);
        }

        $entry = $this->createEntry($account, [
            'entry_date' => $date,
            'entry_type' => 'manual',
            'direction' => $direction,
            'amount' => $amount,
            'description' => $description ?: 'Pencatatan modal manual',
            'reference_type' => 'manual',
        ]);

        if ($account->slug === self::DIGITAL_ACCOUNT_SLUG) {
            $account->update([
                'contributed_capital' => round(
                    (float) $account->contributed_capital + $entry->signed_amount,
                    2,
                ),
            ]);

            $profile = StoreProfile::query()->first();

            if ($profile) {
                $profile->update([
                    'saldo_digital' => round((float) $profile->saldo_digital + $entry->signed_amount, 2),
                ]);
            }
        }

        return $entry;
    }

    public function digitalSummary(?CapitalAccount $account = null): array
    {
        $account ??= $this->ensureAccounts()->firstWhere('slug', self::DIGITAL_ACCOUNT_SLUG);

        $digitalItems = TransactionItem::query()
            ->with('transaction')
            ->whereIn('type', ['ppob', 'digital']);
        $profitCalculator = app(ProfitCalculationService::class);
        $digitalItemRows = $digitalItems->get();

        return [
            'contributed_capital' => (float) ($account?->contributed_capital ?? 0),
            'current_balance' => (float) ($account?->current_balance ?? 0),
            'total_profit' => (float) $digitalItemRows->sum(
                fn (TransactionItem $item) => $profitCalculator->itemProfit($item)
            ),
            'total_usage' => (float) $digitalItemRows->sum(
                fn (TransactionItem $item) => $profitCalculator->itemBase($item)
            ),
            'transaction_count' => (int) TransactionItem::query()
                ->whereIn('type', ['ppob', 'digital'])
                ->distinct('transaction_id')
                ->count('transaction_id'),
        ];
    }

    public function syncPurchase(Purchase $purchase): ?CapitalLedgerEntry
    {
        $purchase->loadMissing('product.category');
        $account = $this->accountForProduct($purchase->product);

        if (! $account) {
            return null;
        }

        return $this->upsertReference(
            $account,
            'purchase',
            $purchase->id,
            [
                'entry_date' => $purchase->purchase_date,
                'entry_type' => 'restock',
                'direction' => 'credit',
                'amount' => (float) $purchase->total_price,
                'description' => 'Restock: '.$purchase->product->name,
            ]
        );
    }

    public function syncTransactionItemUsage(TransactionItem $item): ?CapitalLedgerEntry
    {
        $item->loadMissing('product.category', 'transaction');
        $account = $this->accountForProduct($item->product, $item->type);

        if (! $account) {
            return null;
        }

        return $this->upsertReference(
            $account,
            'transaction_item',
            $item->id,
            [
                'entry_date' => $item->transaction?->created_at?->toDateString() ?: now()->toDateString(),
                'entry_type' => 'usage',
                'direction' => 'debit',
                'amount' => (float) app(ProfitCalculationService::class)->itemBase($item),
                'description' => 'Pemakaian modal: '.$item->item_name,
            ]
        );
    }

    public function syncDigitalBalance(float $oldBalance, float $newBalance, string $date): ?CapitalLedgerEntry
    {
        $difference = round($newBalance - $oldBalance, 2);

        if (abs($difference) < 0.01) {
            return null;
        }

        $account = $this->ensureAccounts()->firstWhere('slug', self::DIGITAL_ACCOUNT_SLUG);

        return $this->createEntry($account, [
            'entry_date' => $date,
            'entry_type' => 'adjustment',
            'direction' => $difference > 0 ? 'credit' : 'debit',
            'amount' => abs($difference),
            'description' => 'Penyesuaian saldo digital toko',
            'reference_type' => 'system',
        ]);
    }

    public function deleteReference(string $referenceType, int $referenceId): void
    {
        $entry = CapitalLedgerEntry::query()
            ->where('reference_type', $referenceType)
            ->where('reference_id', $referenceId)
            ->first();

        if (! $entry) {
            return;
        }

        $reverseDelta = -$entry->signed_amount;
        $this->adjustBalance($entry->account, $reverseDelta);

        if ($entry->account->slug === self::DIGITAL_ACCOUNT_SLUG) {
            $profile = StoreProfile::query()->first();

            if ($profile) {
                $profile->update([
                    'saldo_digital' => round((float) $profile->saldo_digital + $reverseDelta, 2),
                ]);
            }
        }

        $entry->delete();
    }

    public function deleteManual(CapitalLedgerEntry $entry): void
    {
        abort_unless($entry->reference_type === 'manual', 422, 'Entry otomatis tidak dapat dihapus dari ledger.');

        $this->adjustBalance($entry->account, -$entry->signed_amount);

        if ($entry->account->slug === self::DIGITAL_ACCOUNT_SLUG) {
            $entry->account->update([
                'contributed_capital' => round(
                    (float) $entry->account->contributed_capital - $entry->signed_amount,
                    2,
                ),
            ]);

            $profile = StoreProfile::query()->first();

            if ($profile) {
                $profile->update([
                    'saldo_digital' => round((float) $profile->saldo_digital - $entry->signed_amount, 2),
                ]);
            }
        }

        $entry->delete();
    }

    private function accountForProduct(?Product $product, ?string $itemType = null): ?CapitalAccount
    {
        if ($itemType === 'ppob' || $product?->category?->type === 'ppob') {
            return $this->ensureAccounts()->firstWhere('slug', self::DIGITAL_ACCOUNT_SLUG);
        }

        if (! $product || $product->category?->type !== 'fisik') {
            return null;
        }

        return $this->ensureAccounts()->firstWhere('slug', 'category-'.$product->category_id);
    }

    private function seedDigitalAccount(CapitalAccount $account): void
    {
        if ($account->entries()->exists()) {
            return;
        }

        $currentBalance = (float) (StoreProfile::query()->value('saldo_digital') ?? 0);
        $pastUsage = (float) TransactionItem::query()
            ->whereIn('type', ['ppob', 'digital'])
            ->sum('subtotal_base');

        $this->createEntry($account, [
            'entry_date' => now()->toDateString(),
            'entry_type' => 'opening',
            'direction' => 'credit',
            'amount' => $currentBalance + $pastUsage,
            'description' => 'Saldo awal hasil sinkronisasi ledger',
            'reference_type' => 'system',
        ]);

        $account->update([
            'contributed_capital' => round($currentBalance, 2),
        ]);

        TransactionItem::query()
            ->with('transaction')
            ->whereIn('type', ['ppob', 'digital'])
            ->orderBy('id')
            ->get()
            ->each(fn (TransactionItem $item) => $this->upsertReference($account, 'transaction_item', $item->id, [
                'entry_date' => $item->transaction?->created_at?->toDateString() ?: now()->toDateString(),
                'entry_type' => 'usage',
                'direction' => 'debit',
                'amount' => (float) $item->subtotal_base,
                'description' => 'Pemakaian modal: '.$item->item_name,
            ]));
    }

    private function seedInventoryAccount(CapitalAccount $account, int $categoryId): void
    {
        if ($account->entries()->exists()) {
            return;
        }

        $productIds = Product::query()->where('category_id', $categoryId)->pluck('id');
        $purchases = Purchase::query()
            ->with('product')
            ->whereIn('product_id', $productIds)
            ->orderBy('id')
            ->get();
        $usages = TransactionItem::query()
            ->with('transaction')
            ->whereIn('product_id', $productIds)
            ->orderBy('id')
            ->get();

        foreach ($purchases as $purchase) {
            $this->upsertReference($account, 'purchase', $purchase->id, [
                'entry_date' => $purchase->purchase_date,
                'entry_type' => 'restock',
                'direction' => 'credit',
                'amount' => (float) $purchase->total_price,
                'description' => 'Restock: '.$purchase->product->name,
            ]);
        }

        foreach ($usages as $item) {
            $this->upsertReference($account, 'transaction_item', $item->id, [
                'entry_date' => $item->transaction?->created_at?->toDateString() ?: now()->toDateString(),
                'entry_type' => 'usage',
                'direction' => 'debit',
                'amount' => (float) $item->subtotal_base,
                'description' => 'Pemakaian modal: '.$item->item_name,
            ]);
        }

        $actualInventoryValue = (float) Product::query()
            ->where('category_id', $categoryId)
            ->selectRaw('COALESCE(SUM(stock * base_price), 0) as value')
            ->value('value');
        $difference = round($actualInventoryValue - (float) $account->fresh()->current_balance, 2);

        if (abs($difference) >= 0.01) {
            $this->createEntry($account, [
                'entry_date' => now()->toDateString(),
                'entry_type' => 'adjustment',
                'direction' => $difference > 0 ? 'credit' : 'debit',
                'amount' => abs($difference),
                'description' => 'Penyesuaian nilai stok saat sinkronisasi ledger',
                'reference_type' => 'system',
            ]);
        }
    }

    private function upsertReference(CapitalAccount $account, string $referenceType, int $referenceId, array $data): CapitalLedgerEntry
    {
        $entry = CapitalLedgerEntry::query()
            ->where('reference_type', $referenceType)
            ->where('reference_id', $referenceId)
            ->first();

        if (! $entry) {
            return $this->createEntry($account, array_merge($data, [
                'capital_account_id' => $account->id,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
            ]));
        }

        $oldSignedAmount = $entry->signed_amount;
        $newSignedAmount = ($data['direction'] ?? $entry->direction) === 'debit'
            ? -(float) ($data['amount'] ?? $entry->amount)
            : (float) ($data['amount'] ?? $entry->amount);

        if ($entry->capital_account_id !== $account->id) {
            $this->adjustBalance($entry->account, -$oldSignedAmount);
            $this->adjustBalance($account, $newSignedAmount);
        } else {
            $this->adjustBalance($account, $newSignedAmount - $oldSignedAmount);
        }

        $entry->fill(array_merge($data, ['capital_account_id' => $account->id]))->save();

        return $entry->fresh();
    }

    private function createEntry(CapitalAccount $account, array $data): CapitalLedgerEntry
    {
        $entry = CapitalLedgerEntry::create(array_merge($data, [
            'capital_account_id' => $data['capital_account_id'] ?? $account->id,
        ]));
        $this->adjustBalance($account, $entry->signed_amount);

        return $entry;
    }

    private function adjustBalance(CapitalAccount $account, float $delta): void
    {
        $account->update([
            'current_balance' => round((float) $account->current_balance + $delta, 2),
        ]);
    }
}
