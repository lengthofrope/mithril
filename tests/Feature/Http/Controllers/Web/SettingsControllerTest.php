<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

test('settings index returns 200 for authenticated user', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/settings');

    $response->assertOk();
});

test('settings index redirects unauthenticated user to login', function () {
    /** @var \Tests\TestCase $this */
    $response = $this->get('/settings');

    $response->assertRedirect('/login');
});

test('settings index renders the correct view', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/settings');

    $response->assertViewIs('pages.settings.index');
});

test('settings index passes authenticated user to view', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create(['name' => 'Jane Doe']);

    $response = $this->actingAs($user)->get('/settings');

    $response->assertViewHas('user');
    expect($response->viewData('user')->id)->toBe($user->id);
});

test('settings updateProfile updates user name and email', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create([
        'name' => 'Old Name',
        'email' => 'old@example.com',
        'theme_preference' => 'light',
    ]);

    $response = $this->actingAs($user)->patch('/settings/profile', [
        'name' => 'New Name',
        'email' => 'new@example.com',
        'theme_preference' => 'dark',
    ]);

    $response->assertRedirect(route('settings.index'));

    $this->assertDatabaseHas('users', [
        'id' => $user->id,
        'name' => 'New Name',
        'email' => 'new@example.com',
        'theme_preference' => 'dark',
    ]);
});

test('settings updateProfile redirects unauthenticated user to login', function () {
    /** @var \Tests\TestCase $this */
    $response = $this->patch('/settings/profile', [
        'name' => 'Hacker',
        'email' => 'hack@example.com',
        'theme_preference' => 'light',
    ]);

    $response->assertRedirect('/login');
});

test('settings updateProfile returns 422 when name is missing', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();

    $response = $this->actingAs($user)->patch('/settings/profile', [
        'email' => 'test@example.com',
        'theme_preference' => 'light',
    ]);

    $response->assertSessionHasErrors(['name']);
});

test('settings updateProfile returns 422 when email is invalid', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();

    $response = $this->actingAs($user)->patch('/settings/profile', [
        'name' => 'Test User',
        'email' => 'not-an-email',
        'theme_preference' => 'light',
    ]);

    $response->assertSessionHasErrors(['email']);
});

test('settings updateProfile returns 422 when theme_preference is invalid', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();

    $response = $this->actingAs($user)->patch('/settings/profile', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'theme_preference' => 'solarized',
    ]);

    $response->assertSessionHasErrors(['theme_preference']);
});

test('settings updateProfile returns 422 when email is taken by another user', function () {
    /** @var \Tests\TestCase $this */
    User::factory()->create(['email' => 'taken@example.com']);
    $user = User::factory()->create(['email' => 'mine@example.com']);

    $response = $this->actingAs($user)->patch('/settings/profile', [
        'name' => 'Test User',
        'email' => 'taken@example.com',
        'theme_preference' => 'light',
    ]);

    $response->assertSessionHasErrors(['email']);
});

test('settings updateProfile allows user to keep their own email', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create([
        'email' => 'mine@example.com',
        'theme_preference' => 'light',
    ]);

    $response = $this->actingAs($user)->patch('/settings/profile', [
        'name' => 'Updated Name',
        'email' => 'mine@example.com',
        'theme_preference' => 'dark',
    ]);

    $response->assertRedirect(route('settings.index'));
    $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => 'Updated Name']);
});

test('settings updateProfile updates password when current password is correct', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create(['password' => Hash::make('current-password')]);

    $response = $this->actingAs($user)->patch('/settings/profile', [
        'name' => $user->name,
        'email' => $user->email,
        'theme_preference' => 'light',
        'current_password' => 'current-password',
        'password' => 'new-password-123',
        'password_confirmation' => 'new-password-123',
    ]);

    $response->assertRedirect(route('settings.index'));
    $user->refresh();
    expect(Hash::check('new-password-123', $user->password))->toBeTrue();
});

test('settings updateProfile returns error when current password is wrong', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create(['password' => Hash::make('real-password')]);

    $response = $this->actingAs($user)->patch('/settings/profile', [
        'name' => $user->name,
        'email' => $user->email,
        'theme_preference' => 'light',
        'current_password' => 'wrong-password',
        'password' => 'new-password-123',
        'password_confirmation' => 'new-password-123',
    ]);

    $response->assertSessionHasErrors(['current_password']);
});

test('settings updateProfile does not change password when current_password is empty', function () {
    /** @var \Tests\TestCase $this */
    $originalHash = Hash::make('unchanged-password');
    $user = User::factory()->create(['password' => $originalHash]);

    $this->actingAs($user)->patch('/settings/profile', [
        'name' => $user->name,
        'email' => $user->email,
        'theme_preference' => 'light',
    ]);

    $user->refresh();
    expect(Hash::check('unchanged-password', $user->password))->toBeTrue();
});

test('settings updateProfile sets push_enabled to false when not sent', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create(['push_enabled' => true]);

    $this->actingAs($user)->patch('/settings/profile', [
        'name' => $user->name,
        'email' => $user->email,
        'theme_preference' => 'light',
    ]);

    $this->assertDatabaseHas('users', ['id' => $user->id, 'push_enabled' => false]);
});

test('settings updateProfile sets push_enabled to true when sent', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create(['push_enabled' => false]);

    $this->actingAs($user)->patch('/settings/profile', [
        'name' => $user->name,
        'email' => $user->email,
        'theme_preference' => 'light',
        'push_enabled' => true,
    ]);

    $this->assertDatabaseHas('users', ['id' => $user->id, 'push_enabled' => true]);
});
