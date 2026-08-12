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
        ->and($metadata['pricing_unit'])->toBe('m2')
        ->and((float) $metadata['selling_rate'])->toBe(25000.0)
        ->and((float) $metadata['base_rate'])->toBe(15000.0)
        ->and($metadata['detail'])->toBe('Ukuran: 2 x 1 m');

    // Snapshot unit area = pcs (bukan meter), karena quantity bermakna pcs.
    expect($item->unit)->toBe('pcs');
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

it('menolak quantity non-numeric untuk produk area', function () {
    $this->actingAs(User::factory()->create());
    $product = makeAreaProduct('Spanduk Biasa', 25000, 15000);

    $this->from('/pos')->post('/pos', posAreaCartPayload($product, [], [
        'quantity' => 'abc',
    ]))->assertSessionHasErrors('cart.0.quantity');

    expect(Transaction::count())->toBe(0);
});

// ---------------------------------------------------------------------------
// SECURITY / MANIPULATION: nilai finansial dari frontend harus diabaikan
// untuk produk area yang ada di database.
// ---------------------------------------------------------------------------

it('A: area_per_piece dari frontend diabaikan (dihitung dari length x width)', function () {
    $this->actingAs(User::factory()->create());
    $product = makeAreaProduct('Spanduk Biasa', 25000, 15000);

    $this->from('/pos')->post('/pos', posAreaCartPayload($product, [], [
        'price' => 50000,
        'quantity' => 1,
        'area_per_piece' => 0.01, // manipulasi
    ]))->assertRedirect();

    $item = TransactionItem::first();
    $metadata = $item->metadata;

    expect((float) $metadata['area_per_piece'])->toBe(2.0)
        ->and((float) $metadata['total_area'])->toBe(2.0)
        ->and((float) $item->selling_price)->toBe(50000.0)
        ->and((float) $item->base_price)->toBe(30000.0);
});

it('B: selling_rate dari frontend diabaikan (pakai product.selling_price)', function () {
    $this->actingAs(User::factory()->create());
    $product = makeAreaProduct('Spanduk Biasa', 25000, 15000);

    $this->from('/pos')->post('/pos', posAreaCartPayload($product, [], [
        'selling_rate' => 1, // manipulasi
        'price' => 2,
    ]))->assertRedirect();

    $item = TransactionItem::first();

    expect((float) $item->metadata['selling_rate'])->toBe(25000.0)
        ->and((float) $item->selling_price)->toBe(50000.0);
});

it('C: base_rate dari frontend diabaikan (pakai product.base_price)', function () {
    $this->actingAs(User::factory()->create());
    $product = makeAreaProduct('Spanduk Biasa', 25000, 15000);

    $this->from('/pos')->post('/pos', posAreaCartPayload($product, [], [
        'base_rate' => 0, // manipulasi
    ]))->assertRedirect();

    $item = TransactionItem::first();

    expect((float) $item->metadata['base_rate'])->toBe(15000.0)
        ->and((float) $item->base_price)->toBe(30000.0);
});

it('D: price dari frontend diabaikan untuk area DB (selling_price dihitung server)', function () {
    $this->actingAs(User::factory()->create());
    $product = makeAreaProduct('Spanduk Biasa', 25000, 15000);

    $this->from('/pos')->post('/pos', posAreaCartPayload($product, [], [
        'price' => 100, // manipulasi
    ]))->assertRedirect();

    $item = TransactionItem::first();

    expect((float) $item->selling_price)->toBe(50000.0)
        ->and((float) $item->subtotal_price)->toBe(50000.0);
});

it('E: request.total palsu diabaikan (total_price = jumlah item server)', function () {
    $this->actingAs(User::factory()->create());
    $product = makeAreaProduct('Spanduk Biasa', 25000, 15000);

    $this->from('/pos')->post('/pos', posAreaCartPayload($product, [
        'total' => 1000, // manipulasi
        'uang_diterima' => 1000,
    ]))->assertRedirect();

    $transaction = Transaction::first();

    expect((float) $transaction->total_price)->toBe(50000.0);
});

