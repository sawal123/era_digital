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
        // 1. Add admin_fee to products table if not exists
        if (!Schema::hasColumn('products', 'admin_fee')) {
            Schema::table('products', function (Blueprint $table) {
                $table->decimal('admin_fee', 15, 2)->default(0)->after('selling_price');
            });
        }

        // 2. Create customer_digital_accounts table
        Schema::create('customer_digital_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->onDelete('cascade');
            $table->enum('type', ['token', 'phone']);
            $table->string('account_number');
            $table->string('account_name');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (Schema::hasColumn('products', 'admin_fee')) {
                $table->dropColumn('admin_fee');
            }
        });

        Schema::dropIfExists('customer_digital_accounts');
    }
};
