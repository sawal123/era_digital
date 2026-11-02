<?php

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    config()->set('services.undangan.base_url', 'https://undangan-access.test/api/v1');
    config()->set('services.undangan.api_key', 'test-only-undangan-key');
    $this->withoutVite();
    Http::preventStrayRequests();
    $this->actingAs(User::factory()->create());
});

it('menampilkan halaman kebutuhan API key tanpa request ketika key belum diisi', function (?string $key) {
    config()->set('services.undangan.api_key', $key);
    Http::fake();

    $this->get('/undangan')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Undangan/ApiKeyRequired')
        ->missing('undangan')
        ->missing('jenisUndangan'));

    Http::assertNothingSent();
})->with([null, '', '   ']);

it('mengganti katalog dengan halaman akses ketika API menolak key', function (int $status) {
    Http::fake([
        'undangan-access.test/*' => Http::response('PRIVATE-UPSTREAM-DETAIL test-only-undangan-key', $status),
    ]);

    $this->get('/undangan')->assertOk()
        ->assertDontSee('test-only-undangan-key')
        ->assertDontSee('PRIVATE-UPSTREAM-DETAIL')
        ->assertInertia(fn (Assert $page) => $page
            ->component('Undangan/ApiKeyRequired')
            ->missing('undangan')
            ->missing('jenisUndangan'));

    Http::assertSentCount(1);
})->with([401, 403]);

it('menampilkan halaman akses juga ketika endpoint jenis undangan ditolak', function (int $status) {
    Http::fake([
        'undangan-access.test/api/v1/undangan-cetak*' => Http::response([
            'data' => ['data' => [['id' => 1, 'nama' => 'Data sebelum penolakan']], 'last_page' => 1, 'total' => 1],
        ]),
        'undangan-access.test/api/v1/jenis-undangan' => Http::response(['message' => 'PRIVATE-UPSTREAM-DETAIL'], $status),
    ]);

    $this->get('/undangan')->assertOk()
        ->assertDontSee('Data sebelum penolakan')
        ->assertDontSee('PRIVATE-UPSTREAM-DETAIL')
        ->assertInertia(fn (Assert $page) => $page
            ->component('Undangan/ApiKeyRequired')
            ->missing('undangan'));

    Http::assertSentCount(2);
})->with([401, 403]);

it('tidak menampilkan data parsial ketika akses ditolak pada halaman API berikutnya', function () {
    Http::fakeSequence('undangan-access.test/*')
        ->push(['data' => ['data' => [['id' => 1, 'nama' => 'Data parsial']], 'last_page' => 2, 'total' => 2]])
        ->push(['message' => 'Key expired'], 401);

    $this->get('/undangan')->assertOk()
        ->assertDontSee('Data parsial')
        ->assertInertia(fn (Assert $page) => $page->component('Undangan/ApiKeyRequired'));

    Http::assertSentCount(2);
});

it('tetap membedakan gangguan server dari penolakan akses API key', function () {
    Http::fake([
        'undangan-access.test/api/v1/undangan-cetak*' => Http::response(['message' => 'Layanan sedang bermasalah.'], 500),
        'undangan-access.test/api/v1/jenis-undangan' => Http::response(['data' => []]),
    ]);

    $this->get('/undangan')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Undangan/Index')
        ->where('undangan', [])
        ->where('apiError', 'Layanan sedang bermasalah.'));
});