it('F: status pembayaran memakai total server (dp/partial)', function () {
    $this->actingAs(User::factory()->create());
    $product = makeAreaProduct('Spanduk Biasa', 25000, 15000);

    // Server total 50.000, bayar 20.000 -> dp/partial
    $this->from('/pos')->post('/pos', posAreaCartPayload($product, [
        'total' => 1000, // manipulasi, diabaikan
        'uang_diterima' => 20000,
    ]))->assertRedirect();

    $transaction = Transaction::first();

    expect((float) $transaction->total_price)->toBe(50000.0)
        ->and((float) $transaction->jumlah_dibayar)->toBe(20000.0)
        ->and((float) $transaction->sisa_tagihan)->toBe(30000.0)
        ->and($transaction->status_bayar)->toBe('dp')
        ->and($transaction->payment_status)->toBe('partial');

    $history = $transaction->paymentHistories()->first();
    expect((float) $history->jumlah_bayar)->toBe(20000.0);
});

it('G: pelunasan memakai total server (lunas/paid)', function () {
    $this->actingAs(User::factory()->create());
    $product = makeAreaProduct('Spanduk Biasa', 25000, 15000);

    $this->from('/pos')->post('/pos', posAreaCartPayload($product, [
        'total' => 1000, // manipulasi
        'uang_diterima' => 50000,
    ]))->assertRedirect();

    $transaction = Transaction::first();

    expect((float) $transaction->total_price)->toBe(50000.0)
        ->and((float) $transaction->jumlah_dibayar)->toBe(50000.0)
        ->and((float) $transaction->sisa_tagihan)->toBe(0.0)
        ->and($transaction->status_bayar)->toBe('lunas')
        ->and($transaction->payment_status)->toBe('paid');

    $history = $transaction->paymentHistories()->first();
    expect((float) $history->jumlah_bayar)->toBe(50000.0);
});

it('H: produk area DB dengan base_price 0 ditolak', function () {
    $this->actingAs(User::factory()->create());
    $product = makeAreaProduct('Spanduk Biasa', 25000, 0);

    $this->from('/pos')->post('/pos', posAreaCartPayload($product))->assertSessionHasErrors('cart.0.base_rate');

    expect(Transaction::count())->toBe(0)
        ->and(TransactionItem::count())->toBe(0);
});

it('I: quantity desimal ditolak backend untuk produk area', function () {
    $this->actingAs(User::factory()->create());
    $product = makeAreaProduct('Spanduk Biasa', 25000, 15000);

    $this->from('/pos')->post('/pos', posAreaCartPayload($product, [], [
        'quantity' => 1.5,
    ]))->assertSessionHasErrors('cart.0.quantity');

    expect(Transaction::count())->toBe(0);
});

it('J: area_per_piece mismatch diabaikan (1.6 x 1.2 -> 1.92)', function () {
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
        'area_per_piece' => 2, // mismatch dengan kalkulasi server
    ]))->assertRedirect();

    $item = TransactionItem::first();

    expect((float) $item->metadata['area_per_piece'])->toBe(1.92)
        ->and((float) $item->metadata['total_area'])->toBe(1.92)
        ->and((float) $item->selling_price)->toBe(48000.0)
        ->and((float) $item->base_price)->toBe(28800.0)
        ->and((float) $item->profit)->toBe(19200.0);
});

// ---------------------------------------------------------------------------
// BLOCKER: produk wajib berasal dari DB & aktif (server-authoritative)
// ---------------------------------------------------------------------------
it('menolak cart dengan product id yang tidak ada di database', function () {
    $this->actingAs(User::factory()->create());
    posCashMethod();

    $payload = posAreaCartPayload(makeAreaProduct('Spanduk Biasa', 25000, 15000));
    $payload['cart'][0]['id'] = 999999; // id tidak ada di database

    $this->from('/pos')->post('/pos', $payload)->assertSessionHasErrors('cart.0.id');

    expect(Transaction::count())->toBe(0)
        ->and(TransactionItem::count())->toBe(0);
});

it('menolak cart dengan produk non-aktif', function () {
    $this->actingAs(User::factory()->create());
    $product = makeAreaProduct('Spanduk Biasa', 25000, 15000);
    $product->update(['is_active' => false]);
    posCashMethod();

    $this->from('/pos')->post('/pos', posAreaCartPayload($product))->assertSessionHasErrors('cart.0.id');

    expect(Transaction::count())->toBe(0)
        ->and(TransactionItem::count())->toBe(0);
});

