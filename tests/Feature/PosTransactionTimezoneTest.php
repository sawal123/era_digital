<?php

use App\Models\Category;
use App\Models\PaymentHistory;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Transaction;
use App\Models\User;
use App\Services\ProfitCalculationService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Regression test khusus timezone transaksi POS (WIB, UTC+7).
 *
 * Konfigurasi yang diuji/diasumsikan:
 *   APP_TIMEZONE=Asia/Jakarta
 *   DB_TIMEZONE=+07:00
 *
 * Source of truth = timestamp di database (WIB). Frontend tidak boleh
 * melakukan konversi timezone tambahan.
 */
function wibTimezoneCategory(): Category
{
    return Category::firstOrCreate(
        ['slug' => 'jasa-cetak'],
        ['name' => 'Jasa Cetak', 'type' => 'jasa']
    );
}

function wibTimezoneProduct(): Product
{
    return Product::create([
        'category_id' => wibTimezoneCategory()->id,
        'sku' => 'TZ-'.fake()->unique()->numerify('####'),
        'name' => 'Produk Timezone Test',
        'unit' => 'pcs',
        'base_price' => 1000,
        'selling_price' => 2000,
        'stock' => 10,
        'is_active' => true,
    ]);
}

function wibTimezoneCashMethod(): PaymentMethod
{
    return PaymentMethod::firstOrCreate(
        ['code' => 'cash'],
        ['name' => 'Cash / Tunai', 'is_cash' => true, 'is_active' => true, 'sort_order' => 1]
    );
}

/**
 * Simulasi transaksi lama yang sudah pernah digeser +7 jam oleh migration
 * 2026_08_30_000001_fix_timestamps_to_wib_timezone (SALAH).
 *
 * transactions.created_at = payment_histories.created_at + 7 jam
 *
 * CATATAN: data lama di-insert RAW via query builder karena Eloquent
 * menormalkan Carbon ke UTC saat menyimpan — di database produksi timestamp
 * sudah tersimpan apa adanya (WIB), sehingga simulasi harus mereplikasi nilai
 * DB mentah, bukan lewat Eloquent.
 */
function makeOverShiftedTransaction(Carbon $paymentCreatedAt, ?int $cashierId = null): Transaction
{
    $cashierId ??= User::factory()->create()->id;

    $transactionId = DB::table('transactions')->insertGetId([
        'invoice_number' => 'TRX-TZ-OLD-'.fake()->unique()->numerify('####'),
        'cashier_id' => $cashierId,
        'customer_name' => 'Customer Lama',
        'total_base_price' => 1000,
        'total_price' => 2000,
        'total_profit' => 1000,
        'payment_method' => 'cash',
        'payment_method_id' => wibTimezoneCashMethod()->id,
        'payment_status' => 'paid',
        'status_bayar' => 'lunas',
        'jumlah_dibayar' => 2000,
        'uang_diterima' => 2000,
        'kembalian' => 0,
        'sisa_tagihan' => 0,
        'created_at' => $paymentCreatedAt->copy()->addHours(7)->format('Y-m-d H:i:s'),
        'updated_at' => $paymentCreatedAt->copy()->addHours(7)->format('Y-m-d H:i:s'),
    ]);

    DB::table('payment_histories')->insert([
        'transaction_id' => $transactionId,
        'jumlah_bayar' => 2000,
        'tanggal_bayar' => $paymentCreatedAt->format('Y-m-d H:i:s'),
        'metode_bayar' => 'cash',
        'keterangan' => 'Pembayaran Lunas',
        'created_at' => $paymentCreatedAt->format('Y-m-d H:i:s'),
        'updated_at' => $paymentCreatedAt->format('Y-m-d H:i:s'),
    ]);

    return Transaction::findOrFail($transactionId);
}

/**
 * Simulasi transaksi baru/benar:
 * transactions.created_at = payment_histories.created_at (delta 0 jam).
 * Di-insert RAW dengan nilai DB mentah (WIB), lihat catatan helper di atas.
 */
