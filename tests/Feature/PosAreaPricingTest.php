<?php

use App\Models\Category;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Models\User;

/**
 * Helpers khusus test POS area (percetakan berbasis meter persegi).
 * Nama dibuat unik agar tidak bertabrakan dengan helper test lain.
 */

function posAreaCategory(): Category
{
    return Category::firstOrCreate(
        ['slug' => 'jasa-cetak'],
        ['name' => 'Jasa Cetak', 'type' => 'jasa']
    );
}

function makeAreaProduct(string $name = 'Spanduk Biasa', float $selling = 25000, float $base = 15000): Product
{
    return Product::create([
        'category_id' => posAreaCategory()->id,
        'sku' => 'TST-SPD-' . fake()->unique()->numerify('###'),
        'name' => $name,
        'unit' => 'meter', // area-based per m²
        'base_price' => $base,
        'selling_price' => $selling,
        'stock' => null,
        'is_active' => true,
    ]);
}

function makeNonAreaProduct(string $name = 'Fotokopi', float $selling = 200, float $base = 100, string $unit = 'lembar'): Product
{
    return Product::create([
        'category_id' => posAreaCategory()->id,
        'sku' => 'TST-NAR-' . fake()->unique()->numerify('###'),
        'name' => $name,
        'unit' => $unit,
        'base_price' => $base,
        'selling_price' => $selling,
        'stock' => null,
        'is_active' => true,
    ]);
}

function posCashMethod(): PaymentMethod
{
    return PaymentMethod::firstOrCreate(
        ['code' => 'cash'],
        ['name' => 'Cash / Tunai', 'is_cash' => true, 'is_active' => true, 'sort_order' => 1]
    );
}

/**
 * Payload POST /pos untuk produk area (spanduk). Item & total bisa dioverride.
 */
function posAreaCartPayload(Product $product, array $payloadOverrides = [], array $itemOverrides = []): array
{
    $item = array_merge([
        'id' => $product->id,
        'name' => $product->name,
        'price' => 50000,            // harga efektif per pcs (dihitung frontend)
        'quantity' => 1,             // jumlah pcs
        'type' => 'cetak',
        'detail' => 'Ukuran: 2 x 1 m',
        'is_area_based' => true,
        'length' => 2,
        'width' => 1,
        'area_per_piece' => 2,
        'total_area' => 2,
        'selling_rate' => 25000,     // rate jual per m²
        'base_rate' => 15000,        // rate modal per m²
        'print_vendor_id' => null,
    ], $itemOverrides);

    return array_merge([
        'cart' => [$item],
        'total' => 50000,
        'payment_method_id' => posCashMethod()->id,
        'uang_diterima' => 50000,
        'customer_id' => null,
        'invoice_customer_name' => null,
        'invoice_customer_phone' => null,
        'keterangan' => null,
    ], $payloadOverrides);
}

// ---------------------------------------------------------------------------
// CASE 1: 2 x 1 m, Qty 1, rate 25.000 / 15.000
// ---------------------------------------------------------------------------
it('CASE 1: 2x1 m qty 1 pcs -> quantity pcs, harga & HPP efektif per pcs', function () {
    $this->actingAs(User::factory()->create());
    $product = makeAreaProduct('Spanduk Biasa', 25000, 15000);

    $this->from('/pos')->post('/pos', posAreaCartPayload($product, [], [
        'price' => 50000,
        'quantity' => 1,
        'area_per_piece' => 2,
        'total_area' => 2,
    ]))->assertRedirect();

    $item = TransactionItem::first();
    expect($item)->not->toBeNull();

    expect((float) $item->quantity)->toBe(1.0)
        ->and((float) $item->selling_price)->toBe(50000.0)
        ->and((float) $item->base_price)->toBe(30000.0)
        ->and((float) $item->subtotal_price)->toBe(50000.0)
        ->and((float) $item->subtotal_base)->toBe(30000.0)
        ->and((float) $item->profit)->toBe(20000.0);

    $metadata = $item->metadata;
    expect((float) $metadata['length'])->toBe(2.0)
        ->and((float) $metadata['width'])->toBe(1.0)
        ->and((float) $metadata['area_per_piece'])->toBe(2.0)
        ->and((float) $metadata['total_area'])->toBe(2.0)
        ->and((float) $metadata['selling_rate'])->toBe(25000.0)
        ->and((float) $metadata['base_rate'])->toBe(15000.0)
        ->and($metadata['detail'])->toBe('Ukuran: 2 x 1 m');
});

