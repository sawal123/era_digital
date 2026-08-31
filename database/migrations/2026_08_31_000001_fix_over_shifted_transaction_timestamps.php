<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Corrective migration — memperbaiki transaksi yang TERGESER GANDA +7 jam.
 *
 * Latar belakang:
 * Migration 2026_08_30_000001_fix_timestamps_to_wib_timezone menambah +7 jam
 * ke seluruh transactions.created_at/updated_at dan payment_histories.tanggal_bayar
 * untuk mengoreksi data era UTC. Namun sebagian transaksi LAMA sebenarnya sudah
 * pernah ditulis dalam WIB oleh aplikasi, sehingga setelah migration tersebut:
 *
 *   transactions.created_at = payment_histories.created_at + 7 jam   (SALAH)
 *
 * sedangkan transaksi baru yang benar memenuhi:
 *
 *   transactions.created_at = payment_histories.created_at            (BENAR)
 *
 * Migration ini menggeser KEMBALI -7 jam HANYA transaksi yang memenuhi pola
 * delta persis +7 jam tersebut. Deteksi berbasis DATA (relasi dengan payment
 * history pertama), bukan ID. Transaksi tanpa payment history dan transaksi
 * yang delta-nya bukan 7 jam TIDAK disentuh. payment_histories.tanggal_bayar
 * tidak diubah (sudah merepresentasikan WIB yang benar).
 */
return new class extends Migration
{
    /**
     * Deteksi +7 jam: transactions.created_at - MIN(payment_histories.created_at)
     * dalam detik. Menggunakan fungsi MySQL (TIMESTAMPDIFF) sehingga migration
     * ini sengaja di-guard MySQL-only. Logika yang sama diverifikasi di test
     * memakai SQLite (julianday delta * 86400) agar deterministik.
     */
    public function up(): void
    {
        $connection = Schema::getConnection();

        if ($connection->getDriverName() !== 'mysql') {
            return;
        }

        $diffSeconds = $this->diffSecondsExpression();

        $sql = "UPDATE transactions AS t
                INNER JOIN (
                    SELECT transaction_id, MIN(created_at) AS first_payment_created_at
                    FROM payment_histories
                    GROUP BY transaction_id
                ) AS p ON p.transaction_id = t.id
                SET t.created_at = DATE_SUB(t.created_at, INTERVAL 7 HOUR),
                    t.updated_at = DATE_SUB(t.updated_at, INTERVAL 7 HOUR)
                WHERE {$diffSeconds} = 25200";

        DB::statement($sql);
    }

    /**
     * Rollback data-fix ini sengaja NO-OP.
     *
     * Setelah up() dijalankan, delta transaksi yang dikoreksi berubah dari
     * 25200 detik menjadi 0 detik, sehingga tidak ada cara aman untuk
     * mengidentifikasi ulang transaksi mana yang sebelumnya dikoreksi
     * (delta 0 juga dimiliki transaksi baru yang memang benar). Mencoba
     * rollback heuristik berisiko menggeser transaksi yang benar.
     *
     * Koreksi data timestamp ini bersifat irreversible secara aman: schema
     * tidak berubah dan tidak ada data selain timestamp yang disentuh.
     */
    public function down(): void
    {
        // Explicit no-op — jangan menebak transaksi mana yang harus dibalik.
    }

    private function diffSecondsExpression(): string
    {
        return 'TIMESTAMPDIFF(SECOND, p.first_payment_created_at, t.created_at)';
    }
};
