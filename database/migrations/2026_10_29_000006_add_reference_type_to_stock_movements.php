<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('stock_movements', 'reference_type')) {
            Schema::table('stock_movements', function (Blueprint $table) {
                $table->string('reference_type', 40)->nullable()->after('quantity');
                $table->index(['reference_type', 'reference_id']);
            });
        }

        DB::table('purchases')
            ->select(['id', 'product_id'])
            ->orderBy('id')
            ->get()
            ->each(function ($purchase): void {
                DB::table('stock_movements')
                    ->whereNull('reference_type')
                    ->where('type', 'in')
                    ->where('product_id', $purchase->product_id)
                    ->where('reference_id', $purchase->id)
                    ->update(['reference_type' => 'purchase']);
            });
    }

    public function down(): void
    {
        if (Schema::hasColumn('stock_movements', 'reference_type')) {
            Schema::table('stock_movements', function (Blueprint $table) {
                $table->dropIndex(['reference_type', 'reference_id']);
                $table->dropColumn('reference_type');
            });
        }
    }
};
