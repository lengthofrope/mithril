<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

uses(RefreshDatabase::class);

// --- Middleware: users without 2FA pass through ---

test('user without 2fa can access authenticated routes normally', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create([
        'two_factor_secret' => null,
        'two_factor_confirmed_at' => null,
    ]);

    $response = $this->actingAs($user)->get('/');

    $response->assertOk();
});

test('user with 2fa enabled is redirected to challenge page after login', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create([
        'two_factor_secret' => encrypt('TESTSECRET'),
        'two_factor_confirmed_at' => now(),
    ]);

    $response = $this->actingAs($user)->get('/');

    $response->assertRedirect(route('two-factor.challenge'));
});

test('user with 2fa who completed challenge can access routes normally', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create([
        'two_factor_secret' => encrypt('TESTSECRET'),
        'two_factor_confirmed_at' => now(),
    ]);

    $response = $this->actingAs($user)
        ->withSession(['two_factor_authenticated' => true])
        ->get('/');

    $response->assertOk();
});

test('two-factor challenge page is accessible without completing challenge', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create([
        'two_factor_secret' => encrypt('TESTSECRET'),
        'two_factor_confirmed_at' => now(),
    ]);

    $response = $this->actingAs($user)->get('/two-factor-challenge');

    $response->assertOk();
    $response->assertViewIs('auth.two-factor-challenge');
});

// --- Challenge form submission ---

test('valid totp code completes the two-factor challenge', function () {
    /** @var \Tests\TestCase $this */
    $google2fa = new Google2FA();
    $secret = $google2fa->generateSecretKey();

    $user = User::factory()->create([
        'two_factor_secret' => encrypt($secret),
        'two_factor_confirmed_at' => now(),
    ]);

    $validCode = $google2fa->getCurrentOtp($secret);

    $response = $this->actingAs($user)->post('/two-factor-challenge', [
        'code' => $validCode,
    ]);

    $response->assertRedirect(route('dashboard'));
    expect(session('two_factor_authenticated'))->toBeTrue();
});

test('invalid totp code is rejected during challenge', function () {
    /** @var \Tests\TestCase $this */
    $google2fa = new Google2FA();
    $secret = $google2fa->generateSecretKey();

    $user = User::factory()->create([
        'two_factor_secret' => encrypt($secret),
        'two_factor_confirmed_at' => now(),
    ]);

    $response = $this->actingAs($user)->post('/two-factor-challenge', [
        'code' => '000000',
    ]);

    $response->assertRedirect(route('two-factor.challenge'));
    $response->assertSessionHasErrors(['code']);
    expect(session('two_factor_authenticated'))->toBeNull();
});

test('valid recovery code completes the two-factor challenge', function () {
    /** @var \Tests\TestCase $this */
    $recoveryCodes = ['abc12-def34', 'ghi56-jkl78', 'mno90-pqr12'];

    $user = User::factory()->create([
        'two_factor_secret' => encrypt('TESTSECRET'),
        'two_factor_recovery_codes' => encrypt(json_encode($recoveryCodes)),
        'two_factor_confirmed_at' => now(),
    ]);

    $response = $this->actingAs($user)->post('/two-factor-challenge', [
        'recovery_code' => 'abc12-def34',
    ]);

    $response->assertRedirect(route('dashboard'));
    expect(session('two_factor_authenticated'))->toBeTrue();

    $user->refresh();
    $remainingCodes = json_decode(decrypt($user->two_factor_recovery_codes), true);
    expect($remainingCodes)->not->toContain('abc12-def34');
    expect($remainingCodes)->toHaveCount(2);
});

test('invalid recovery code is rejected during challenge', function () {
    /** @var \Tests\TestCase $this */
    $recoveryCodes = ['abc12-def34', 'ghi56-jkl78'];

    $user = User::factory()->create([
        'two_factor_secret' => encrypt('TESTSECRET'),
        'two_factor_recovery_codes' => encrypt(json_encode($recoveryCodes)),
        'two_factor_confirmed_at' => now(),
    ]);

    $response = $this->actingAs($user)->post('/two-factor-challenge', [
        'recovery_code' => 'wrong-code-here',
    ]);

    $response->assertRedirect(route('two-factor.challenge'));
    $response->assertSessionHasErrors(['code']);
});

test('challenge code field is validated as required when no recovery code provided', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create([
        'two_factor_secret' => encrypt('TESTSECRET'),
        'two_factor_confirmed_at' => now(),
    ]);

    $response = $this->actingAs($user)->post('/two-factor-challenge', []);

    $response->assertSessionHasErrors(['code']);
});

// --- Login flow integration ---

test('login with 2fa enabled redirects to challenge instead of dashboard', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create([
        'email' => 'test@example.com',
        'password' => Hash::make('password123'),
        'two_factor_secret' => encrypt('TESTSECRET'),
        'two_factor_confirmed_at' => now(),
    ]);

    $response = $this->post('/login', [
        'email' => 'test@example.com',
        'password' => 'password123',
    ]);

    $response->assertRedirect(route('two-factor.challenge'));
});

test('login without 2fa redirects to dashboard as usual', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create([
        'email' => 'test@example.com',
        'password' => Hash::make('password123'),
        'two_factor_secret' => null,
        'two_factor_confirmed_at' => null,
    ]);

    $response = $this->post('/login', [
        'email' => 'test@example.com',
        'password' => 'password123',
    ]);

    $response->assertRedirect(route('dashboard'));
});

// --- Remember-me bypasses 2FA challenge ---

test('user restored via remember token skips two-factor challenge', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create([
        'two_factor_secret' => encrypt('TESTSECRET'),
        'two_factor_confirmed_at' => now(),
        'remember_token' => Str::random(60),
    ]);

    $cookieName = Auth::guard('web')->getRecallerName();
    $recaller = $user->id . '|' . $user->remember_token . '|' . $user->password;

    $response = $this->withCookies([$cookieName => $recaller])->get('/');

    $response->assertOk();
    expect(Auth::viaRemember())->toBeTrue();
    expect(session('two_factor_authenticated'))->toBeTrue();
});

// --- Logout clears 2FA session ---

test('logout clears two-factor authenticated session flag', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create([
        'two_factor_secret' => encrypt('TESTSECRET'),
        'two_factor_confirmed_at' => now(),
    ]);

    $response = $this->actingAs($user)
        ->withSession(['two_factor_authenticated' => true])
        ->post('/logout');

    $response->assertRedirect(route('login'));
});
