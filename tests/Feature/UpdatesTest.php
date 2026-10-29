<?php

use App\Models\User;

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