// ---------------------------------------------------------------------------
// CASE 2: 2 x 1 m, Qty 3 -> area_per_piece 2, total_area 6
// ---------------------------------------------------------------------------
it('CASE 2: 2x1 m qty 3 pcs -> quantity 3, total_area 6', function () {
    $this->actingAs(User::factory()->create());
    $product = makeAreaProduct('Spanduk Biasa', 25000, 15000);

    $this->from('/pos')->post('/pos', posAreaCartPayload($product, [
        'total' => 150000,
        'uang_diterima' => 150000,
    ], [
        'price' => 50000,
        'quantity' => 3,
        'area_per_piece' => 2,
        'total_area' => 6,
    ]))->assertRedirect();

    $item = TransactionItem::first();
    expect((float) $item->quantity)->toBe(3.0)
        ->and((float) $item->selling_price)->toBe(50000.0)
        ->and((float) $item->base_price)->toBe(30000.0)
        ->and((float) $item->subtotal_price)->toBe(150000.0)
        ->and((float) $item->subtotal_base)->toBe(90000.0)
        ->and((float) $item->profit)->toBe(60000.0);

    $metadata = $item->metadata;
    expect((float) $metadata['area_per_piece'])->toBe(2.0)
        ->and((float) $metadata['total_area'])->toBe(6.0);
});

// ---------------------------------------------------------------------------
// CASE 3: 1.6 x 1.2 m, Qty 1 -> area 1.92, harga efektif 48.000
// ---------------------------------------------------------------------------
it('CASE 3: 1.6x1.2 m qty 1 -> area 1.92, harga efektif 48.000', function () {
    $this->actingAs(User::factory()->create());
    $product = makeAreaProduct('Spanduk Biasa', 25000, 15000);

    $this->from('/pos')->post('/pos', posAreaCartPayload($product, [
        'total' => 48000,
        'uang_diterima' => 48000,
    ], [
        'price' => 48000,
        'quantity' => 1,
        'length' => 1.6,
        'width' => 1.2,
        'area_per_piece' => 1.92,
        'total_area' => 1.92,
    ]))->assertRedirect();

    $item = TransactionItem::first();
    expect((float) $item->quantity)->toBe(1.0)
        ->and((float) $item->selling_price)->toBe(48000.0)
        ->and((float) $item->base_price)->toBe(28800.0)
        ->and((float) $item->subtotal_price)->toBe(48000.0)
        ->and((float) $item->subtotal_base)->toBe(28800.0)
        ->and((float) $item->profit)->toBe(19200.0);

    $metadata = $item->metadata;
    expect((float) $metadata['area_per_piece'])->toBe(1.92)
        ->and((float) $metadata['total_area'])->toBe(1.92);
});

// ---------------------------------------------------------------------------
// CASE 4: produk non-area (fotokopi per lembar / rim) tidak berubah perilaku
// ---------------------------------------------------------------------------
it('CASE 4: produk non-area (fotokopi per lembar) tidak berubah perilakunya', function () {
    $this->actingAs(User::factory()->create());
    $product = makeNonAreaProduct('Fotokopi', 200, 100, 'lembar');

    $this->from('/pos')->post('/pos', posAreaCartPayload($product, [
        'total' => 1000,
        'uang_diterima' => 1000,
    ], [
        'price' => 200,
        'quantity' => 5,
        'type' => 'fotokopi',
        'detail' => '5 lembar x Rp 200',
        'is_area_based' => false,
    ]))->assertRedirect();

    $item = TransactionItem::first();
    expect((float) $item->quantity)->toBe(5.0)
        ->and((float) $item->selling_price)->toBe(200.0)
        ->and((float) $item->base_price)->toBe(100.0)
        ->and((float) $item->subtotal_price)->toBe(1000.0)
        ->and((float) $item->subtotal_base)->toBe(500.0)
        ->and((float) $item->profit)->toBe(500.0);

    $metadata = $item->metadata ?? [];
    expect($metadata)->not->toHaveKey('area_per_piece');
});

