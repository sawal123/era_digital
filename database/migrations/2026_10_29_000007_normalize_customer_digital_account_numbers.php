<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('customer_digital_accounts')
            ->select(['id', 'account_number'])
            ->orderBy('id')
            ->get()
            ->each(function ($account): void {
                $normalized = preg_replace('/\s+/', '', trim((string) $account->account_number));

                if ($normalized !== $account->account_number) {
                    DB::table('customer_digital_accounts')
                        ->where('id', $account->id)
                        ->update(['account_number' => $normalized]);
                }
            });

        $duplicates = DB::table('customer_digital_accounts')
            ->select(['type', 'account_number'])
            ->groupBy('type', 'account_number')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        if ($duplicates->isNotEmpty()) {
            throw new RuntimeException(
                'Duplikasi akun digital ditemukan. Selesaikan manual sebelum migration 000007: '.$duplicates->map(fn ($row) => $row->type.':'.$row->account_number)->implode(', ')
            );
        }

        Schema::table('customer_digital_accounts', function (Blueprint $table) {
            $table->unique(['type', 'account_number'], 'customer_digital_accounts_type_number_unique');
        });
    }

    public function down(): void
    {
        Schema::table('customer_digital_accounts', function (Blueprint $table) {
            $table->dropUnique('customer_digital_accounts_type_number_unique');
        });
    }
};
