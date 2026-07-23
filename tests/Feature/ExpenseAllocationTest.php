<?php

use App\Models\Expense;
use App\Models\ExpenseAllocation;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Models\User;
use App\Services\ProfitCalculationService;

function actingUser($testCase): User
{
    $user = User::factory()->create();
    $testCase->actingAs($user);

    return $user;
}

function makeTransaction(array $attributes = [], array $itemAttributes = []): array
{
    $user = User::factory()->create();
    $transaction = Transaction::create(array_merge([
        'invoice_number' => 'TRX-' . fake()->unique()->numerify('###'),
        'cashier_id' => $user->id,
        'customer_name' => 'Customer Test',
        'total_base_price' => 100000,
        'total_price' => 150000,
        'total_profit' => 50000,
        'payment_method' => 'cash',
        'payment_status' => 'paid',
    ], $attributes));

    $item = TransactionItem::create(array_merge([
        'transaction_id' => $transaction->id,
        'item_name' => 'Spanduk Test',
        'type' => 'jasa',
        'unit' => 'meter',
        'quantity' => 1,
        'base_price' => 100000,
        'selling_price' => 150000,
        'subtotal_base' => 100000,
        'subtotal_price' => 150000,
        'profit' => 50000,
        'service_status' => 'menunggu_file',
    ], $itemAttributes));

    return [$transaction, $item];
}

function makeExpense(array $attributes = [], array $allocations = []): Expense
{
    $expense = Expense::create(array_merge([
        'date' => now()->toDateString(),
        'name' => 'Bayar Vendor',
        'amount' => 150000,
        'category' => 'hpp_pesanan',
        'hpp_status' => 'belum_masuk_hpp',
    ], $attributes));

    foreach ($allocations as $allocation) {
        $expense->allocations()->create(array_merge([
            'amount' => 50000,
            'hpp_status' => ExpenseAllocation::STATUS_BELUM_MASUK_HPP,
        ], $allocation));
    }

    return $expense->fresh('allocations');
}

it('membuat expense tanpa allocation', function () {
    actingUser($this);

    $response = $this->post('/expenses', [
        'date' => now()->toDateString(),
        'name' => 'Listrik',
        'amount' => 100000,
        'category' => 'operasional_rutin',
        'note' => 'PLN',
        'allocations' => [],
    ]);

    $response->assertRedirect();
    expect(Expense::first()->allocations)->toHaveCount(0);
});

it('membuat satu expense dengan satu allocation', function () {
    actingUser($this);
    [$transaction, $item] = makeTransaction();

    $this->post('/expenses', [
        'date' => now()->toDateString(),
        'name' => 'Bayar Vendor',
        'amount' => 30000,
        'category' => 'hpp_pesanan',
        'allocations' => [[
            'transaction_id' => $transaction->id,
            'transaction_item_id' => $item->id,
            'amount' => 30000,
            'hpp_status' => 'belum_masuk_hpp',
        ]],
    ])->assertRedirect();

    expect(ExpenseAllocation::count())->toBe(1);
});

it('membuat satu expense dengan tiga transaction berbeda', function () {
    actingUser($this);
    [$a] = makeTransaction();
    [$b] = makeTransaction();
    [$c] = makeTransaction();

    $this->post('/expenses', [
        'date' => now()->toDateString(),
        'name' => 'Bayar Vendor Gabungan',
        'amount' => 150000,
        'category' => 'hpp_pesanan',
        'allocations' => [
            ['transaction_id' => $a->id, 'amount' => 30000, 'hpp_status' => 'belum_masuk_hpp'],
            ['transaction_id' => $b->id, 'amount' => 70000, 'hpp_status' => 'belum_masuk_hpp'],
            ['transaction_id' => $c->id, 'amount' => 50000, 'hpp_status' => 'sudah_masuk_hpp'],
        ],
    ])->assertRedirect();

    expect(ExpenseAllocation::query()->distinct('transaction_id')->count('transaction_id'))->toBe(3);
});

it('mengizinkan allocation ke transaction item yang benar', function () {
    actingUser($this);
    [$transaction, $item] = makeTransaction();

    $this->post('/expenses', [
        'date' => now()->toDateString(),
        'name' => 'Item Vendor',
        'amount' => 40000,
        'category' => 'hpp_pesanan',
        'allocations' => [[
            'transaction_id' => $transaction->id,
            'transaction_item_id' => $item->id,
            'amount' => 40000,
            'hpp_status' => 'belum_masuk_hpp',
        ]],
    ])->assertRedirect();

    expect(ExpenseAllocation::first()->transaction_item_id)->toBe($item->id);
});