// ---------------------------------------------------------------------------
// CASE 5: total transaksi == jumlah seluruh transaction_items
// ---------------------------------------------------------------------------
it('CASE 5: total transaksi sama dengan jumlah seluruh item', function () {
    $this->actingAs(User::factory()->create());
    $spanduk = makeAreaProduct('Spanduk Biasa', 25000, 15000);
    $fotokopi = makeNonAreaProduct('Fotokopi', 200, 100, 'lembar');

    // Spanduk 2x1 qty 2 -> subtotal 100.000, base 60.000, profit 40.000
    // Fotokopi 5 lembar -> subtotal 1.000, base 500, profit 500
    $response = $this->from('/pos')->post('/pos', [
        'cart' => [
            [
                'id' => $spanduk->id,
                'name' => 'Spanduk Biasa',
                'price' => 50000,
                'quantity' => 2,
                'type' => 'cetak',
                'detail' => 'Ukuran: 2 x 1 m',
                'is_area_based' => true,
                'length' => 2,
                'width' => 1,
                'area_per_piece' => 2,
                'total_area' => 4,
                'selling_rate' => 25000,
                'base_rate' => 15000,
                'print_vendor_id' => null,
            ],
            [
                'id' => $fotokopi->id,
                'name' => 'Fotokopi',
                'price' => 200,
                'quantity' => 5,
                'type' => 'fotokopi',
                'detail' => '5 lembar x Rp 200',
                'print_vendor_id' => null,
            ],
        ],
        'total' => 101000,
        'payment_method_id' => posCashMethod()->id,
        'uang_diterima' => 101000,
        'customer_id' => null,
        'invoice_customer_name' => null,
        'invoice_customer_phone' => null,
        'keterangan' => null,
    ]);

    $response->assertRedirect();

    $transaction = Transaction::first();
    expect($transaction)->not->toBeNull();

    $items = TransactionItem::all();

    expect((float) $transaction->total_price)
        ->toBe((float) $items->sum('subtotal_price'))
        ->and((float) $transaction->total_base_price)
        ->toBe((float) $items->sum('subtotal_base'))
        ->and((float) $transaction->total_profit)
        ->toBe((float) $items->sum('profit'))
        ->and((float) $transaction->total_price)->toBe(101000.0)
        ->and((float) $transaction->total_base_price)->toBe(60500.0)
        ->and((float) $transaction->total_profit)->toBe(40500.0);
});

// ---------------------------------------------------------------------------
// VALIDASI: length <= 0, width <= 0, qty 0/negatif/desimal -> ditolak
// ---------------------------------------------------------------------------
it('menolak produk area dengan panjang atau lebar <= 0', function () {
    $this->actingAs(User::factory()->create());
    $product = makeAreaProduct('Spanduk Biasa', 25000, 15000);

    $this->from('/pos')->post('/pos', posAreaCartPayload($product, [], [
        'length' => 0,
        'width' => 1,
    ]))->assertSessionHasErrors('cart.0.length');

    $this->from('/pos')->post('/pos', posAreaCartPayload($product, [], [
        'length' => 2,
        'width' => 0,
    ]))->assertSessionHasErrors('cart.0.width');

    expect(TransactionItem::count())->toBe(0);
});

it('menolak produk area dengan quantity 0, negatif, atau desimal', function () {
    $this->actingAs(User::factory()->create());
    $product = makeAreaProduct('Spanduk Biasa', 25000, 15000);

    $this->from('/pos')->post('/pos', posAreaCartPayload($product, [], [
        'quantity' => 0,
    ]))->assertSessionHasErrors('cart.0.quantity');

    $this->from('/pos')->post('/pos', posAreaCartPayload($product, [], [
        'quantity' => -2,
    ]))->assertSessionHasErrors('cart.0.quantity');

    $this->from('/pos')->post('/pos', posAreaCartPayload($product, [], [
        'quantity' => 1.5,
    ]))->assertSessionHasErrors('cart.0.quantity');

    expect(TransactionItem::count())->toBe(0);
});