function makeCorrectTransaction(Carbon $createdAt, ?int $cashierId = null): Transaction
{
    $cashierId ??= User::factory()->create()->id;

    $transactionId = DB::table('transactions')->insertGetId([
        'invoice_number' => 'TRX-TZ-NEW-'.fake()->unique()->numerify('####'),
        'cashier_id' => $cashierId,
        'customer_name' => 'Customer Baru',
        'total_base_price' => 1000,
        'total_price' => 2000,
        'total_profit' => 1000,
        'payment_method' => 'cash',
        'payment_method_id' => wibTimezoneCashMethod()->id,
        'payment_status' => 'paid',
        'status_bayar' => 'lunas',
        'jumlah_dibayar' => 2000,
        'uang_diterima' => 2000,
        'kembalian' => 0,
        'sisa_tagihan' => 0,
        'created_at' => $createdAt->format('Y-m-d H:i:s'),
        'updated_at' => $createdAt->format('Y-m-d H:i:s'),
    ]);

    DB::table('payment_histories')->insert([
        'transaction_id' => $transactionId,
        'jumlah_bayar' => 2000,
        'tanggal_bayar' => $createdAt->format('Y-m-d H:i:s'),
        'metode_bayar' => 'cash',
        'keterangan' => 'Pembayaran Lunas',
        'created_at' => $createdAt->format('Y-m-d H:i:s'),
        'updated_at' => $createdAt->format('Y-m-d H:i:s'),
    ]);

    return Transaction::findOrFail($transactionId);
}

// ---------------------------------------------------------------------------
// Corrective migration: deteksi delta +7 jam vs delta 0 jam
// ---------------------------------------------------------------------------

