<?php

use App\Models\Customer;
use App\Models\PaymentMethod;
use App\Models\Transaction;
use App\Models\User;

function customerCashMethod(): PaymentMethod
{
    return PaymentMethod::firstOrCreate(
        ['code' => 'cash'],
        ['name' => 'Cash / Tunai', 'is_cash' => true, 'is_active' => true, 'sort_order' => 1]
    );
}

it('mengembalikan data customer beserta transactions_count dan total_spent aggregate', function () {
    $user = User::factory()->create();

    $customerA = Customer::create(['name' => 'Alfa', 'customer_type' => 'general']);
    $customerB = Customer::create(['name' => 'Beta', 'customer_type' => 'general']);
    $customerC = Customer::create(['name' => 'Charlie', 'customer_type' => 'general']);

    $method = customerCashMethod();

    // Customer A: 2 transaksi, total 150.000
    Transaction::create([
        'invoice_number' => 'TRX-TEST-001',
        'cashier_id' => $user->id,
        'customer_id' => $customerA->id,
        'customer_name' => $customerA->name,
        'total_base_price' => 50000,
        'total_price' => 100000,
        'total_profit' => 50000,
        'jumlah_dibayar' => 100000,
        'sisa_tagihan' => 0,
        'status_bayar' => 'lunas',
        'payment_status' => 'paid',
        'payment_method_id' => $method->id,
        'payment_method' => $method->name,
    ]);

    Transaction::create([
        'invoice_number' => 'TRX-TEST-002',
        'cashier_id' => $user->id,
        'customer_id' => $customerA->id,
        'customer_name' => $customerA->name,
        'total_base_price' => 20000,
        'total_price' => 50000,
        'total_profit' => 30000,
        'jumlah_dibayar' => 50000,
        'sisa_tagihan' => 0,
        'status_bayar' => 'lunas',
        'payment_status' => 'paid',
        'payment_method_id' => $method->id,
        'payment_method' => $method->name,
    ]);

    // Customer B: 3 transaksi, total 90.000
    for ($i = 3; $i <= 5; $i++) {
        Transaction::create([
            'invoice_number' => "TRX-TEST-00{$i}",
            'cashier_id' => $user->id,
            'customer_id' => $customerB->id,
            'customer_name' => $customerB->name,
            'total_base_price' => 15000,
            'total_price' => 30000,
            'total_profit' => 15000,
            'jumlah_dibayar' => 30000,
            'sisa_tagihan' => 0,
            'status_bayar' => 'lunas',
            'payment_status' => 'paid',
            'payment_method_id' => $method->id,
            'payment_method' => $method->name,
        ]);
    }

    // Customer C: 0 transaksi

    $response = $this->actingAs($user)->get('/customers');

    $response->assertOk();
    $response->assertInertia(function ($page) use ($customerA, $customerB, $customerC) {
        $data = $page->toArray();
        $customers = collect($data['props']['customers']);

        $alfa = $customers->firstWhere('id', $customerA->id);
        $beta = $customers->firstWhere('id', $customerB->id);
        $charlie = $customers->firstWhere('id', $customerC->id);

        expect($alfa['transactions_count'])->toBe(2)
            ->and((float) $alfa['total_spent'])->toBe(150000.0);

        expect($beta['transactions_count'])->toBe(3)
            ->and((float) $beta['total_spent'])->toBe(90000.0);

        expect($charlie['transactions_count'])->toBe(0)
            ->and($charlie['total_spent'])->toBeNull();

        return true;
    });
});
