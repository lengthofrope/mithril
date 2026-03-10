<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PragmaRX\Google2FA\Google2FA;

uses(RefreshDatabase::class);

// --- Enable ---

test('enable two-factor generates secret and recovery codes', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/profile/two-factor/enable');

    $response->assertRedirect(route('profile.index'));
    $response->assertSessionHas('two_factor_setup', true);

    $user->refresh();
    expect($user->two_factor_secret)->not->toBeNull();
    expect($user->two_factor_recovery_codes)->not->toBeNull();
    expect($user->two_factor_confirmed_at)->toBeNull();

    $recoveryCodes = json_decode(decrypt($user->two_factor_recovery_codes), true);
    expect($recoveryCodes)->toHaveCount(8);
});

test('enable two-factor redirects unauthenticated user to login', function () {
    /** @var \Tests\TestCase $this */
    $response = $this->post('/profile/two-factor/enable');

    $response->assertRedirect('/login');
});

// --- Confirm ---

test('confirm two-factor succeeds with valid code', function () {
    /** @var \Tests\TestCase $this */
    $google2fa = new Google2FA();
    $secret = $google2fa->generateSecretKey();

    $user = User::factory()->create([
        'two_factor_secret' => encrypt($secret),
        'two_factor_recovery_codes' => encrypt(json_encode(['code-1', 'code-2'])),
        'two_factor_confirmed_at' => null,
    ]);

    $validCode = $google2fa->getCurrentOtp($secret);

    $response = $this->actingAs($user)->post('/profile/two-factor/confirm', [
        'code' => $validCode,
    ]);

    $response->assertRedirect(route('profile.index'));
    $response->assertSessionHas('status', 'Two-factor authentication enabled successfully.');

    $user->refresh();
    expect($user->two_factor_confirmed_at)->not->toBeNull();
});

test('confirm two-factor fails with invalid code', function () {
    /** @var \Tests\TestCase $this */
    $google2fa = new Google2FA();
    $secret = $google2fa->generateSecretKey();

    $user = User::factory()->create([
        'two_factor_secret' => encrypt($secret),
        'two_factor_recovery_codes' => encrypt(json_encode(['code-1'])),
        'two_factor_confirmed_at' => null,
    ]);

    $response = $this->actingAs($user)->post('/profile/two-factor/confirm', [
        'code' => '000000',
    ]);

    $response->assertSessionHasErrors(['code']);

    $user->refresh();
    expect($user->two_factor_confirmed_at)->toBeNull();
});

test('confirm two-factor fails when secret is not set', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create([
        'two_factor_secret' => null,
    ]);

    $response = $this->actingAs($user)->post('/profile/two-factor/confirm', [
        'code' => '123456',
    ]);

    $response->assertSessionHasErrors(['code']);
});

test('confirm two-factor validates code is required', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/profile/two-factor/confirm', []);

    $response->assertSessionHasErrors(['code']);
});

test('confirm two-factor validates code is exactly 6 characters', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/profile/two-factor/confirm', [
        'code' => '12345',
    ]);

    $response->assertSessionHasErrors(['code']);
});

test('confirm two-factor redirects unauthenticated user to login', function () {
    /** @var \Tests\TestCase $this */
    $response = $this->post('/profile/two-factor/confirm', ['code' => '123456']);

    $response->assertRedirect('/login');
});

// --- Disable ---

test('disable two-factor clears all two-factor fields', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create([
        'password' => Hash::make('my-password'),
        'two_factor_secret' => encrypt('TESTSECRET'),
        'two_factor_recovery_codes' => encrypt(json_encode(['code-1'])),
        'two_factor_confirmed_at' => now(),
    ]);

    $response = $this->actingAs($user)
        ->withSession(['two_factor_authenticated' => true])
        ->delete('/profile/two-factor/disable', [
            'current_password' => 'my-password',
        ]);

    $response->assertRedirect(route('profile.index'));
    $response->assertSessionHas('status', 'Two-factor authentication has been disabled.');

    $user->refresh();
    expect($user->two_factor_secret)->toBeNull();
    expect($user->two_factor_recovery_codes)->toBeNull();
    expect($user->two_factor_confirmed_at)->toBeNull();
});

test('disable two-factor requires current password', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create([
        'two_factor_secret' => encrypt('TESTSECRET'),
        'two_factor_confirmed_at' => now(),
    ]);

    $response = $this->actingAs($user)
        ->withSession(['two_factor_authenticated' => true])
        ->delete('/profile/two-factor/disable', []);

    $response->assertSessionHasErrors(['current_password']);
});

test('disable two-factor fails with wrong password', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create([
        'password' => Hash::make('correct-password'),
        'two_factor_secret' => encrypt('TESTSECRET'),
        'two_factor_confirmed_at' => now(),
    ]);

    $response = $this->actingAs($user)
        ->withSession(['two_factor_authenticated' => true])
        ->delete('/profile/two-factor/disable', [
            'current_password' => 'wrong-password',
        ]);

    $response->assertSessionHasErrors(['current_password']);

    $user->refresh();
    expect($user->two_factor_secret)->not->toBeNull();
});

test('disable two-factor redirects unauthenticated user to login', function () {
    /** @var \Tests\TestCase $this */
    $response = $this->delete('/profile/two-factor/disable');

    $response->assertRedirect('/login');
});

// --- Model ---

test('user has two factor enabled returns true when confirmed', function () {
    $user = User::factory()->create([
        'two_factor_secret' => encrypt('TESTSECRET'),
        'two_factor_confirmed_at' => now(),
    ]);

    expect($user->hasTwoFactorEnabled())->toBeTrue();
});

test('user has two factor enabled returns false when not confirmed', function () {
    $user = User::factory()->create([
        'two_factor_secret' => encrypt('TESTSECRET'),
        'two_factor_confirmed_at' => null,
    ]);

    expect($user->hasTwoFactorEnabled())->toBeFalse();
});

test('user has two factor enabled returns false when no secret', function () {
    $user = User::factory()->create([
        'two_factor_secret' => null,
        'two_factor_confirmed_at' => null,
    ]);

    expect($user->hasTwoFactorEnabled())->toBeFalse();
});

// --- QR Code Generation ---

test('qr code svg is generated for user with secret', function () {
    $google2fa = new Google2FA();
    $secret = $google2fa->generateSecretKey();

    $user = User::factory()->create([
        'two_factor_secret' => encrypt($secret),
    ]);

    $svg = \App\Http\Controllers\Web\TwoFactorController::generateQrCodeSvg($user);

    expect($svg)->toContain('<svg');
    expect($svg)->toContain('</svg>');
});

test('qr code svg returns empty string when no secret', function () {
    $user = User::factory()->create([
        'two_factor_secret' => null,
    ]);

    $svg = \App\Http\Controllers\Web\TwoFactorController::generateQrCodeSvg($user);

    expect($svg)->toBe('');
});
