<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Transaction;
use App\Models\User;

function productAggregateCategory(): Category
{
    return Category::create([
        'name' => 'Kategori Agregat '.fake()->unique()->numerify('###'),
        'slug' => 'kategori-agregat-'.fake()->unique()->numerify('###'),
        'type' => 'fisik',
    ]);
}

function productAggregateProduct(float $stock = 10): Product
{
    return Product::create([
        'category_id' => productAggregateCategory()->id,
        'sku' => 'AGG-'.fake()->unique()->numerify('###'),
        'name' => 'Produk Agregat',
        'unit' => 'pcs',
        'base_price' => 1000,
        'selling_price' => 1500,
        'stock' => $stock,
        'min_stock' => 1,
        'is_active' => true,
    ]);
}

function productAggregateTransaction(User $user, Product $product, float $quantity, $createdAt): Transaction
{
    $transaction = Transaction::create([
        'invoice_number' => 'AGG-'.fake()->unique()->numerify('#####'),
        'cashier_id' => $user->id,
        'total_base_price' => 1000 * $quantity,
        'total_price' => 1500 * $quantity,
        'total_profit' => 500 * $quantity,
        'payment_method' => 'cash',
        'payment_status' => 'paid',
    ]);
    $transaction->forceFill([
        'created_at' => $createdAt,
        'updated_at' => $createdAt,
    ])->save();
    $transaction->items()->create([
        'product_id' => $product->id,
        'item_name' => $product->name,
        'type' => 'fisik',
        'unit' => 'pcs',
        'quantity' => $quantity,
        'base_price' => 1000,
        'selling_price' => 1500,
        'subtotal_base' => 1000 * $quantity,
        'subtotal_price' => 1500 * $quantity,
        'profit' => 500 * $quantity,
    ]);

    return $transaction;
}

it('mengirimkan total terjual keseluruhan ke master produk', function () {
    $user = User::factory()->create();
    $product = productAggregateProduct();

    productAggregateTransaction($user, $product, 3, now()->subDay());
    productAggregateTransaction($user, $product, 2, now());

    $response = $this->actingAs($user)->get('/products');

    $response->assertOk();
    $response->assertInertia(function ($page) use ($product) {
        $row = collect($page->toArray()['props']['products'])
            ->firstWhere('id', $product->id);

        expect((float) $row['total_sold'])->toBe(5.0)
            ->and(array_key_exists('today_sold', $row))->toBeFalse();

        return true;
    });
});

it('mencatat penyesuaian saat stok produk diubah manual', function () {
    $user = User::factory()->create();
    $product = productAggregateProduct(10);

    $this->actingAs($user)->patch('/products/'.$product->id, [
        'category_id' => $product->category_id,
        'name' => $product->name,
        'sku' => $product->sku,
        'unit' => $product->unit,
        'base_price' => $product->base_price,
        'selling_price' => $product->selling_price,
        'admin_fee' => 0,
        'stock' => 7,
        'is_active' => true,
    ])->assertRedirect();

    $movement = StockMovement::where('reference_type', 'product_edit')->firstOrFail();

    expect((float) $product->fresh()->stock)->toBe(7.0)
        ->and($movement->type)->toBe('adjustment')
        ->and((float) $movement->quantity)->toBe(3.0);
});
