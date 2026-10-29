<?php

use App\Models\CapitalAccount;
use App\Models\CapitalLedgerEntry;
use App\Models\Category;
use App\Models\Product;
use App\Models\StoreProfile;
use App\Models\Transaction;
use App\Models\User;
use App\Services\CapitalLedgerService;

function capitalActingUser($testCase): User
{
    $user = User::factory()->create();
    $testCase->actingAs($user);

    return $user;
}

function capitalCategory(string $name = 'ATK'): Category
{
    return Category::create([
        'name' => $name,
        'slug' => strtolower($name).'-'.fake()->unique()->numerify('###'),
        'type' => 'fisik',
    ]);
}

it('menampilkan akun modal dan mencatat modal manual', function () {
    capitalActingUser($this);
    capitalCategory();
    StoreProfile::create([
        'store_name' => 'Era Digital',
        'address' => 'Alamat test',
        'phone' => '0800000000',
        'saldo_digital' => 0,
    ]);

    $this->get('/capital')->assertOk();

    $account = CapitalAccount::where('slug', 'saldo-digital')->firstOrFail();

    $this->post('/capital/entries', [
        'capital_account_id' => $account->id,
        'direction' => 'credit',
        'amount' => 2000000,
        'entry_date' => '2026-10-01',
        'description' => 'Modal awal saldo pulsa',
    ])->assertRedirect();

    expect((float) $account->fresh()->current_balance)->toBe(2000000.0)
        ->and((float) $account->fresh()->contributed_capital)->toBe(2000000.0)
        ->and((float) StoreProfile::first()->saldo_digital)->toBe(2000000.0)
        ->and(CapitalLedgerEntry::where('reference_type', 'manual')->count())->toBe(1);
});

it('restock otomatis menambah ledger modal kategori produk', function () {
    capitalActingUser($this);
    $category = capitalCategory();
    $product = Product::create([
        'category_id' => $category->id,
        'name' => 'Spidol Baru',
        'unit' => 'pcs',
        'base_price' => 0,
        'selling_price' => 10000,
        'stock' => 0,
        'min_stock' => 1,
        'is_active' => true,
    ]);

    $this->post('/purchases', [
        'product_id' => $product->id,
        'quantity' => 10,
        'cost_price' => 5000,
        'purchase_date' => '2026-10-10',
        'note' => 'Produk baru',
    ])->assertRedirect();

    $account = CapitalAccount::where('slug', 'category-'.$category->id)->firstOrFail();

    expect((float) $account->current_balance)->toBe(50000.0)
        ->and(CapitalLedgerEntry::where('reference_type', 'purchase')->count())->toBe(1);
});

it('penjualan fisik otomatis mengurangi modal persediaan', function () {
    $category = capitalCategory();
    $product = Product::create([
        'category_id' => $category->id,
        'name' => 'Pulpen Ledger',
        'unit' => 'pcs',
        'base_price' => 2000,
        'selling_price' => 3500,
        'stock' => 10,
        'min_stock' => 1,
        'is_active' => true,
    ]);
    $service = app(CapitalLedgerService::class);
    $service->ensureAccounts();

    $transaction = Transaction::create([
        'invoice_number' => 'LEDGER-001',
        'cashier_id' => User::factory()->create()->id,
        'total_base_price' => 4000,
        'total_price' => 7000,
        'total_profit' => 3000,
        'payment_method' => 'cash',
        'payment_status' => 'paid',
    ]);
    $item = $transaction->items()->create([
        'product_id' => $product->id,
        'item_name' => 'Pulpen Ledger',
        'type' => 'fisik',
        'unit' => 'pcs',
        'quantity' => 2,
        'base_price' => 2000,
        'selling_price' => 3500,
        'subtotal_base' => 4000,
        'subtotal_price' => 7000,
        'profit' => 3000,
    ]);

    $service->syncTransactionItemUsage($item);

    $account = CapitalAccount::where('slug', 'category-'.$category->id)->firstOrFail();

    expect((float) $account->current_balance)->toBe(16000.0)
        ->and(CapitalLedgerEntry::where('reference_type', 'transaction_item')->count())->toBe(1);
});