it('corrective migration hanya menggeser transaksi dengan delta +7 jam', function () {
    Carbon::setTestNow(Carbon::parse('2026-08-30 12:00:00', 'Asia/Jakarta'));

    $oldWrong = makeOverShiftedTransaction(Carbon::parse('2026-08-30 12:00:00', 'Asia/Jakarta'));
    $correct = makeCorrectTransaction(Carbon::parse('2026-08-30 12:00:00', 'Asia/Jakarta'));

    // Transaksi tanpa payment history tidak boleh rusak.
    $noHistoryId = DB::table('transactions')->insertGetId([
        'invoice_number' => 'TRX-TZ-NOHIST-'.fake()->unique()->numerify('####'),
        'cashier_id' => User::factory()->create()->id,
        'customer_name' => 'Tanpa Riwayat',
        'total_base_price' => 0,
        'total_price' => 0,
        'total_profit' => 0,
        'payment_method' => 'cash',
        'payment_method_id' => wibTimezoneCashMethod()->id,
        'payment_status' => 'unpaid',
        'status_bayar' => 'piutang',
        'jumlah_dibayar' => 0,
        'uang_diterima' => 0,
        'kembalian' => 0,
        'sisa_tagihan' => 0,
        'created_at' => '2026-08-30 12:00:00',
        'updated_at' => '2026-08-30 12:00:00',
    ]);

    // Jalankan koreksi yang sama dengan SQL corrective migration.
    // Di MySQL ekspresi diff = TIMESTAMPDIFF(SECOND, p.first_payment_created_at, t.created_at)
    // SQLite: (julianday(a) - julianday(b)) * 86400 menghasilkan nilai detik identik
    // (dibulatkan karena julianday menyimpan pecahan).
    $diffExpression = 'CAST(ROUND((julianday(t.created_at) - julianday(p.first_payment_created_at)) * 86400) AS INTEGER)';

    $correctedIds = DB::table('transactions as t')
        ->join(
            DB::raw('(SELECT transaction_id, MIN(created_at) AS first_payment_created_at FROM payment_histories GROUP BY transaction_id) as p'),
            'p.transaction_id',
            '=',
            't.id'
        )
        ->whereRaw("{$diffExpression} = 25200")
        ->pluck('t.id');

    expect($correctedIds->toArray())->toContain($oldWrong->id)
        ->and($correctedIds->toArray())->not->toContain($correct->id)
        ->and($correctedIds->toArray())->not->toContain($noHistoryId);

    // Eksekusi koreksi identik dengan migration.
    DB::table('transactions as t')
        ->join(
            DB::raw('(SELECT transaction_id, MIN(created_at) AS first_payment_created_at FROM payment_histories GROUP BY transaction_id) as p'),
            'p.transaction_id',
            '=',
            't.id'
        )
        ->whereRaw("{$diffExpression} = 25200")
        ->update([
            't.created_at' => DB::raw("datetime(t.created_at, '-7 hours')"),
            't.updated_at' => DB::raw("datetime(t.updated_at, '-7 hours')"),
        ]);

    $oldWrong->refresh();
    $correct->refresh();

    // Transaksi lama: created_at harus kembali = payment history pertama (WIB).
    $firstPayment = PaymentHistory::where('transaction_id', $oldWrong->id)->orderBy('created_at')->first();
    expect($oldWrong->created_at->format('Y-m-d H:i:s'))
        ->toBe($firstPayment->created_at->format('Y-m-d H:i:s'))
        ->and($oldWrong->created_at->format('Y-m-d H:i:s'))
        ->toBe('2026-08-30 12:00:00');

    // Transaksi benar: TIDAK berubah sama sekali.
    expect($correct->created_at->format('Y-m-d H:i:s'))->toBe('2026-08-30 12:00:00');

    // Transaksi tanpa payment history: TIDAK berubah.
    expect(DB::table('transactions')->where('id', $noHistoryId)->value('created_at'))->toBe('2026-08-30 12:00:00');

    // payment_histories.tanggal_bayar TIDAK disentuh.
    $payment = PaymentHistory::where('transaction_id', $oldWrong->id)->first();
    expect($payment->tanggal_bayar->format('Y-m-d H:i:s'))->toBe('2026-08-30 12:00:00');

    Carbon::setTestNow();
});

it('corrective migration aman dijalankan dua kali (idempotent)', function () {
    Carbon::setTestNow(Carbon::parse('2026-08-30 20:00:00', 'Asia/Jakarta'));

    $oldWrong = makeOverShiftedTransaction(Carbon::parse('2026-08-30 20:00:00', 'Asia/Jakarta'));

    $diffExpression = 'CAST(ROUND((julianday(t.created_at) - julianday(p.first_payment_created_at)) * 86400) AS INTEGER)';

    $applyCorrection = function () use ($diffExpression) {
        DB::table('transactions as t')
            ->join(
                DB::raw('(SELECT transaction_id, MIN(created_at) AS first_payment_created_at FROM payment_histories GROUP BY transaction_id) as p'),
                'p.transaction_id',
                '=',
                't.id'
            )
            ->whereRaw("{$diffExpression} = 25200")
            ->update([
                't.created_at' => DB::raw("datetime(t.created_at, '-7 hours')"),
                't.updated_at' => DB::raw("datetime(t.updated_at, '-7 hours')"),
            ]);
    };

    // Jalankan dua kali seperti migrate berulang.
    $applyCorrection();
    $applyCorrection();

    $oldWrong->refresh();
    $firstPayment = PaymentHistory::where('transaction_id', $oldWrong->id)->orderBy('created_at')->first();

    // Setelah koreksi, delta = 0 -> run kedua tidak mengubah apa-apa.
    expect($oldWrong->created_at->format('Y-m-d H:i:s'))
        ->toBe($firstPayment->created_at->format('Y-m-d H:i:s'))
        ->and($oldWrong->created_at->format('Y-m-d H:i:s'))
        ->toBe('2026-08-30 20:00:00');

    Carbon::setTestNow();
});

