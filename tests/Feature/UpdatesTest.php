<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

it('menampilkan halaman informasi update untuk pengguna terautentikasi', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/updates');

    $response->assertOk();
    $response->assertInertia(function ($page) {
        $page->component('Updates/Index')
            ->has('latestUpdate.version')
            ->has('latestUpdate.highlights')
            ->has('updates');

        return true;
    });
});

it('mengumumkan chatbot terbaru dan mempertahankan isi update sebelumnya', function () {
    $oldUpdate = [
        'version' => '2026.10.29',
        'date' => '29 Oktober 2026',
        'title' => 'Pembaruan pengelolaan modal dan transaksi',
        'summary' => 'Peningkatan pencatatan modal, saldo digital, customer, laporan, dan pengalaman penggunaan aplikasi.',
        'highlights' => [
            'Ledger modal dan saldo digital yang lebih transparan.',
            'Perhitungan provider cost dan laba PPOB lebih akurat.',
            'Filter riwayat ledger dengan rentang tanggal.',
            'Pengelolaan customer dan akun digital yang lebih terstruktur.',
        ],
    ];

    $this->actingAs(User::factory()->create())->get('/updates')->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Updates/Index')
            ->where('latestUpdate.version', '2026.11.01')
            ->where('latestUpdate.action.type', 'open-chat')
            ->where('latestUpdate.action.label', 'Coba Bantuan POS')
            ->has('latestUpdate.getting_started', 3)
            ->has('updates', 2)
            ->where('updates.0.version', '2026.11.01')
            ->where('updates.1', $oldUpdate));
});

it('membagikan pengumuman chatbot pada halaman aplikasi untuk notifikasi versi baru', function () {
    $this->actingAs(User::factory()->create())->get('/categories')->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('latestUpdate.version', '2026.11.01')
            ->where('latestUpdate.title', 'Asisten POS: bantuan AI langsung di aplikasi')
            ->has('latestUpdate.highlights', 4));
});

it('memerlukan login untuk melihat riwayat update', function () {
    $this->get('/updates')->assertRedirect('/login');
});
