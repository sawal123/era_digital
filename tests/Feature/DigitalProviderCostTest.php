<?php

use App\Models\CapitalAccount;
use App\Models\CapitalLedgerEntry;
use App\Models\Category;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\StoreProfile;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Models\User;
use App\Services\CapitalLedgerService;

function digitalProviderProduct(): Product
{
    $category = Category::create([
        'name' => 'PPOB '.fake()->unique()->numerify('###'),
        'slug' => 'ppob-'.fake()->unique()->numerify('###'),
        'type' => 'ppob',
    ]);

    return Product::create([
        'category_id' => $category->id,
        'sku' => 'PULSA-'.fake()->unique()->numerify('###'),
        'name' => 'Pulsa Provider',
        'unit' => 'transaksi',
        'base_price' => 0,
        'selling_price' => 0,
        'admin_fee' => 0,
        'stock' => 0,
        'is_active' => true,
    ]);
}

function digitalProviderPayload(Product $product, array $itemOverrides = []): array
{
    return [
        'cart' => [array_merge([
            'id' => $product->id,
            'name' => 'Pulsa Provider - Rp 100.000',
            'price' => 100000,
            'quantity' => 1,
            'type' => 'digital',
            'nominal' => 100000,
            'provider_cost' => 98000,
            'admin_fee' => 0,
            'digital_type' => 'phone',
            'account_number' => '081234567890',
            'account_name' => 'Customer Test',
        ], $itemOverrides)],
        'total' => 100000,
        'payment_method_id' => PaymentMethod::firstOrCreate(
            ['code' => 'cash'],
            ['name' => 'Cash / Tunai', 'is_cash' => true, 'is_active' => true, 'sort_order' => 1]
        )->id,
        'uang_diterima' => 100000,
        'customer_id' => null,
        'invoice_customer_name' => null,
        'invoice_customer_phone' => null,
        'keterangan' => null,
    ];
}

it('menggunakan biaya aktual provider untuk HPP profit dan saldo digital', function () {
    $this->actingAs(User::factory()->create());
    $product = digitalProviderProduct();
    StoreProfile::create([
        'store_name' => 'Era Digital',
        'address' => 'Alamat test',
        'phone' => '0800000000',
        'saldo_digital' => 2600000,
    ]);

    $this->get('/pos')->assertOk();
    $this->post('/pos', digitalProviderPayload($product))->assertRedirect();

    $item = TransactionItem::firstOrFail();
    $account = CapitalAccount::where('slug', CapitalLedgerService::DIGITAL_ACCOUNT_SLUG)->firstOrFail();

    expect((float) $item->provider_cost)->toBe(98000.0)
        ->and((float) $item->base_price)->toBe(98000.0)
        ->and((float) $item->subtotal_base)->toBe(98000.0)
        ->and((float) $item->profit)->toBe(2000.0)
        ->and((float) StoreProfile::first()->saldo_digital)->toBe(2502000.0)
        ->and((float) $account->fresh()->current_balance)->toBe(2502000.0)
        ->and((float) CapitalLedgerEntry::where('reference_type', 'transaction_item')->first()->amount)->toBe(98000.0)
        ->and(app(CapitalLedgerService::class)->digitalSummary($account->fresh())['total_profit'])->toBe(2000.0);
});

it('menggunakan biaya provider terakhir jika biaya transaksi tidak dikirim', function () {
    $this->actingAs(User::factory()->create());
    $product = digitalProviderProduct();
    StoreProfile::create([
        'store_name' => 'Era Digital',
        'address' => 'Alamat test',
        'phone' => '0800000000',
        'saldo_digital' => 2600000,
    ]);

    $this->get('/pos')->assertOk();
    $this->post('/pos', digitalProviderPayload($product))->assertRedirect();
    $this->post('/pos', digitalProviderPayload($product, [
        'provider_cost' => null,
        'account_number' => '081234567891',
    ]))->assertRedirect();

    expect(TransactionItem::query()->count())->toBe(2)
        ->and((float) TransactionItem::latest('id')->first()->provider_cost)->toBe(98000.0);
});

it('mengembalikan saldo provider saat transaksi digital dihapus', function () {
    $this->actingAs(User::factory()->create());
    $product = digitalProviderProduct();
    StoreProfile::create([
        'store_name' => 'Era Digital',
        'address' => 'Alamat test',
        'phone' => '0800000000',
        'saldo_digital' => 2600000,
    ]);

    $this->get('/pos')->assertOk();
    $this->post('/pos', digitalProviderPayload($product))->assertRedirect();
    $transaction = Transaction::firstOrFail();

    $this->delete('/reports/'.$transaction->id)->assertRedirect();

    $account = CapitalAccount::where('slug', CapitalLedgerService::DIGITAL_ACCOUNT_SLUG)->firstOrFail();

    expect((float) StoreProfile::first()->saldo_digital)->toBe(2600000.0)
        ->and((float) $account->fresh()->current_balance)->toBe(2600000.0)
        ->and(CapitalLedgerEntry::where('reference_type', 'transaction_item')->count())->toBe(0);
});

it('menolak transaksi PPOB jika saldo provider tidak mencukupi', function () {
    $this->actingAs(User::factory()->create());
    $product = digitalProviderProduct();
    StoreProfile::create([
        'store_name' => 'Era Digital',
        'address' => 'Alamat test',
        'phone' => '0800000000',
        'saldo_digital' => 50000,
    ]);

    $this->get('/pos')->assertOk();
    $this->post('/pos', digitalProviderPayload($product))->assertRedirect();

    expect(Transaction::query()->count())->toBe(0)
        ->and((float) StoreProfile::first()->saldo_digital)->toBe(50000.0);
});

it('menolak penjualan digital saat saldo provider sudah habis', function () {
    $this->actingAs(User::factory()->create());
    $product = digitalProviderProduct();
    StoreProfile::create([
        'store_name' => 'Era Digital',
        'address' => 'Alamat test',
        'phone' => '0800000000',
        'saldo_digital' => 0,
    ]);

    $this->get('/pos')->assertOk();
    $this->post('/pos', digitalProviderPayload($product))->assertRedirect();

    expect(Transaction::query()->count())->toBe(0)
        ->and((float) StoreProfile::first()->saldo_digital)->toBe(0.0);
});

it('mengizinkan saldo terakhir dipakai lalu menolak penjualan berikutnya', function () {
    $this->actingAs(User::factory()->create());
    $product = digitalProviderProduct();
    StoreProfile::create([
        'store_name' => 'Era Digital',
        'address' => 'Alamat test',
        'phone' => '0800000000',
        'saldo_digital' => 98000,
    ]);

    $this->get('/pos')->assertOk();
    $payload = digitalProviderPayload($product);
    $this->post('/pos', $payload)->assertRedirect();
    $this->post('/pos', $payload)->assertRedirect();

    expect(Transaction::query()->count())->toBe(1)
        ->and((float) StoreProfile::first()->saldo_digital)->toBe(0.0);
});

it('menolak biaya provider nol agar tidak menjual tanpa modal', function () {
    $this->actingAs(User::factory()->create());
    $product = digitalProviderProduct();
    StoreProfile::create([
        'store_name' => 'Era Digital',
        'address' => 'Alamat test',
        'phone' => '0800000000',
        'saldo_digital' => 2600000,
    ]);

    $this->get('/pos')->assertOk();
    $this->from('/pos')->post('/pos', digitalProviderPayload($product, [
        'provider_cost' => 0,
    ]))->assertSessionHasErrors('cart.0.provider_cost');

    expect(Transaction::query()->count())->toBe(0);
});
