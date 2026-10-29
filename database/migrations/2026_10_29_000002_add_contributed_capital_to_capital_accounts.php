<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('capital_accounts', function (Blueprint $table) {
            $table->decimal('contributed_capital', 15, 2)
                ->default(0)
                ->after('current_balance');
        });

        DB::table('capital_accounts')
            ->where('type', 'digital')
            ->update([
                'contributed_capital' => DB::raw('current_balance'),
            ]);
    }

    public function down(): void
    {
        Schema::table('capital_accounts', function (Blueprint $table) {
            $table->dropColumn('contributed_capital');
        });
    }
};
