<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            if (!Schema::hasColumn('expenses', 'transaction_id')) {
                $table->foreignId('transaction_id')
                    ->nullable()
                    ->after('category')
                    ->constrained('transactions')
                    ->nullOnDelete();
            }

            if (!Schema::hasColumn('expenses', 'hpp_status')) {
                $table->string('hpp_status', 40)
                    ->default('not_applicable')
                    ->after('transaction_id');
            }
        });

        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE expenses MODIFY category VARCHAR(40) NOT NULL DEFAULT 'operasional_rutin'");
        } elseif ($driver === 'pgsql') {
            DB::statement("ALTER TABLE expenses ALTER COLUMN category TYPE VARCHAR(40)");
            DB::statement("ALTER TABLE expenses ALTER COLUMN category SET DEFAULT 'operasional_rutin'");
        }

        DB::table('expenses')
            ->where('category', 'stok')
            ->update(['category' => 'pembelian_stok']);

        DB::table('expenses')
            ->where('category', 'operasional')
            ->update(['category' => 'operasional_rutin']);

        DB::table('expenses')
            ->where('category', 'lainnya')
            ->update(['category' => 'operasional_rutin']);

        DB::table('expenses')
            ->where('category', '<>', 'hpp_pesanan')
            ->update(['hpp_status' => 'not_applicable']);
    }

    public function down(): void
    {
        DB::table('expenses')
            ->where('category', 'pembelian_stok')
            ->update(['category' => 'stok']);

        DB::table('expenses')
            ->where('category', 'operasional_rutin')
            ->update(['category' => 'operasional']);

        DB::table('expenses')
            ->whereIn('category', ['hpp_pesanan', 'aset_peralatan', 'pribadi_pemilik'])
            ->update(['category' => 'lainnya']);

        Schema::table('expenses', function (Blueprint $table) {
            if (Schema::hasColumn('expenses', 'transaction_id')) {
                $table->dropConstrainedForeignId('transaction_id');
            }

            if (Schema::hasColumn('expenses', 'hpp_status')) {
                $table->dropColumn('hpp_status');
            }
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE expenses MODIFY category ENUM('stok', 'operasional', 'lainnya') NOT NULL");
        }
    }
};
