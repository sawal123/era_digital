<?php

use App\Models\Category;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\StoreProfile;
use App\Models\User;

function posTestUser(): User
{
    return User::factory()->create();
}

function posTestCash(): PaymentMethod
{
    return PaymentMethod::firstOrCreate(
        ['code' => 'cash'],
        ['name' => 'Cash / Tunai', 'is_cash' => true, 'is_active' => true, 'sort_order' => 1]
    );
}

function posPpobProduct(): Product
{
    $category = Category::firstOrCreate(
        ['slug' => 'ppob-digital'],
        ['name' => 'PPOB / Digital', 'type' => 'ppob']
    );

    return Product::create([
        'category_id' => $category->id,
        'sku' => 'PPOB-PLN-50K',
        'name' => 'Token Listrik 50.000',
        'unit' => 'transaksi',
        'base_price' => 50000,
        'selling_price' => 52500,
        'is_active' => true,
    ]);
}

function posPhysicalProduct(): Product
{
    $category = Category::firstOrCreate(
        ['slug' => 'barang-fisik'],
        ['name' => 'Barang Fisik', 'type' => 'fisik']
    );

    return Product::create([
        'category_id' => $category->id,
        'sku' => 'BRG-PENA-01',
        'name' => 'Pena Gel Hitam',
        'unit' => 'pcs',
        'base_price' => 2000,
        'selling_price' => 3500,
        'stock' => 100,
        'is_active' => true,
    ]);
}

beforeEach(function () {
    StoreProfile::firstOrCreate([], [
        'store_name' => 'Era Digital',
        'address' => 'Jl. Raya Utama No. 45',
        'phone' => '0812345678',
        'saldo_digital' => 500000.00,
    ]);
});

it('menolak produk PPOB / digital dengan quantity selain 1', function () {
    $user = posTestUser();
    $product = posPpobProduct();
    $method = posTestCash();

    // Coba kirim Qty = 2
    $response = $this->actingAs($user)->from('/pos')->post('/pos', [
        'cart' => [
            [
                'id' => $product->id,
                'name' => 'Token Listrik 50.000 - Rp 50.000',
                'price' => 52500,
                'quantity' => 2,
                'type' => 'digital',
                'detail' => 'No: 123456789 | Nama: Budi',
                'digital_type' => 'token',
                'account_number' => '123456789',
                'account_name' => 'Budi',
                'admin_fee' => 2500,
                'nominal' => 50000,
            ],
        ],
        'total' => 105000,
        'payment_method_id' => $method->id,
        'uang_diterima' => 105000,
    ]);

    $response->assertSessionHasErrors('cart.0.quantity');
});

it('menerima produk PPOB / digital dengan quantity tepat 1', function () {
    $user = posTestUser();
    $product = posPpobProduct();
    $method = posTestCash();

    $response = $this->actingAs($user)->from('/pos')->post('/pos', [
        'cart' => [
            [
                'id' => $product->id,
                'name' => 'Token Listrik 50.000 - Rp 50.000',
                'price' => 52500,
                'quantity' => 1,
                'type' => 'digital',
                'detail' => 'No: 123456789 | Nama: Budi',
                'digital_type' => 'token',
                'account_number' => '123456789',
                'account_name' => 'Budi',
                'admin_fee' => 2500,
                'nominal' => 50000,
            ],
        ],
        'total' => 52500,
        'payment_method_id' => $method->id,
        'uang_diterima' => 52500,
    ]);

    $response->assertRedirect();
});

it('menolak quantity <= 0 atau melebihi 999.999 pada backend', function () {
    $user = posTestUser();
    $product = posPhysicalProduct();
    $method = posTestCash();

    // Qty <= 0
    $this->actingAs($user)->from('/pos')->post('/pos', [
        'cart' => [
            [
                'id' => $product->id,
                'name' => $product->name,
                'price' => 3500,
                'quantity' => 0,
                'type' => 'fisik',
            ],
        ],
        'total' => 0,
        'payment_method_id' => $method->id,
        'uang_diterima' => 0,
    ])->assertSessionHasErrors('cart.0.quantity');

    // Qty > 999999
    $this->actingAs($user)->from('/pos')->post('/pos', [
        'cart' => [
            [
                'id' => $product->id,
                'name' => $product->name,
                'price' => 3500,
                'quantity' => 1000000,
                'type' => 'fisik',
            ],
        ],
        'total' => 3500000000,
        'payment_method_id' => $method->id,
        'uang_diterima' => 3500000000,
    ])->assertSessionHasErrors('cart.0.quantity');
});