it('corrective migration tidak melakukan apa pun jika tidak ada delta +7 jam', function () {
    Carbon::setTestNow(Carbon::parse('2026-08-30 10:00:00', 'Asia/Jakarta'));

    makeCorrectTransaction(Carbon::parse('2026-08-30 10:00:00', 'Asia/Jakarta'));

    $before = Transaction::with('paymentHistories')->get()->map(fn ($t) => [
        'created_at' => $t->created_at->format('Y-m-d H:i:s'),
        'updated_at' => $t->updated_at->format('Y-m-d H:i:s'),
        'first_payment_created_at' => $t->paymentHistories->sortBy('created_at')->first()?->created_at->format('Y-m-d H:i:s'),
    ])->toArray();

    $diffExpression = 'CAST(ROUND((julianday(t.created_at) - julianday(p.first_payment_created_at)) * 86400) AS INTEGER)';

    $affected = DB::table('transactions as t')
        ->join(
            DB::raw('(SELECT transaction_id, MIN(created_at) AS first_payment_created_at FROM payment_histories GROUP BY transaction_id) as p'),
            'p.transaction_id',
            '=',
            't.id'
        )
        ->whereRaw("{$diffExpression} = 25200")
        ->update([
            't.created_at' => DB::raw("datetime(t.created_at, '-7 hours')"),
            't.updated_at' => DB::raw("datetime(t.updated_at, '-7 hours')"),
        ]);

    expect($affected)->toBe(0);

    $after = Transaction::with('paymentHistories')->get()->map(fn ($t) => [
        'created_at' => $t->created_at->format('Y-m-d H:i:s'),
        'updated_at' => $t->updated_at->format('Y-m-d H:i:s'),
        'first_payment_created_at' => $t->paymentHistories->sortBy('created_at')->first()?->created_at->format('Y-m-d H:i:s'),
    ])->toArray();

    expect($after)->toBe($before);

    Carbon::setTestNow();
});

it('migration corrective hanya mengeksekusi SQL di driver mysql (no-op di driver lain)', function () {
    // Seed satu transaksi tanpa payment history: tidak boleh tersentuh oleh driver mana pun.
    $noHistoryId = DB::table('transactions')->insertGetId([
        'invoice_number' => 'TRX-TZ-DRIVER-'.fake()->unique()->numerify('####'),
        'cashier_id' => User::factory()->create()->id,
        'customer_name' => 'Driver Check',
        'total_base_price' => 0,
        'total_price' => 0,
        'total_profit' => 0,
        'payment_method' => 'cash',
        'payment_method_id' => wibTimezoneCashMethod()->id,
        'payment_status' => 'unpaid',
        'status_bayar' => 'piutang',
        'jumlah_dibayar' => 0,
        'uang_diterima' => 0,
        'kembalian' => 0,
        'sisa_tagihan' => 0,
        'created_at' => '2026-08-30 12:00:00',
        'updated_at' => '2026-08-30 12:00:00',
    ]);

    $migration = require database_path('migrations/2026_08_31_000001_fix_over_shifted_transaction_timestamps.php');
    $migration->up();

    // Tidak error; transaksi tanpa payment history tidak berubah di driver mana pun.
    expect(DB::table('transactions')->where('id', $noHistoryId)->value('created_at'))->toBe('2026-08-30 12:00:00');
});

// ---------------------------------------------------------------------------
// POS store: tanggal transaksi & invoice number berbasis WIB
// ---------------------------------------------------------------------------

