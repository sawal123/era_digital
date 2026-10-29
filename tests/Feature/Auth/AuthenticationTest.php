<?php

use App\Models\StoreProfile;
use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Testing\Fluent\AssertableJson;
use Laravel\Fortify\Features;

test('login screen can be rendered', function () {
    $response = $this->get(route('login'));

    $response->assertOk();
});

test('login membagikan logo toko dari setting', function () {
    StoreProfile::create([
        'store_name' => 'Era Digital',
        'address' => 'Alamat test',
        'phone' => '0800000000',
        'logo_path' => '/storage/logos/era-digital.png',
        'saldo_digital' => 0,
    ]);

    $this->get(route('login'))
        ->assertOk()
        ->assertInertia(fn (AssertableJson $page) => $page
            ->component('auth/Login')
            ->where('storeProfile.store_name', 'Era Digital')
            ->where('storeProfile.logo_path', '/storage/logos/era-digital.png'));
});

test('metadata browser menggunakan identitas toko dan bukan branding Laravel', function () {
    StoreProfile::create([
        'store_name' => 'Era Digital',
        'address' => 'Alamat test',
        'phone' => '0800000000',
        'logo_path' => '/storage/logos/era-digital.png',
        'saldo_digital' => 0,
    ]);

    $this->get(route('login'))
        ->assertOk()
        ->assertSee('<title inertia>Era Digital</title>', false)
        ->assertSee('<link rel="icon" href="/storage/logos/era-digital.png?v=', false)
        ->assertDontSee('href="/favicon.ico"', false)
        ->assertDontSee('>Laravel<', false);
});

test('users can authenticate using the login screen', function () {
    $user = User::factory()->create();

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));
});

test('users with two factor enabled are redirected to two factor challenge', function () {
    $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());

    Features::twoFactorAuthentication([
        'confirm' => true,
        'confirmPassword' => true,
    ]);

    $user = User::factory()->withTwoFactor()->create();

    $response = $this->post(route('login'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response->assertRedirect(route('two-factor.login'));
    $response->assertSessionHas('login.id', $user->id);
    $this->assertGuest();
});

test('users can not authenticate with invalid password', function () {
    $user = User::factory()->create();

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $this->assertGuest();
});

test('users can logout', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('logout'));

    $response->assertRedirect(route('home'));

    $this->assertGuest();
});

test('users are rate limited', function () {
    $user = User::factory()->create();

    RateLimiter::increment(md5('login'.implode('|', [$user->email, '127.0.0.1'])), amount: 5);

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $response->assertTooManyRequests();
});