it('menolak transaction item yang bukan milik transaction terpilih', function () {
    actingUser($this);
    [$transaction] = makeTransaction();
    [, $wrongItem] = makeTransaction();

    $this->from('/expenses')->post('/expenses', [
        'date' => now()->toDateString(),
        'name' => 'Salah Item',
        'amount' => 40000,
        'category' => 'hpp_pesanan',
        'allocations' => [[
            'transaction_id' => $transaction->id,
            'transaction_item_id' => $wrongItem->id,
            'amount' => 40000,
            'hpp_status' => 'belum_masuk_hpp',
        ]],
    ])->assertSessionHasErrors('allocations.0.transaction_item_id');
});

it('menolak total allocation melebihi amount expense', function () {
    actingUser($this);
    [$transaction] = makeTransaction();

    $this->from('/expenses')->post('/expenses', [
        'date' => now()->toDateString(),
        'name' => 'Kelebihan',
        'amount' => 10000,
        'category' => 'hpp_pesanan',
        'allocations' => [[
            'transaction_id' => $transaction->id,
            'amount' => 11000,
            'hpp_status' => 'belum_masuk_hpp',
        ]],
    ])->assertSessionHasErrors('allocations');
});

it('mengizinkan allocation sebagian', function () {
    actingUser($this);
    [$transaction] = makeTransaction();

    $this->post('/expenses', [
        'date' => now()->toDateString(),
        'name' => 'Sebagian',
        'amount' => 100000,
        'category' => 'hpp_pesanan',
        'allocations' => [[
            'transaction_id' => $transaction->id,
            'amount' => 40000,
            'hpp_status' => 'belum_masuk_hpp',
        ]],
    ])->assertRedirect();

    expect(Expense::first()->fresh('allocations')->allocation_status)->toBe('sebagian');
});

it('menghitung allocated dan unallocated amount dengan benar', function () {
    [$transaction] = makeTransaction();
    $expense = makeExpense(['amount' => 100000], [
        ['transaction_id' => $transaction->id, 'amount' => 40000],
    ]);

    expect($expense->allocated_amount)->toBe(40000.0)
        ->and($expense->unallocated_amount)->toBe(60000.0);
});

it('allocation sudah_masuk_hpp tidak mengurangi laba dua kali', function () {
    [$transaction] = makeTransaction(['total_profit' => 50000]);
    $expense = makeExpense(['amount' => 30000], [
        ['transaction_id' => $transaction->id, 'amount' => 30000, 'hpp_status' => 'sudah_masuk_hpp'],
    ]);

    $summary = app(ProfitCalculationService::class)->summarizeCollections(collect([$transaction]), collect([$expense]));

    expect($summary['net_profit'])->toBe(50000.0);
});

it('allocation belum_masuk_hpp mengurangi laba tepat satu kali', function () {
    [$transaction] = makeTransaction(['total_profit' => 50000]);
    $expense = makeExpense(['amount' => 30000], [
        ['transaction_id' => $transaction->id, 'amount' => 30000, 'hpp_status' => 'belum_masuk_hpp'],
    ]);

    $summary = app(ProfitCalculationService::class)->summarizeCollections(collect([$transaction]), collect([$expense]));

    expect($summary['net_profit'])->toBe(20000.0);
});

it('sisa belum dialokasikan masuk HPP tambahan', function () {
    [$transaction] = makeTransaction(['total_profit' => 50000]);
    $expense = makeExpense(['amount' => 50000], [
        ['transaction_id' => $transaction->id, 'amount' => 20000, 'hpp_status' => 'belum_masuk_hpp'],
    ]);

    $summary = app(ProfitCalculationService::class)->summarizeCollections(collect([$transaction]), collect([$expense]));

    expect($summary['additional_hpp'])->toBe(50000.0)
        ->and($summary['net_profit'])->toBe(0.0);
});

it('edit allocation tidak membuat HPP bertambah berulang kali', function () {
    actingUser($this);
    [$transaction] = makeTransaction(['total_profit' => 100000]);
    $expense = makeExpense(['amount' => 100000], [
        ['transaction_id' => $transaction->id, 'amount' => 30000, 'hpp_status' => 'belum_masuk_hpp'],
    ]);

    $this->patch("/expenses/{$expense->id}", [
        'date' => $expense->date,
        'name' => $expense->name,
        'amount' => 100000,
        'category' => 'hpp_pesanan',
        'allocations' => [[
            'transaction_id' => $transaction->id,
            'amount' => 40000,
            'hpp_status' => 'belum_masuk_hpp',
        ]],
    ])->assertRedirect();

    $summary = app(ProfitCalculationService::class)->summarizeCollections(collect([$transaction]), collect([$expense->fresh('allocations')]));

    expect($summary['additional_hpp'])->toBe(100000.0)
        ->and(ExpenseAllocation::count())->toBe(1);
});

