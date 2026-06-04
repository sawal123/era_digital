<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Customer;
use App\Models\CustomerDigitalAccount;
use App\Models\Expense;
use App\Models\PaymentHistory;
use App\Models\PaymentMethod;
use App\Models\PrintVendor;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\StockMovement;
use App\Models\StoreProfile;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        Schema::disableForeignKeyConstraints();

        PaymentHistory::truncate();
        TransactionItem::truncate();
        Transaction::truncate();
        StockMovement::truncate();
        Purchase::truncate();
        Expense::truncate();
        CustomerDigitalAccount::truncate();
        Customer::truncate();
        Product::truncate();
        Category::truncate();
        PaymentMethod::truncate();
        PrintVendor::truncate();
        StoreProfile::truncate();

        Schema::enableForeignKeyConstraints();

        $demoUser = User::updateOrCreate(
            ['email' => 'demo@eradigital.test'],
            [
                'name' => 'Akun Demo',
                'password' => 'password',
                'role' => 'demo',
                'email_verified_at' => now(),
            ]
        );

        User::updateOrCreate(
            ['email' => 'admin@eradigital.test'],
            [
                'name' => 'Admin Era Digital',
                'password' => 'password',
                'role' => 'admin',
                'email_verified_at' => now(),
            ]
        );

        StoreProfile::create([
            'store_name' => 'Era Digital',
            'address' => 'Jl. Raya Utama No. 45, Kebayoran Baru, Jakarta Selatan',
            'phone' => '0812-3456-7890',
            'signature_path' => null,
            'logo_path' => null,
            'saldo_digital' => 350000,
        ]);

        $cash = PaymentMethod::create(['code' => 'cash', 'name' => 'Cash / Tunai', 'is_cash' => true, 'is_active' => true, 'sort_order' => 1]);
        $qris = PaymentMethod::create(['code' => 'qris', 'name' => 'QRIS', 'is_cash' => false, 'is_active' => true, 'sort_order' => 2]);
        PaymentMethod::create(['code' => 'transfer', 'name' => 'Transfer Bank', 'is_cash' => false, 'is_active' => true, 'sort_order' => 3]);

        $vendor = PrintVendor::create([
            'name' => 'MITRA PRINT',
            'phone' => '0812-0000-7788',
            'address' => 'Jl. Percetakan No. 12, Jakarta',
            'is_active' => true,
        ]);

        $catFisik = Category::create(['slug' => 'barang-fisik', 'name' => 'Barang Fisik', 'type' => 'fisik']);
        $catJasa = Category::create(['slug' => 'jasa-cetak', 'name' => 'Jasa Cetak', 'type' => 'jasa']);
        $catPpob = Category::create(['slug' => 'saldo-digital', 'name' => 'Saldo Digital', 'type' => 'ppob']);

        $pulpen = Product::create(['category_id' => $catFisik->id, 'sku' => 'ATK-PLP-01', 'name' => 'Pulpen Joyko', 'unit' => 'pcs', 'base_price' => 2000, 'selling_price' => 3500, 'stock' => 100, 'min_stock' => 10, 'is_active' => true]);
        Product::create(['category_id' => $catFisik->id, 'sku' => 'ATK-BKT-01', 'name' => 'Buku Tulis 38 Lembar', 'unit' => 'pcs', 'base_price' => 3500, 'selling_price' => 5500, 'stock' => 20, 'min_stock' => 10, 'is_active' => true]);
        Product::create(['category_id' => $catFisik->id, 'sku' => 'ATK-KRT-01', 'name' => 'Kertas A4 1 Rim', 'unit' => 'rim', 'base_price' => 40000, 'selling_price' => 48000, 'stock' => 12, 'min_stock' => 5, 'is_active' => true]);
        Product::create(['category_id' => $catFisik->id, 'sku' => 'ATK-KLK-01', 'name' => 'Kalkulator Standar', 'unit' => 'pcs', 'base_price' => 15000, 'selling_price' => 25000, 'stock' => 6, 'min_stock' => 5, 'is_active' => true]);

        Product::create(['category_id' => $catJasa->id, 'sku' => 'JSA-FTK-01', 'name' => 'Fotokopi', 'unit' => 'lembar', 'base_price' => 100, 'selling_price' => 200, 'stock' => null, 'is_active' => true]);
        Product::create(['category_id' => $catJasa->id, 'sku' => 'JSA-SPD-01', 'name' => 'Spanduk Biasa', 'unit' => 'meter', 'base_price' => 15000, 'selling_price' => 25000, 'stock' => null, 'is_active' => true]);
        $spandukTebal = Product::create(['category_id' => $catJasa->id, 'sku' => 'JSA-SPD-02', 'name' => 'Spanduk Tebal', 'unit' => 'meter', 'base_price' => 18000, 'selling_price' => 35000, 'stock' => null, 'is_active' => true]);
        Product::create(['category_id' => $catJasa->id, 'sku' => 'JSA-BRS-01', 'name' => 'Brosur A4', 'unit' => 'rim', 'base_price' => 100000, 'selling_price' => 150000, 'stock' => null, 'is_active' => true]);
        Product::create(['category_id' => $catJasa->id, 'sku' => 'JSA-STK-01', 'name' => 'Stiker Vinyl', 'unit' => 'meter', 'base_price' => 30000, 'selling_price' => 50000, 'stock' => null, 'is_active' => true]);

        $token = Product::create(['category_id' => $catPpob->id, 'sku' => 'DGT-TKN-01', 'name' => 'Token Listrik Pintar', 'unit' => 'transaksi', 'base_price' => 0, 'selling_price' => 0, 'admin_fee' => 2500, 'stock' => null, 'is_active' => true]);
        Product::create(['category_id' => $catPpob->id, 'sku' => 'DGT-PLS-01', 'name' => 'Isi Pulsa All Operator', 'unit' => 'transaksi', 'base_price' => 0, 'selling_price' => 0, 'admin_fee' => 2000, 'stock' => null, 'is_active' => true]);
        Product::create(['category_id' => $catPpob->id, 'sku' => 'DGT-EWL-01', 'name' => 'Topup E-Wallet', 'unit' => 'transaksi', 'base_price' => 0, 'selling_price' => 0, 'admin_fee' => 1500, 'stock' => null, 'is_active' => true]);

        $budi = Customer::create(['name' => 'Budi Santoso', 'phone' => '08122334455', 'address' => 'Jl. Merdeka No. 10, Jakarta', 'customer_type' => 'regular']);
        $siti = Customer::create(['name' => 'Siti Aminah', 'phone' => '08139876543', 'address' => 'Jl. Mawar No. 4, Depok', 'customer_type' => 'regular']);
        Customer::create(['name' => 'Andi Pratama', 'phone' => '08571234567', 'address' => 'Jl. Sudirman No. 22, Bekasi', 'customer_type' => 'reseller']);

        CustomerDigitalAccount::create(['customer_id' => $budi->id, 'type' => 'token', 'account_number' => '14238947239', 'account_name' => 'Budi Santoso (PLN)']);
        CustomerDigitalAccount::create(['customer_id' => $budi->id, 'type' => 'phone', 'account_number' => '08122334455', 'account_name' => 'Budi Santoso (Telkomsel)']);
        CustomerDigitalAccount::create(['customer_id' => $siti->id, 'type' => 'phone', 'account_number' => '08139876543', 'account_name' => 'Siti Aminah (Indosat)']);

        Purchase::create(['product_id' => $pulpen->id, 'quantity' => 50, 'cost_price' => 2000, 'total_price' => 100000, 'purchase_date' => '2026-06-01', 'note' => 'Stok awal demo']);
        StockMovement::create(['product_id' => $pulpen->id, 'type' => 'in', 'quantity' => 50, 'reference_id' => 1, 'note' => 'Stok awal demo']);

        Expense::create(['date' => '2026-06-01', 'name' => 'Bayar Tagihan WiFi', 'amount' => 350000, 'category' => 'operasional', 'note' => 'Biaya operasional demo']);
        Expense::create(['date' => '2026-06-02', 'name' => 'Kertas A4 HVS 5 Rim', 'amount' => 200000, 'category' => 'stok', 'note' => 'Bahan baku demo']);

        $transaction = Transaction::create([
            'invoice_number' => 'TRX-DEMO-0001',
            'cashier_id' => $demoUser->id,
            'customer_id' => $budi->id,
            'customer_name' => $budi->name,
            'customer_phone' => $budi->phone,
            'total_base_price' => 22000,
            'total_price' => 42500,
            'total_profit' => 20500,
            'payment_method' => $cash->code,
            'payment_method_id' => $cash->id,
            'payment_status' => 'paid',
            'status_bayar' => 'lunas',
            'jumlah_dibayar' => 42500,
            'uang_diterima' => 50000,
            'kembalian' => 7500,
            'sisa_tagihan' => 0,
            'created_at' => '2026-06-03 10:20:00',
        ]);

        TransactionItem::create(['transaction_id' => $transaction->id, 'product_id' => $pulpen->id, 'item_name' => 'Pulpen Joyko', 'type' => 'fisik', 'unit' => 'pcs', 'quantity' => 2, 'base_price' => 2000, 'selling_price' => 3500, 'subtotal_base' => 4000, 'subtotal_price' => 7000, 'profit' => 3000]);
        TransactionItem::create(['transaction_id' => $transaction->id, 'product_id' => $spandukTebal->id, 'print_vendor_id' => $vendor->id, 'item_name' => 'Spanduk Tebal', 'type' => 'jasa', 'unit' => 'meter', 'quantity' => 1, 'base_price' => 18000, 'selling_price' => 35500, 'subtotal_base' => 18000, 'subtotal_price' => 35500, 'profit' => 17500, 'service_status' => 'diproses_mitra']);

        $ppobTransaction = Transaction::create([
            'invoice_number' => 'TRX-DEMO-0002',
            'cashier_id' => $demoUser->id,
            'customer_id' => $siti->id,
            'customer_name' => $siti->name,
            'customer_phone' => $siti->phone,
            'total_base_price' => 50000,
            'total_price' => 52500,
            'total_profit' => 2500,
            'payment_method' => $qris->code,
            'payment_method_id' => $qris->id,
            'payment_status' => 'paid',
            'status_bayar' => 'lunas',
            'jumlah_dibayar' => 52500,
            'sisa_tagihan' => 0,
            'created_at' => '2026-06-03 14:45:00',
        ]);

        TransactionItem::create(['transaction_id' => $ppobTransaction->id, 'product_id' => $token->id, 'item_name' => 'Token Listrik Pintar (Rp 50.000)', 'type' => 'ppob', 'unit' => 'transaksi', 'quantity' => 1, 'base_price' => 50000, 'selling_price' => 52500, 'subtotal_base' => 50000, 'subtotal_price' => 52500, 'profit' => 2500, 'metadata' => ['detail' => 'No/ID: 14238947239']]);
    }
}
