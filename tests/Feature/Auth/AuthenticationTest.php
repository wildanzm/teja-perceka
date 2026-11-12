<?php

use App\Models\User;
use Laravel\Fortify\Features;

test('login screen can be rendered', function () {
    $this->get(route('login'))->assertOk();
});

test('login screen offers passkey sign in when feature is enabled', function () {
    $this->skipUnlessFortifyHas(Features::passkeys());

    $this->get(route('login'))
        ->assertOk()
        ->assertSee('data-test="passkey-login-button"', false)
        ->assertSee('autocomplete="email webauthn"', false);
});

test('users can authenticate and are offered the dashboard before redirecting', function () {
    $user = User::factory()->create();

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertOk()
        ->assertSee('id="login-success-swal"', false)
        ->assertSee('Login Berhasil!')
        ->assertSee(route('dashboard', absolute: false), false);

    $this->assertAuthenticated();
});

test('users cannot authenticate with an invalid password', function () {
    $user = User::factory()->create();

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'wrong-password',
    ])->assertSessionHasErrors(['password' => 'Kata sandi salah. Silakan coba lagi.']);

    $this->assertGuest();
});

test('login with an unregistered email errors the email field', function () {
    $this->post(route('login.store'), [
        'email' => 'tidak-terdaftar@example.com',
        'password' => 'password',
    ])->assertSessionHasErrors(['email' => 'Email tidak terdaftar. Periksa kembali alamat email Anda.']);

    $this->assertGuest();
});

test('inactive users cannot authenticate', function () {
    $user = User::factory()->create(['is_active' => false]);

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertSessionHasErrors(['email' => 'Akun Anda tidak aktif.']);

    $this->assertGuest();
});

test('failed login renders the failure modal', function () {
    $user = User::factory()->create();

    $this->from(route('login'))
        ->followingRedirects()
        ->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])
        ->assertOk()
        ->assertSee('id="login-swal"', false)
        ->assertSee('Kesalahan pada Kata Sandi')
        ->assertSee('Kata sandi salah. Silakan coba lagi.');

    $this->assertGuest();
});

test('login ignores an external intended url', function () {
    $user = User::factory()->create();

    $this->withSession(['url.intended' => 'https://attacker.example.com/phish'])
        ->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ])
        ->assertOk()
        ->assertSee(route('dashboard', absolute: false), false)
        ->assertDontSee('attacker.example.com', false);

    $this->assertAuthenticated();
});

test('users with two factor enabled are redirected to two factor challenge', function () {
    $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());

    Features::twoFactorAuthentication([
        'confirm' => true,
        'confirmPassword' => true,
    ]);

    $user = User::factory()->withTwoFactor()->create();

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect(route('two-factor.login'));

    $this->assertGuest();
});

test('users can logout', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('logout'))
        ->assertRedirect(route('home'));

    $this->assertGuest();
});
