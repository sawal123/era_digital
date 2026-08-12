<?php

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    config()->set('services.undangan.base_url', 'https://undangan-digital.test/api/v1');
    config()->set('services.undangan.api_key', 'test-api-key');

    $user = User::factory()->create();
    $this->actingAs($user);
});

test('index mengambil daftar undangan-cetak dan jenis-undangan dari API', function () {
    Http::fake([
        'https://undangan-digital.test/api/v1/undangan-cetak*' => Http::response([
            'success' => true,
            'data' => [
                'current_page' => 1,
                'data' => [
                    [
                        'id' => 1,
                        'nama' => 'Undangan A',
                        'jenis_id' => 1,
                        'jenis_undangan' => ['id' => 1, 'jenis' => 'Premium'],
                        'stok' => 100,
                        'harga' => 5000,
                    ],
                ],
                'last_page' => 1,
                'total' => 1,
            ],
        ]),
        'https://undangan-digital.test/api/v1/jenis-undangan' => Http::response([
            'success' => true,
            'data' => [
                ['id' => 1, 'jenis' => 'Premium'],
                ['id' => 2, 'jenis' => 'Standar'],
            ],
        ]),
    ]);

    $response = $this->get('/undangan');

    $response->assertInertia(fn (Assert $page) => $page
        ->component('Undangan/Index')
        ->has('undangan', 1)
        ->has('jenisUndangan', 2)
        ->where('jenisUndangan.0.id', 1)
        ->where('jenisUndangan.0.jenis', 'Premium')
    );
});

test('store mengirimkan jenis_id bukan jenis', function () {
    Http::fake([
        'undangan-digital.test/*' => Http::response(['success' => true, 'data' => []], 200),
    ]);

    $resp = $this->post('/undangan', [
        'nama' => 'Undangan Test',
        'jenis_id' => 1,
        'stok' => 100,
        'harga' => 5000,
    ]);

    $resp->assertRedirect();

    Http::assertSent(function ($request) {
        return str_contains($request->url(), '/undangan-cetak')
            && $request->method() === 'POST'
            && isset($request->data()['jenis_id'])
            && ! isset($request->data()['jenis']);
    });
});

test('update mengirimkan jenis_id bukan jenis', function () {
    Http::fake([
        'undangan-digital.test/*' => Http::response(['success' => true, 'data' => []], 200),
    ]);

    $resp = $this->put('/undangan/1', [
        'nama' => 'Undangan Updated',
        'jenis_id' => 2,
        'stok' => 200,
        'harga' => 6000,
    ]);

    $resp->assertRedirect();

    Http::assertSent(function ($request) {
        return str_contains($request->url(), '/undangan-cetak/1')
            && $request->method() === 'PUT'
            && isset($request->data()['jenis_id'])
            && ! isset($request->data()['jenis']);
    });
});

test('store meneruskan error validasi dari API', function () {
    Http::fake([
        'undangan-digital.test/*' => Http::response([
            'success' => false,
            'message' => 'Validasi gagal.',
            'errors' => ['jenis_id' => ['The jenis id field is required.']],
        ], 422),
    ]);

    $this->post('/undangan', [
        'nama' => 'Undangan Test',
        'stok' => 100,
        'harga' => 5000,
    ])->assertSessionHasErrors();
});