it('transaksi pukul 23:30 WIB tetap masuk tanggal 30 Agustus dan invoice TRX-20260830', function () {
    Carbon::setTestNow(Carbon::parse('2026-08-30 23:30:00', 'Asia/Jakarta'));

    $this->actingAs(User::factory()->create());
    $product = wibTimezoneProduct();

    $this->from('/pos')->post('/pos', [
        'cart' => [[
            'id' => $product->id,
            'name' => $product->name,
            'price' => 2000,
            'quantity' => 1,
            'type' => 'fisik',
        ]],
        'total' => 2000,
        'payment_method_id' => wibTimezoneCashMethod()->id,
        'uang_diterima' => 2000,
        'customer_id' => null,
        'invoice_customer_name' => null,
        'invoice_customer_phone' => null,
        'keterangan' => null,
    ])->assertRedirect();

    $transaction = Transaction::first();
    expect($transaction)->not->toBeNull();

    // Waktu transaksi sesuai WIB (tidak bergeser ke hari berikutnya).
    expect($transaction->created_at->format('Y-m-d H:i:s'))->toBe('2026-08-30 23:30:00')
        ->and($transaction->created_at->toDateString())->toBe('2026-08-30');

    // Nomor invoice memakai tanggal WIB: TRX-YYYYMMDD-XXX
    expect($transaction->invoice_number)->toMatch('/^TRX-20260830-\d{3}$/');

    Carbon::setTestNow();
});

it('transaksi pukul 00:30 WIB masuk tanggal 31 Agustus dan invoice TRX-20260831', function () {
    Carbon::setTestNow(Carbon::parse('2026-08-31 00:30:00', 'Asia/Jakarta'));

    $this->actingAs(User::factory()->create());
    $product = wibTimezoneProduct();

    $this->from('/pos')->post('/pos', [
        'cart' => [[
            'id' => $product->id,
            'name' => $product->name,
            'price' => 2000,
            'quantity' => 1,
            'type' => 'fisik',
        ]],
        'total' => 2000,
        'payment_method_id' => wibTimezoneCashMethod()->id,
        'uang_diterima' => 2000,
        'customer_id' => null,
        'invoice_customer_name' => null,
        'invoice_customer_phone' => null,
        'keterangan' => null,
    ])->assertRedirect();

    $transaction = Transaction::first();
    expect($transaction)->not->toBeNull();

    expect($transaction->created_at->format('Y-m-d H:i:s'))->toBe('2026-08-31 00:30:00')
        ->and($transaction->created_at->toDateString())->toBe('2026-08-31')
        ->and($transaction->invoice_number)->toMatch('/^TRX-20260831-\d{3}$/');

    Carbon::setTestNow();
});

it('invoice number unik per hari WIB (23:30 dan 00:30 adalah hari berbeda)', function () {
    Carbon::setTestNow(Carbon::parse('2026-08-30 23:30:00', 'Asia/Jakarta'));
    $this->actingAs(User::factory()->create());
    $product = wibTimezoneProduct();

    $this->from('/pos')->post('/pos', [
        'cart' => [[
            'id' => $product->id,
            'name' => $product->name,
            'price' => 2000,
            'quantity' => 1,
            'type' => 'fisik',
        ]],
        'total' => 2000,
        'payment_method_id' => wibTimezoneCashMethod()->id,
        'uang_diterima' => 2000,
    ])->assertRedirect();

    $first = Transaction::first();
    expect($first->invoice_number)->toMatch('/^TRX-20260830-\d{3}$/');

    // Pindah ke hari berikutnya di WIB.
    Carbon::setTestNow(Carbon::parse('2026-08-31 00:30:00', 'Asia/Jakarta'));

    $this->from('/pos')->post('/pos', [
        'cart' => [[
            'id' => $product->id,
            'name' => $product->name,
            'price' => 2000,
            'quantity' => 1,
            'type' => 'fisik',
        ]],
        'total' => 2000,
        'payment_method_id' => wibTimezoneCashMethod()->id,
        'uang_diterima' => 2000,
    ])->assertRedirect();

    $second = Transaction::orderBy('id', 'desc')->first();
    expect($second->invoice_number)->toMatch('/^TRX-20260831-\d{3}$/')
        ->and($second->invoice_number)->not->toBe($first->invoice_number);

    Carbon::setTestNow();
});

