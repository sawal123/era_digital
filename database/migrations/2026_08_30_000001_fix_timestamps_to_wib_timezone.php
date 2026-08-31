<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Sebelumnya PHP berjalan di UTC (config app.timezone = UTC) sementara
     * MySQL session timezone adalah SYSTEM/WIB. Akibatnya nilai timestamp
     * yang ditulis Eloquent (UTC) diinterpretasikan MySQL sebagai WIB dan
     * disimpan bergeser -7 jam. Setelah APP_TIMEZONE=Asia/Jakarta dan
     * DB_TIMEZONE=+07:00, data lama perlu digeser +offset agar konsisten.
     */
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        $offsetMinutes = $this->wibOffsetMinutes();

        DB::statement("UPDATE transactions SET created_at = DATE_ADD(created_at, INTERVAL {$offsetMinutes} MINUTE), updated_at = DATE_ADD(updated_at, INTERVAL {$offsetMinutes} MINUTE)");
        DB::statement("UPDATE payment_histories SET tanggal_bayar = DATE_ADD(tanggal_bayar, INTERVAL {$offsetMinutes} MINUTE)");
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        $offsetMinutes = $this->wibOffsetMinutes();

        DB::statement("UPDATE transactions SET created_at = DATE_SUB(created_at, INTERVAL {$offsetMinutes} MINUTE), updated_at = DATE_SUB(updated_at, INTERVAL {$offsetMinutes} MINUTE)");
        DB::statement("UPDATE payment_histories SET tanggal_bayar = DATE_SUB(tanggal_bayar, INTERVAL {$offsetMinutes} MINUTE)");
    }

    /**
     * Offset menit dari DB_TIMEZONE (default +07:00 / WIB).
     */
    private function wibOffsetMinutes(): int
    {
        $timezone = (string) config('database.connections.mysql.timezone', '+07:00');
        $minutes = 7 * 60;

        if (preg_match('/^([+-])(\d{2}):?(\d{2})$/', $timezone, $m)) {
            $total = ((int) $m[2] * 60) + (int) $m[3];
            $minutes = $m[1] === '-' ? -$total : $total;
        }

        return $minutes;
    }
};
