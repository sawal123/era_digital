<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

test('jenis udangan relation is created with jenis_id and no legacy jenis column', function () {
    expect(Schema::hasTable('undangan_cetaks'))->toBeTrue()
        ->and(Schema::hasTable('jenis_udangans'))->toBeTrue()
        ->and(Schema::hasColumn('undangan_cetaks', 'jenis_id'))->toBeTrue()
        ->and(Schema::hasColumn('undangan_cetaks', 'jenis'))->toBeFalse();

    $jenisId = DB::table('jenis_udangans')->insertGetId([
        'jenis' => 'Pernikahan',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('undangan_cetaks')->insert([
        'nama' => 'Undangan Test',
        'jenis_id' => $jenisId,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $record = DB::table('undangan_cetaks')->first();

    expect((int) $record->jenis_id)->toBe($jenisId)
        ->and(DB::table('jenis_udangans')->where('id', $jenisId)->exists())->toBeTrue();
});
