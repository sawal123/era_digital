<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Category;
use App\Models\Product;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Models\Expense;
use App\Models\Customer;
use App\Models\StoreProfile;

class PosSeeder extends Seeder
{
    public function run(): void
    {
        // Seed default store profile
        StoreProfile::create([
            'store_name' => 'Era Digital',
            'address' => 'Jl. Raya Utama No. 45, Kebayoran Baru, Jakarta Selatan',
            'phone' => '0812-3456-7890',
            'signature_path' => null
        ]);

        // Seed some customer directory data
        Customer::create(['name' => 'Budi Santoso', 'phone' => '08122334455', 'address' => 'Jl. Merdeka No. 10, Jakarta']);
        Customer::create(['name' => 'Siti Aminah', 'phone' => '08139876543', 'address' => 'Jl. Mawar No. 4, Depok']);
        Customer::create(['name' => 'Joko Widodo', 'phone' => '08571234567', 'address' => 'Jl. Solo Baru No. 12, Surakarta']);

        // Kategori
        $catFisik = Category::firstOrCreate(['slug' => 'barang-fifisik'], ['name' => 'Barang Fisik', 'type' => 'fisik']);
        $catJasa = Category::firstOrCreate(['slug' => 'jasa-cetak'], ['name' => 'Jasa Cetak', 'type' => 'jasa']);
        $catPpob = Category::firstOrCreate(['slug' => 'saldo-digital'], ['name' => 'Saldo Digital', 'type' => 'ppob']);

        // Produk Fisik
        $p1 = Product::create(['category_id' => $catFisik->id, 'sku' => 'ATK-PLP-01', 'name' => 'Pulpen Joyko', 'unit' => 'pcs', 'base_price' => 2000, 'selling_price' => 3500, 'stock' => 100, 'min_stock' => 10]);
        $p2 = Product::create(['category_id' => $catFisik->id, 'sku' => 'ATK-BKT-01', 'name' => 'Buku Tulis 38 lembar', 'unit' => 'pcs', 'base_price' => 3500, 'selling_price' => 5500, 'stock' => 3, 'min_stock' => 10]);
        $p3 = Product::create(['category_id' => $catFisik->id, 'sku' => 'ATK-KRT-01', 'name' => 'Kertas A4 (1 rim)', 'unit' => 'rim', 'base_price' => 40000, 'selling_price' => 48000, 'stock' => 20, 'min_stock' => 5]);
        $p4 = Product::create(['category_id' => $catFisik->id, 'sku' => 'ATK-KLK-01', 'name' => 'Kalkulator Standar', 'unit' => 'pcs', 'base_price' => 15000, 'selling_price' => 25000, 'stock' => 1, 'min_stock' => 5]);

        // Fotokopi (tipe jasa, di bawah kategori Jasa Cetak)
        $p5 = Product::create(['category_id' => $catJasa->id, 'sku' => 'JSA-FTK-01', 'name' => 'Fotokopi', 'unit' => 'lembar', 'base_price' => 100, 'selling_price' => 200, 'stock' => null]);

        // Jasa Cetak
        $p6 = Product::create(['category_id' => $catJasa->id, 'sku' => 'JSA-SPD-01', 'name' => 'Spanduk Biasa', 'unit' => 'meter', 'base_price' => 15000, 'selling_price' => 25000, 'stock' => null]);
        $p7 = Product::create(['category_id' => $catJasa->id, 'sku' => 'JSA-SPD-02', 'name' => 'Spanduk Tebal', 'unit' => 'meter', 'base_price' => 18000, 'selling_price' => 35000, 'stock' => null]);
        $p8 = Product::create(['category_id' => $catJasa->id, 'sku' => 'JSA-BRS-01', 'name' => 'Brosur A4', 'unit' => 'rim', 'base_price' => 100000, 'selling_price' => 150000, 'stock' => null]);
        $p9 = Product::create(['category_id' => $catJasa->id, 'sku' => 'JSA-STK-01', 'name' => 'Stiker Vinyl', 'unit' => 'meter', 'base_price' => 30000, 'selling_price' => 50000, 'stock' => null]);

        // PPOB (Harga fluktuatif, base price 0)
        $p10 = Product::create(['category_id' => $catPpob->id, 'sku' => 'DGT-TKN-01', 'name' => 'Token Listrik Pintar', 'unit' => 'transaksi', 'base_price' => 0, 'selling_price' => 0, 'admin_fee' => 2500, 'stock' => null]);
        $p11 = Product::create(['category_id' => $catPpob->id, 'sku' => 'DGT-PLS-01', 'name' => 'Isi Pulsa All Operator', 'unit' => 'transaksi', 'base_price' => 0, 'selling_price' => 0, 'admin_fee' => 2000, 'stock' => null]);
        $p12 = Product::create(['category_id' => $catPpob->id, 'sku' => 'DGT-EWL-01', 'name' => 'Topup E-Wallet', 'unit' => 'transaksi', 'base_price' => 0, 'selling_price' => 0, 'admin_fee' => 1500, 'stock' => null]);

        // Seed customer digital accounts
        \App\Models\CustomerDigitalAccount::create([
            'customer_id' => 1,
            'type' => 'token',
            'account_number' => '14238947239',
            'account_name' => 'Budi Santoso (PLN)'
        ]);
        \App\Models\CustomerDigitalAccount::create([
            'customer_id' => 1,
            'type' => 'phone',
            'account_number' => '08122334455',
            'account_name' => 'Budi Santoso (Telkomsel)'
        ]);
        \App\Models\CustomerDigitalAccount::create([
            'customer_id' => 2,
            'type' => 'phone',
            'account_number' => '08139876543',
            'account_name' => 'Siti Aminah (Indosat)'
        ]);

        // Seed some mock expenses
        Expense::create(['date' => '2026-05-10', 'name' => 'Bayar Tagihan WiFi Biznet', 'amount' => 350000, 'category' => 'operasional', 'note' => 'Langganan WiFi internet bulanan toko']);
        Expense::create(['date' => '2026-05-12', 'name' => 'Kertas A4 HVS PaperOne 5 Rim', 'amount' => 200000, 'category' => 'stok', 'note' => 'Bahan baku fotokopi dan cetak spanduk/brosur']);
        Expense::create(['date' => '2026-05-15', 'name' => 'Token Listrik PLN Kantor', 'amount' => 100000, 'category' => 'operasional', 'note' => 'Listrik operasional mesin fotokopi']);

        // Seed some mock transactions
        $t1 = Transaction::create([
            'invoice_number' => 'TRX-20260515-001',
            'cashier_id' => 1,
            'total_base_price' => 27500,
            'total_price' => 42500,
            'total_profit' => 15000,
            'payment_method' => 'cash',
            'payment_status' => 'paid',
            'created_at' => '2026-05-15 10:20:00'
        ]);

        TransactionItem::create([
            'transaction_id' => $t1->id,
            'product_id' => $p1->id,
            'item_name' => 'Pulpen Joyko',
            'type' => 'fisik',
            'unit' => 'pcs',
            'quantity' => 2,
            'base_price' => 2000,
            'selling_price' => 3500,
            'subtotal_base' => 4000,
            'subtotal_price' => 7000,
            'profit' => 3000,
        ]);

        TransactionItem::create([
            'transaction_id' => $t1->id,
            'product_id' => $p7->id,
            'item_name' => 'Spanduk Tebal',
            'type' => 'jasa',
            'unit' => 'meter',
            'quantity' => 1,
            'base_price' => 18000,
            'selling_price' => 35500,
            'subtotal_base' => 18000,
            'subtotal_price' => 35500,
            'profit' => 17500,
        ]);

        $t2 = Transaction::create([
            'invoice_number' => 'TRX-20260516-001',
            'cashier_id' => 1,
            'total_base_price' => 50000,
            'total_price' => 52000,
            'total_profit' => 2000,
            'payment_method' => 'qris',
            'payment_status' => 'paid',
            'created_at' => '2026-05-16 14:45:00'
        ]);

        TransactionItem::create([
            'transaction_id' => $t2->id,
            'product_id' => $p10->id,
            'item_name' => 'Token Listrik Pintar (Rp 50.000)',
            'type' => 'ppob',
            'unit' => 'transaksi',
            'quantity' => 1,
            'base_price' => 50000,
            'selling_price' => 52000,
            'subtotal_base' => 50000,
            'subtotal_price' => 52000,
            'profit' => 2000,
            'metadata' => ['detail' => 'No/ID: 14238947239']
        ]);
    }
}