it('penambahan saldo digital tercatat sebagai penyesuaian ledger', function () {
    StoreProfile::create([
        'store_name' => 'Era Digital',
        'address' => 'Alamat test',
        'phone' => '0800000000',
        'saldo_digital' => 2000000,
    ]);

    $service = app(CapitalLedgerService::class);
    $service->ensureAccounts();
    $service->syncDigitalBalance(2000000, 2500000, '2026-10-20');

    $account = CapitalAccount::where('slug', 'saldo-digital')->firstOrFail();

    expect((float) $account->current_balance)->toBe(2500000.0)
        ->and((float) $account->contributed_capital)->toBe(2000000.0)
        ->and(CapitalLedgerEntry::where('entry_type', 'adjustment')->count())->toBe(1);
});

it('menolak debit manual yang melebihi saldo akun modal', function () {
    capitalActingUser($this);
    StoreProfile::create([
        'store_name' => 'Era Digital',
        'address' => 'Alamat test',
        'phone' => '0800000000',
        'saldo_digital' => 100000,
    ]);

    $this->get('/capital')->assertOk();
    $account = CapitalAccount::where('slug', CapitalLedgerService::DIGITAL_ACCOUNT_SLUG)->firstOrFail();

    $this->from('/capital')->post('/capital/entries', [
        'capital_account_id' => $account->id,
        'direction' => 'debit',
        'amount' => 150000,
        'entry_date' => '2026-10-20',
        'description' => 'Debit terlalu besar',
    ])->assertSessionHasErrors('amount');

    expect((float) $account->fresh()->current_balance)->toBe(100000.0)
        ->and(CapitalLedgerEntry::where('reference_type', 'manual')->count())->toBe(0);
});

it('mengizinkan penarikan seluruh saldo modal tanpa membuat saldo negatif', function () {
    capitalActingUser($this);
    StoreProfile::create([
        'store_name' => 'Era Digital',
        'address' => 'Alamat test',
        'phone' => '0800000000',
        'saldo_digital' => 100000,
    ]);

    $this->get('/capital')->assertOk();
    $account = CapitalAccount::where('slug', CapitalLedgerService::DIGITAL_ACCOUNT_SLUG)->firstOrFail();

    $this->post('/capital/entries', [
        'capital_account_id' => $account->id,
        'direction' => 'debit',
        'amount' => 100000,
        'entry_date' => '2026-10-20',
        'description' => 'Tarik semua modal',
    ])->assertRedirect();

    expect((float) $account->fresh()->current_balance)->toBe(0.0)
        ->and((float) $account->fresh()->contributed_capital)->toBe(0.0)
        ->and((float) StoreProfile::first()->saldo_digital)->toBe(0.0);
});

it('memisahkan modal disetor dari perputaran dan menghitung keuntungan digital', function () {
    StoreProfile::create([
        'store_name' => 'Era Digital',
        'address' => 'Alamat test',
        'phone' => '0800000000',
        'saldo_digital' => 2600000,
    ]);

    $service = app(CapitalLedgerService::class);
    $service->ensureAccounts();

    $transaction = Transaction::create([
        'invoice_number' => 'DIGITAL-001',
        'cashier_id' => User::factory()->create()->id,
        'total_base_price' => 100000,
        'total_price' => 105000,
        'total_profit' => 5000,
        'payment_method' => 'cash',
        'payment_status' => 'paid',
    ]);
    $item = $transaction->items()->create([
        'item_name' => 'Pulsa Digital',
        'type' => 'ppob',
        'unit' => 'transaksi',
        'quantity' => 1,
        'base_price' => 100000,
        'selling_price' => 105000,
        'subtotal_base' => 100000,
        'subtotal_price' => 105000,
        'profit' => 5000,
    ]);

    $service->syncTransactionItemUsage($item);
    $account = CapitalAccount::where('slug', CapitalLedgerService::DIGITAL_ACCOUNT_SLUG)->firstOrFail();
    $summary = $service->digitalSummary($account);

    expect((float) $account->current_balance)->toBe(2500000.0)
        ->and((float) $account->contributed_capital)->toBe(2600000.0)
        ->and($summary['total_profit'])->toBe(5000.0)
        ->and($summary['total_usage'])->toBe(100000.0)
        ->and($summary['transaction_count'])->toBe(1);
});
