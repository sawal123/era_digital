<?php

use App\Models\Category;
use App\Models\Customer;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\StoreProfile;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Testing\Fluent\AssertableJson;

function customerDigitalProduct(): Product
{
    $category = Category::create([
        'name' => 'PPOB Customer '.fake()->unique()->numerify('###'),
        'slug' => 'ppob-customer-'.fake()->unique()->numerify('###'),
        'type' => 'ppob',
    ]);

    return Product::create([
        'category_id' => $category->id,
        'sku' => 'PPOB-CUST-'.fake()->unique()->numerify('###'),
        'name' => 'Token Test',
        'unit' => 'transaksi',
        'base_price' => 0,
        'selling_price' => 0,
        'admin_fee' => 0,
        'stock' => 0,
        'is_active' => true,
    ]);
}

it('menggabungkan customer duplikat dan memindahkan akun digital serta transaksi', function () {
    $this->actingAs(User::factory()->create());

    $master = Customer::create([
        'name' => 'Budi',
        'customer_type' => 'general',
        'phone' => '0811111111',
    ]);
    $duplicate = Customer::create([
        'name' => '  budi  ',
        'customer_type' => 'token',
        'phone' => '126150561229',
    ]);
    $duplicate->digitalAccounts()->create([
        'type' => 'token',
        'account_number' => '126150561229',
        'account_name' => 'Budi',
    ]);
    $transaction = Transaction::create([
        'invoice_number' => 'CUSTOMER-MERGE-001',
        'cashier_id' => User::factory()->create()->id,
        'customer_id' => $duplicate->id,
        'customer_name' => 'Budi',
        'total_base_price' => 98000,
        'total_price' => 100000,
        'total_profit' => 2000,
        'payment_method' => 'cash',
        'payment_status' => 'paid',
    ]);

    $this->post('/customers/'.$master->id.'/merge', [
        'source_customer_ids' => [$duplicate->id],
    ])->assertRedirect();

    expect(Customer::find($duplicate->id))->toBeNull()
        ->and($master->fresh()->digitalAccounts()->where('account_number', '126150561229')->exists())->toBeTrue()
        ->and((int) $transaction->fresh()->customer_id)->toBe($master->id);
});

it('menggunakan customer pemilik akun digital saat transaksi dibuat', function () {
    $this->actingAs(User::factory()->create());
    $product = customerDigitalProduct();
    $customer = Customer::create([
        'name' => 'Budi',
        'customer_type' => 'general',
        'phone' => null,
    ]);
    $customer->digitalAccounts()->create([
        'type' => 'token',
        'account_number' => '126150561229',
        'account_name' => 'Budi',
    ]);
    StoreProfile::create([
        'store_name' => 'Era Digital',
        'address' => 'Alamat test',
        'phone' => '0800000000',
        'saldo_digital' => 2600000,
    ]);

    $paymentMethod = PaymentMethod::firstOrCreate(
        ['code' => 'cash'],
        ['name' => 'Cash / Tunai', 'is_cash' => true, 'is_active' => true, 'sort_order' => 1]
    );

    $this->get('/pos')->assertOk();
    $this->post('/pos', [
        'cart' => [[
            'id' => $product->id,
            'name' => 'Token Test',
            'price' => 100000,
            'quantity' => 1,
            'type' => 'digital',
            'nominal' => 100000,
            'provider_cost' => 98000,
            'admin_fee' => 0,
            'digital_type' => 'token',
            'account_number' => '126150561229',
            'account_name' => 'Budi',
        ]],
        'total' => 100000,
        'payment_method_id' => $paymentMethod->id,
        'uang_diterima' => 100000,
    ])->assertRedirect();

    expect(Customer::query()->count())->toBe(1)
        ->and((int) Transaction::firstOrFail()->customer_id)->toBe($customer->id);
});

it('mengirim total keuntungan customer berdasarkan biaya provider aktual', function () {
    $this->actingAs(User::factory()->create());
    $product = customerDigitalProduct();
    $customer = Customer::create([
        'name' => 'Budi Profit',
        'customer_type' => 'general',
    ]);
    $transaction = Transaction::create([
        'invoice_number' => 'CUSTOMER-PROFIT-001',
        'cashier_id' => User::factory()->create()->id,
        'customer_id' => $customer->id,
        'customer_name' => $customer->name,
        'total_base_price' => 100000,
        'total_price' => 100000,
        'total_profit' => 0,
        'payment_method' => 'cash',
        'payment_status' => 'paid',
    ]);
    $transaction->items()->create([
        'product_id' => $product->id,
        'item_name' => $product->name,
        'type' => 'ppob',
        'unit' => 'transaksi',
        'quantity' => 1,
        'base_price' => 100000,
        'selling_price' => 100000,
        'subtotal_base' => 100000,
        'provider_cost' => 98000,
        'subtotal_price' => 100000,
        'profit' => 0,
    ]);

    $this->get('/customers')
        ->assertOk()
        ->assertInertia(fn (AssertableJson $page) => $page
            ->component('Customers/Index')
            ->where('customers.0.id', $customer->id)
            ->where('customers.0.total_profit', 2000));
});

it('menormalisasi dan mencegah akun digital yang sama tercatat dua kali', function () {
    $this->actingAs(User::factory()->create());
    $firstCustomer = Customer::create([
        'name' => 'Customer Satu',
        'customer_type' => 'general',
    ]);
    $secondCustomer = Customer::create([
        'name' => 'Customer Dua',
        'customer_type' => 'general',
    ]);

    $this->post('/customers/'.$firstCustomer->id.'/digital-accounts', [
        'type' => 'phone',
        'account_number' => '0812 3456 7890',
        'account_name' => 'Customer Satu',
    ])->assertRedirect();

    $this->post('/customers/'.$secondCustomer->id.'/digital-accounts', [
        'type' => 'phone',
        'account_number' => '081234567890',
        'account_name' => 'Customer Dua',
    ])->assertStatus(422);

    expect($firstCustomer->fresh()->digitalAccounts()->first()->account_number)->toBe('081234567890')
        ->and($secondCustomer->fresh()->digitalAccounts()->count())->toBe(0);
});
