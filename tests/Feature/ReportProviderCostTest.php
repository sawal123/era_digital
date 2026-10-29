<?php

use App\Models\Transaction;
use App\Models\User;
use App\Services\ProfitCalculationService;

it('menghitung laporan PPOB memakai provider cost aktual', function () {
    $transaction = Transaction::create([
        'invoice_number' => 'REPORT-PPOB-001',
        'cashier_id' => User::factory()->create()->id,
        'total_base_price' => 100000,
        'total_price' => 102230,
        'total_profit' => 2230,
        'payment_method' => 'cash',
        'payment_status' => 'paid',
    ]);

    $transaction->items()->create([
        'item_name' => 'Pulsa 100K',
        'type' => 'ppob',
        'unit' => 'transaksi',
        'quantity' => 1,
        'base_price' => 99000,
        'provider_cost' => 99000,
        'selling_price' => 102230,
        'subtotal_base' => 99000,
        'subtotal_price' => 102230,
        'profit' => 3230,
    ]);

    $summary = app(ProfitCalculationService::class)->summarizeCollections(
        [$transaction->load('items')],
        [],
    );

    expect($summary['total_base'])->toBe(99000.0)
        ->and($summary['gross_profit'])->toBe(3230.0)
        ->and($summary['net_profit'])->toBe(3230.0);
});