it('payment history pertama dibuat bersamaan dengan transaksi (delta 0 jam untuk transaksi baru)', function () {
    Carbon::setTestNow(Carbon::parse('2026-08-31 12:00:00', 'Asia/Jakarta'));

    $this->actingAs(User::factory()->create());
    $product = wibTimezoneProduct();

    $this->from('/pos')->post('/pos', [
        'cart' => [[
            'id' => $product->id,
            'name' => $product->name,
            'price' => 2000,
            'quantity' => 1,
            'type' => 'fisik',
        ]],
        'total' => 2000,
        'payment_method_id' => wibTimezoneCashMethod()->id,
        'uang_diterima' => 2000,
    ])->assertRedirect();

    $transaction = Transaction::first();
    $firstPayment = PaymentHistory::where('transaction_id', $transaction->id)->orderBy('created_at')->first();

    expect($transaction->created_at->format('Y-m-d H:i:s'))
        ->toBe($firstPayment->created_at->format('Y-m-d H:i:s'))
        ->and($transaction->created_at->format('Y-m-d H:i:s'))->toBe('2026-08-31 12:00:00');

    Carbon::setTestNow();
});

// ---------------------------------------------------------------------------
// Dashboard & laporan: whereDate menghasilkan tanggal bisnis WIB yang benar
// ---------------------------------------------------------------------------

it('whereDate created_at hari ini mencakup transaksi 23:30 WIB kemarin malam sesuai tanggal WIB', function () {
    Carbon::setTestNow(Carbon::parse('2026-08-30 23:30:00', 'Asia/Jakarta'));

    $this->actingAs(User::factory()->create());
    $product = wibTimezoneProduct();

    $this->from('/pos')->post('/pos', [
        'cart' => [[
            'id' => $product->id,
            'name' => $product->name,
            'price' => 2000,
            'quantity' => 1,
            'type' => 'fisik',
        ]],
        'total' => 2000,
        'payment_method_id' => wibTimezoneCashMethod()->id,
        'uang_diterima' => 2000,
    ])->assertRedirect();

    // Simulasi laporan tanggal 30 Agustus: transaksi 23:30 WIB harus muncul.
    $today = now()->toDateString(); // 2026-08-30 dalam WIB
    $count = Transaction::whereDate('created_at', $today)->count();

    expect($today)->toBe('2026-08-30')
        ->and($count)->toBe(1);

    Carbon::setTestNow();
});

it('whereDate created_at memisahkan transaksi 23:30 dan 00:30 ke hari WIB yang berbeda', function () {
    Carbon::setTestNow(Carbon::parse('2026-08-30 23:30:00', 'Asia/Jakarta'));
    $this->actingAs(User::factory()->create());
    $product = wibTimezoneProduct();

    $this->from('/pos')->post('/pos', [
        'cart' => [[
            'id' => $product->id,
            'name' => $product->name,
            'price' => 2000,
            'quantity' => 1,
            'type' => 'fisik',
        ]],
        'total' => 2000,
        'payment_method_id' => wibTimezoneCashMethod()->id,
        'uang_diterima' => 2000,
    ])->assertRedirect();

    Carbon::setTestNow(Carbon::parse('2026-08-31 00:30:00', 'Asia/Jakarta'));
    $this->from('/pos')->post('/pos', [
        'cart' => [[
            'id' => $product->id,
            'name' => $product->name,
            'price' => 2000,
            'quantity' => 1,
            'type' => 'fisik',
        ]],
        'total' => 2000,
        'payment_method_id' => wibTimezoneCashMethod()->id,
        'uang_diterima' => 2000,
    ])->assertRedirect();

    expect(Transaction::whereDate('created_at', '2026-08-30')->count())->toBe(1)
        ->and(Transaction::whereDate('created_at', '2026-08-31')->count())->toBe(1);

    Carbon::setTestNow();
});

