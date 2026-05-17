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
        Schema::table('store_profiles', function (Blueprint $table) {
            $table->decimal('saldo_digital', 15, 2)->default(350000.00);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('store_profiles', function (Blueprint $table) {
            $table->dropColumn('saldo_digital');
        });
    }
};
