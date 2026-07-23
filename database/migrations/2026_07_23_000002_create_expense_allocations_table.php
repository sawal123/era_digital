<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expense_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expense_id')->constrained('expenses')->cascadeOnDelete();
            $table->foreignId('transaction_id')->constrained('transactions')->restrictOnDelete();
            $table->foreignId('transaction_item_id')->nullable()->constrained('transaction_items')->restrictOnDelete();
            $table->decimal('amount', 15, 2);
            $table->string('hpp_status', 40);
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['expense_id', 'transaction_id']);
            $table->index(['transaction_id', 'transaction_item_id']);
        });

        DB::table('expenses')
            ->where('category', 'hpp_pesanan')
            ->whereNotNull('transaction_id')
            ->orderBy('id')
            ->each(function ($expense) {
                $exists = DB::table('expense_allocations')
                    ->where('expense_id', $expense->id)
                    ->where('transaction_id', $expense->transaction_id)
                    ->whereNull('transaction_item_id')
                    ->exists();

                if ($exists) {
                    return;
                }

                DB::table('expense_allocations')->insert([
                    'expense_id' => $expense->id,
                    'transaction_id' => $expense->transaction_id,
                    'transaction_item_id' => null,
                    'amount' => $expense->amount,
                    'hpp_status' => in_array($expense->hpp_status, ['belum_masuk_hpp', 'sudah_masuk_hpp'], true)
                        ? $expense->hpp_status
                        : 'belum_masuk_hpp',
                    'note' => $expense->note,
                    'created_at' => $expense->created_at ?? now(),
                    'updated_at' => $expense->updated_at ?? now(),
                ]);
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('expense_allocations');
    }
};
