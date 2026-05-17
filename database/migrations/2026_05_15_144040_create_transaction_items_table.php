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
        Schema::create('transaction_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->constrained()->onDelete('cascade');
            $table->foreignId('product_id')->nullable()->constrained()->onDelete('set null');
            $table->string('item_name');
            $table->enum('type', ['fisik', 'jasa', 'ppob']);
            $table->string('unit')->nullable();
            $table->decimal('quantity', 10, 2);
            $table->decimal('base_price', 15, 2);
            $table->decimal('selling_price', 15, 2);
            $table->decimal('subtotal_base', 15, 2);
            $table->decimal('subtotal_price', 15, 2);
            $table->decimal('profit', 15, 2);
            $table->enum('service_status', ['none', 'menunggu_file', 'diproses_mitra', 'siap_diambil', 'selesai'])->default('none');
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transaction_items');
    }
};
