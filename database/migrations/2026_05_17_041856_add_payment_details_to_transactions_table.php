<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->enum('status_bayar', ['lunas', 'dp', 'piutang'])->default('lunas')->after('payment_status');
            $table->decimal('jumlah_dibayar', 15, 2)->default(0)->after('status_bayar');
            $table->decimal('sisa_tagihan', 15, 2)->default(0)->after('jumlah_dibayar');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn(['status_bayar', 'jumlah_dibayar', 'sisa_tagihan']);
        });
    }
};
