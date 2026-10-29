<?php

use App\Models\Category;
use App\Models\Expense;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\StockMovement;
use App\Models\Transaction;
use App\Models\User;

function auditPhysicalCategory(): Category
{
    return Category::create([
        'name' => 'Audit Fisik '.fake()->unique()->numerify('###'),
        'slug' => 'audit-fisik-'.fake()->unique()->numerify('###'),
        'type' => 'fisik',
    ]);
}

function auditPhysicalProduct(float $stock = 0): Product
{
    return Product::create([
        'category_id' => auditPhysicalCategory()->id,
        'sku' => 'AUDIT-'.fake()->unique()->numerify('###'),
        'name' => 'Produk Audit',
        'unit' => 'pcs',
        'base_price' => 2000,
        'selling_price' => 3500,
        'stock' => $stock,
        'min_stock' => 1,
        'is_active' => true,
    ]);
}

function auditCashMethod(): PaymentMethod
{
    return PaymentMethod::firstOrCreate(
        ['code' => 'cash'],
        ['name' => 'Cash / Tunai', 'is_cash' => true, 'is_active' => true, 'sort_order' => 1]
    );
}

function auditPhysicalCart(Product $product, int $quantity = 1): array
{
    return [
        'cart' => [[
            'id' => $product->id,
            'name' => $product->name,
            'price' => 1,
            'quantity' => $quantity,
            'type' => 'fisik',
        ]],
        'total' => 1,
        'payment_method_id' => auditCashMethod()->id,
        'uang_diterima' => 3500 * $quantity,
    ];
}

it('menggunakan harga master dan mencatat stock movement penjualan', function () {
    $this->actingAs(User::factory()->create());
    $product = auditPhysicalProduct(2);

    $this->post('/pos', auditPhysicalCart($product))->assertRedirect();

    $transaction = Transaction::firstOrFail();
    $item = $transaction->items()->firstOrFail();
    $movement = StockMovement::where('reference_type', 'transaction')->firstOrFail();

    expect((float) $item->selling_price)->toBe(3500.0)
        ->and((float) $transaction->total_price)->toBe(3500.0)
        ->and((float) $product->fresh()->stock)->toBe(1.0)
        ->and($movement->type)->toBe('out')
        ->and((int) $movement->reference_id)->toBe($transaction->id);
});

it('menolak penjualan fisik jika stok tidak mencukupi', function () {
    $this->actingAs(User::factory()->create());
    $product = auditPhysicalProduct();

    $this->post('/pos', auditPhysicalCart($product))->assertRedirect();

    expect(Transaction::query()->count())->toBe(0)
        ->and(StockMovement::query()->count())->toBe(0)
        ->and((float) $product->fresh()->stock)->toBe(0.0);
});

it('mencatat pembalik stok saat transaksi fisik dihapus', function () {
    $this->actingAs(User::factory()->create());
    $product = auditPhysicalProduct(2);

    $this->post('/pos', auditPhysicalCart($product))->assertRedirect();
    $transaction = Transaction::firstOrFail();

    $this->delete('/reports/'.$transaction->id)->assertRedirect();

    expect((float) $product->fresh()->stock)->toBe(2.0)
        ->and(StockMovement::where('reference_type', 'transaction_reversal')->where('reference_id', $transaction->id)->count())->toBe(1);
});

it('mengembalikan stok dan expense yang terhubung saat restock diedit', function () {
    $this->actingAs(User::factory()->create());
    $firstProduct = auditPhysicalProduct();
    $secondProduct = auditPhysicalProduct();

    $this->post('/purchases', [
        'product_id' => $firstProduct->id,
        'quantity' => 2,
        'cost_price' => 1000,
        'purchase_date' => '2026-10-10',
    ])->assertRedirect();
    $purchase = Purchase::firstOrFail();

    $this->patch('/purchases/'.$purchase->id, [
        'product_id' => $secondProduct->id,
        'quantity' => 3,
        'cost_price' => 1500,
        'purchase_date' => '2026-10-11',
    ])->assertRedirect();

    expect((float) $firstProduct->fresh()->stock)->toBe(0.0)
        ->and((float) $secondProduct->fresh()->stock)->toBe(3.0)
        ->and((int) Expense::where('purchase_id', $purchase->id)->firstOrFail()->purchase_id)->toBe($purchase->id)
        ->and(StockMovement::where('reference_type', 'purchase_edit')->where('reference_id', $purchase->id)->value('product_id'))->toBe($secondProduct->id)
        ->and(StockMovement::where('reference_type', 'purchase_edit_reversal')->where('reference_id', $purchase->id)->value('product_id'))->toBe($firstProduct->id);
});

it('menolak penghapusan restock jika stoknya sudah terjual', function () {
    $this->actingAs(User::factory()->create());
    $product = auditPhysicalProduct();

    $this->post('/purchases', [
        'product_id' => $product->id,
        'quantity' => 2,
        'cost_price' => 1000,
        'purchase_date' => '2026-10-10',
    ])->assertRedirect();
    $purchase = Purchase::firstOrFail();
    $this->post('/pos', auditPhysicalCart($product))->assertRedirect();

    $this->delete('/purchases/'.$purchase->id)->assertRedirect();

    expect(Purchase::find($purchase->id))->not->toBeNull()
        ->and((float) $product->fresh()->stock)->toBe(1.0);
});

it('menolak pembayaran piutang yang melebihi sisa tagihan', function () {
    $this->actingAs(User::factory()->create());
    $transaction = Transaction::create([
        'invoice_number' => 'AUDIT-PIUTANG-001',
        'cashier_id' => User::factory()->create()->id,
        'total_base_price' => 70000,
        'total_price' => 100000,
        'total_profit' => 30000,
        'payment_method' => 'cash',
        'payment_status' => 'partial',
        'status_bayar' => 'dp',
        'jumlah_dibayar' => 20000,
        'sisa_tagihan' => 80000,
    ]);

    $this->from('/receivables')->post('/receivables/'.$transaction->id.'/pay', [
        'bayar_nominal' => 90000,
    ])->assertSessionHasErrors('bayar_nominal');

    expect((float) $transaction->fresh()->jumlah_dibayar)->toBe(20000.0)
        ->and($transaction->paymentHistories()->count())->toBe(0);
});
