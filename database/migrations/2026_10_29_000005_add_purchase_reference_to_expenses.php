<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('expenses', 'purchase_id')) {
            Schema::table('expenses', function (Blueprint $table) {
                $table->foreignId('purchase_id')
                    ->nullable()
                    ->after('transaction_id')
                    ->constrained('purchases')
                    ->nullOnDelete();
            });
        }

        // Backfill only unambiguous legacy restock expenses. Ambiguous rows stay
        // unlinked so production history is never attached to the wrong purchase.
        DB::table('purchases')
            ->join('products', 'products.id', '=', 'purchases.product_id')
            ->select([
                'purchases.id as purchase_id',
                'purchases.total_price',
                'purchases.purchase_date',
                'products.name as product_name',
            ])
            ->orderBy('purchases.id')
            ->get()
            ->each(function ($purchase): void {
                $matches = DB::table('expenses')
                    ->whereNull('purchase_id')
                    ->where('category', 'pembelian_stok')
                    ->where('name', 'Belanja Stok: '.$purchase->product_name)
                    ->where('amount', $purchase->total_price)
                    ->whereDate('date', $purchase->purchase_date)
                    ->pluck('id');

                if ($matches->count() === 1) {
                    DB::table('expenses')
                        ->where('id', $matches->first())
                        ->update(['purchase_id' => $purchase->purchase_id]);
                }
            });
    }

    public function down(): void
    {
        if (Schema::hasColumn('expenses', 'purchase_id')) {
            Schema::table('expenses', function (Blueprint $table) {
                $table->dropForeign(['purchase_id']);
                $table->dropColumn('purchase_id');
            });
        }
    }
};