it('ProfitCalculationService summarizePeriod memakai tanggal WIB (bukan geser 7 jam)', function () {
    $service = new ProfitCalculationService;

    Carbon::setTestNow(Carbon::parse('2026-08-30 23:30:00', 'Asia/Jakarta'));
    $this->actingAs(User::factory()->create());
    $product = wibTimezoneProduct();

    $this->from('/pos')->post('/pos', [
        'cart' => [[
            'id' => $product->id,
            'name' => $product->name,
            'price' => 2000,
            'quantity' => 1,
            'type' => 'fisik',
        ]],
        'total' => 2000,
        'payment_method_id' => wibTimezoneCashMethod()->id,
        'uang_diterima' => 2000,
    ])->assertRedirect();

    Carbon::setTestNow(Carbon::parse('2026-08-31 00:30:00', 'Asia/Jakarta'));
    $this->from('/pos')->post('/pos', [
        'cart' => [[
            'id' => $product->id,
            'name' => $product->name,
            'price' => 2000,
            'quantity' => 1,
            'type' => 'fisik',
        ]],
        'total' => 2000,
        'payment_method_id' => wibTimezoneCashMethod()->id,
        'uang_diterima' => 2000,
    ])->assertRedirect();

    Carbon::setTestNow(Carbon::parse('2026-08-31 08:00:00', 'Asia/Jakarta'));

    $summary30 = $service->summarizePeriod('2026-08-30');
    $summary31 = $service->summarizePeriod('2026-08-31');

    // Transaksi 23:30 WIB masuk laporan 30 Agustus, transaksi 00:30 WIB masuk 31 Agustus.
    expect($summary30['transaction_count'])->toBe(1)
        ->and($summary31['transaction_count'])->toBe(1)
        ->and($summary30['total_sales'])->toBe(2000.0)
        ->and($summary31['total_sales'])->toBe(2000.0);

    Carbon::setTestNow();
});

it('Dashboard index menampilkan statistik hari WIB yang benar', function () {
    Carbon::setTestNow(Carbon::parse('2026-08-30 23:30:00', 'Asia/Jakarta'));
    $this->actingAs(User::factory()->create());
    $product = wibTimezoneProduct();

    $this->from('/pos')->post('/pos', [
        'cart' => [[
            'id' => $product->id,
            'name' => $product->name,
            'price' => 2000,
            'quantity' => 1,
            'type' => 'fisik',
        ]],
        'total' => 2000,
        'payment_method_id' => wibTimezoneCashMethod()->id,
        'uang_diterima' => 2000,
    ])->assertRedirect();

    Carbon::setTestNow(Carbon::parse('2026-08-31 00:30:00', 'Asia/Jakarta'));
    $this->from('/pos')->post('/pos', [
        'cart' => [[
            'id' => $product->id,
            'name' => $product->name,
            'price' => 2000,
            'quantity' => 1,
            'type' => 'fisik',
        ]],
        'total' => 2000,
        'payment_method_id' => wibTimezoneCashMethod()->id,
        'uang_diterima' => 2000,
    ])->assertRedirect();

    // Dashboard diakses pada 31 Agustus: hanya transaksi 00:30 WIB yang masuk "hari ini".
    Carbon::setTestNow(Carbon::parse('2026-08-31 09:00:00', 'Asia/Jakarta'));

    $this->get('/dashboard')->assertOk()->assertInertia(fn ($page) => $page
        ->component('Dashboard')
        ->where('stats.total_omset', 2000)
        ->where('stats.total_modal', 1000)
        ->where('stats.keuntungan_bersih', 1000));

    Carbon::setTestNow();
});

it('config timezone tetap Asia/Jakarta dan DB timezone +07:00', function () {
    expect(config('app.timezone'))->toBe('Asia/Jakarta')
        ->and(config('database.connections.mysql.timezone'))->toBe('+07:00');
});
