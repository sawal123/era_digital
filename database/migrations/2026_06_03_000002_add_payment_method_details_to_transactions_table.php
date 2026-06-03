<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->foreignId('payment_method_id')
                ->nullable()
                ->after('payment_method')
                ->constrained('payment_methods')
                ->nullOnDelete();
            $table->decimal('uang_diterima', 15, 2)->default(0)->after('jumlah_dibayar');
            $table->decimal('kembalian', 15, 2)->default(0)->after('uang_diterima');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('payment_method_id');
            $table->dropColumn(['uang_diterima', 'kembalian']);
        });
    }
};
