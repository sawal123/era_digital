<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('customers', 'name_key')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->string('name_key', 191)->nullable()->index()->after('name');
            });
        }

        DB::table('customers')
            ->select(['id', 'name'])
            ->orderBy('id')
            ->chunkById(200, function ($customers) {
                foreach ($customers as $customer) {
                    DB::table('customers')
                        ->where('id', $customer->id)
                        ->update(['name_key' => Str::squish(Str::lower((string) $customer->name))]);
                }
            });
    }

    public function down(): void
    {
        if (Schema::hasColumn('customers', 'name_key')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->dropIndex(['name_key']);
                $table->dropColumn('name_key');
            });
        }
    }
};