// ---------------------------------------------------------------------------
// BLOCKER: tipe item diambil dari kategori produk, bukan dari request
// ---------------------------------------------------------------------------
it('mengabaikan manipulasi type dari frontend (tipe berasal dari kategori produk)', function () {
    $this->actingAs(User::factory()->create());
    $product = makeAreaProduct('Spanduk Biasa', 25000, 15000);
    posCashMethod();

    // StoreProfile dibuat agar kita bisa membuktikan jalur PPOB TIDAK terpanggil
    // (saldo digital tidak boleh berkurang untuk spanduk).
    \App\Models\StoreProfile::create([
        'store_name' => 'Toko Test',
        'address' => 'Alamat',
        'phone' => '0812',
        'saldo_digital' => 100000,
    ]);

    $this->from('/pos')->post('/pos', posAreaCartPayload($product, [], [
        'type' => 'digital', // manipulasi: spanduk dicoba dianggap digital/ppob
    ]))->assertRedirect();

    $item = TransactionItem::first();
    expect($item)->not->toBeNull()
        ->and($item->type)->toBe('jasa') // dari kategori jasa-cetak
        ->and((float) $item->subtotal_price)->toBe(50000.0);

    // Jalur PPOB tidak boleh berjalan untuk spanduk.
    expect((float) \App\Models\StoreProfile::first()->saldo_digital)->toBe(100000.0);
});

// ---------------------------------------------------------------------------
// NOTE: catatan item (metadata.note) terpisah dari ukuran (length/width)
// ---------------------------------------------------------------------------
it('menyimpan catatan item ke metadata.note', function () {
    $this->actingAs(User::factory()->create());
    $product = makeAreaProduct('Spanduk Biasa', 25000, 15000);

    $this->from('/pos')->post('/pos', posAreaCartPayload($product, [], [
        'detail' => 'Ukuran: 2 x 1 m',
        'note' => 'agen 1 spanduk',
    ]))->assertRedirect();

    $item = TransactionItem::first();
    $metadata = $item->metadata ?? [];

    expect($metadata['note'])->toBe('agen 1 spanduk')
        ->and((float) $metadata['length'])->toBe(2.0)
        ->and((float) $metadata['width'])->toBe(1.0)
        ->and((float) $metadata['area_per_piece'])->toBe(2.0);
});

it('transaksi tanpa note tetap berhasil', function () {
    $this->actingAs(User::factory()->create());
    $product = makeAreaProduct('Spanduk Biasa', 25000, 15000);

    $this->from('/pos')->post('/pos', posAreaCartPayload($product, [], [
        'detail' => 'Ukuran: 2 x 1 m',
        'note' => '',
    ]))->assertRedirect();

    $item = TransactionItem::first();
    $metadata = $item->metadata ?? [];

    expect(isset($metadata['note']) ? $metadata['note'] : '')->toBe('')
        ->and((float) $item->selling_price)->toBe(50000.0)
        ->and((float) $item->base_price)->toBe(30000.0)
        ->and((float) $item->profit)->toBe(20000.0);
});

it('ukuran dari length/width tetap tersimpan terpisah dari note', function () {
    $this->actingAs(User::factory()->create());
    $product = makeAreaProduct('Spanduk Custom', 30000, 20000);

    $this->from('/pos')->post('/pos', posAreaCartPayload($product, [
        'total' => 90000,
        'uang_diterima' => 90000,
    ], [
        'price' => 90000,
        'quantity' => 1,
        'length' => 1.5,
        'width' => 2,
        'area_per_piece' => 3,
        'detail' => 'Ukuran: 1.5 x 2 m',
        'note' => 'bahan premium',
    ]))->assertRedirect();

    $item = TransactionItem::first();
    $metadata = $item->metadata ?? [];

    // Ukuran dari length/width, bukan dari note
    expect((float) $metadata['length'])->toBe(1.5)
        ->and((float) $metadata['width'])->toBe(2.0)
        ->and((float) $metadata['area_per_piece'])->toBe(3.0)
        ->and($metadata['note'])->toBe('bahan premium')
        // HPP tidak berubah
        ->and((float) $item->selling_price)->toBe(90000.0)
        ->and((float) $item->base_price)->toBe(60000.0)
        ->and((float) $item->profit)->toBe(30000.0);
});