it('menghapus allocation memperbarui laporan dengan benar', function () {
    [$transaction] = makeTransaction(['total_profit' => 100000]);
    $expense = makeExpense(['amount' => 100000], [
        ['transaction_id' => $transaction->id, 'amount' => 40000, 'hpp_status' => 'belum_masuk_hpp'],
    ]);

    $expense->allocations()->delete();
    $summary = app(ProfitCalculationService::class)->summarizeCollections(collect([$transaction]), collect([$expense->fresh('allocations')]));

    expect($summary['additional_hpp'])->toBe(100000.0);
});

it('mengubah kategori dari hpp_pesanan menghapus allocation', function () {
    actingUser($this);
    [$transaction] = makeTransaction();
    $expense = makeExpense([], [
        ['transaction_id' => $transaction->id, 'amount' => 50000],
    ]);

    $this->patch("/expenses/{$expense->id}", [
        'date' => $expense->date,
        'name' => 'Ganti Kategori',
        'amount' => 150000,
        'category' => 'operasional_rutin',
        'allocations' => [[
            'transaction_id' => $transaction->id,
            'amount' => 50000,
            'hpp_status' => 'belum_masuk_hpp',
        ]],
    ])->assertRedirect();

    expect($expense->fresh('allocations')->allocations)->toHaveCount(0);
});

it('data expense lama berhasil dibackfill tanpa duplikat', function () {
    [$transaction] = makeTransaction();
    $migration = require database_path('migrations/2026_07_23_000002_create_expense_allocations_table.php');
    $migration->down();

    $expense = Expense::create([
        'date' => now()->toDateString(),
        'name' => 'Legacy HPP',
        'amount' => 25000,
        'category' => 'hpp_pesanan',
        'transaction_id' => $transaction->id,
        'hpp_status' => 'belum_masuk_hpp',
    ]);

    $migration->up();

    expect($expense->fresh('allocations')->allocations)->toHaveCount(1);
});

it('pembelian stok tidak langsung mengurangi laba bersih', function () {
    [$transaction] = makeTransaction(['total_profit' => 50000]);
    $expense = makeExpense(['category' => 'pembelian_stok', 'amount' => 30000], []);

    $summary = app(ProfitCalculationService::class)->summarizeCollections(collect([$transaction]), collect([$expense]));

    expect($summary['net_profit'])->toBe(50000.0);
});

it('aset peralatan tidak langsung mengurangi laba bersih', function () {
    [$transaction] = makeTransaction(['total_profit' => 50000]);
    $expense = makeExpense(['category' => 'aset_peralatan', 'amount' => 30000], []);

    $summary = app(ProfitCalculationService::class)->summarizeCollections(collect([$transaction]), collect([$expense]));

    expect($summary['net_profit'])->toBe(50000.0);
});

it('operasional rutin mengurangi laba bersih', function () {
    [$transaction] = makeTransaction(['total_profit' => 50000]);
    $expense = makeExpense(['category' => 'operasional_rutin', 'amount' => 30000], []);

    $summary = app(ProfitCalculationService::class)->summarizeCollections(collect([$transaction]), collect([$expense]));

    expect($summary['net_profit'])->toBe(20000.0);
});

it('penarikan pribadi tidak mengurangi laba usaha', function () {
    [$transaction] = makeTransaction(['total_profit' => 50000]);
    $expense = makeExpense(['category' => 'pribadi_pemilik', 'amount' => 30000], []);

    $summary = app(ProfitCalculationService::class)->summarizeCollections(collect([$transaction]), collect([$expense]));

    expect($summary['net_profit'])->toBe(50000.0);
});

it('transaksi dengan allocation tidak dapat dihapus', function () {
    actingUser($this);
    [$transaction] = makeTransaction();
    makeExpense([], [
        ['transaction_id' => $transaction->id, 'amount' => 50000],
    ]);

    $this->from('/reports')->delete("/reports/{$transaction->id}")
        ->assertRedirect('/reports')
        ->assertSessionHasErrors('error');
});
