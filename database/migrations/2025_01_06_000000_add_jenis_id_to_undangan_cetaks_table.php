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
        if (!Schema::hasTable('undangan_cetaks') || !Schema::hasTable('jenis_udangans')) {
            return;
        }

        if (!Schema::hasColumn('undangan_cetaks', 'jenis_id')) {
            Schema::table('undangan_cetaks', function (Blueprint $table) {
                $table->foreignId('jenis_id')->nullable()->after('id')->constrained('jenis_udangans')->onDelete('restrict')->onUpdate('cascade');
            });
        }

        if (Schema::hasColumn('undangan_cetaks', 'jenis')) {
            Schema::table('undangan_cetaks', function (Blueprint $table) {
                $table->dropColumn('jenis');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('undangan_cetaks')) {
            return;
        }

        if (Schema::hasColumn('undangan_cetaks', 'jenis_id')) {
            Schema::table('undangan_cetaks', function (Blueprint $table) {
                $table->dropConstrainedForeignId('jenis_id');
            });
        }

        if (!Schema::hasColumn('undangan_cetaks', 'jenis')) {
            Schema::table('undangan_cetaks', function (Blueprint $table) {
                $table->string('jenis')->nullable()->after('id');
            });
        }
    }
};
