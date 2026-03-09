<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

// --- Index / Show ---

test('profile page returns 200 for authenticated user', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/profile');

    $response->assertOk();
});

test('profile page redirects unauthenticated user to login', function () {
    /** @var \Tests\TestCase $this */
    $response = $this->get('/profile');

    $response->assertRedirect('/login');
});

test('profile page renders the correct view', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/profile');

    $response->assertViewIs('pages.profile.index');
});

test('profile page passes user to view', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create(['name' => 'Jane Doe']);

    $response = $this->actingAs($user)->get('/profile');

    $response->assertViewHas('user');
    expect($response->viewData('user')->id)->toBe($user->id);
});

// --- Update Profile ---

test('profile update updates user name and email', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create([
        'name' => 'Old Name',
        'email' => 'old@example.com',
    ]);

    $response = $this->actingAs($user)->patch('/profile', [
        'name' => 'New Name',
        'email' => 'new@example.com',
    ]);

    $response->assertRedirect(route('profile.index'));

    $this->assertDatabaseHas('users', [
        'id' => $user->id,
        'name' => 'New Name',
        'email' => 'new@example.com',
    ]);
});

test('profile update redirects unauthenticated user to login', function () {
    /** @var \Tests\TestCase $this */
    $response = $this->patch('/profile', [
        'name' => 'Hacker',
        'email' => 'hack@example.com',
    ]);

    $response->assertRedirect('/login');
});

test('profile update returns 422 when name is missing', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();

    $response = $this->actingAs($user)->patch('/profile', [
        'email' => 'test@example.com',
    ]);

    $response->assertSessionHasErrors(['name']);
});

test('profile update returns 422 when email is invalid', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();

    $response = $this->actingAs($user)->patch('/profile', [
        'name' => 'Test User',
        'email' => 'not-an-email',
    ]);

    $response->assertSessionHasErrors(['email']);
});

test('profile update returns 422 when email is taken by another user', function () {
    /** @var \Tests\TestCase $this */
    User::factory()->create(['email' => 'taken@example.com']);
    $user = User::factory()->create(['email' => 'mine@example.com']);

    $response = $this->actingAs($user)->patch('/profile', [
        'name' => 'Test User',
        'email' => 'taken@example.com',
    ]);

    $response->assertSessionHasErrors(['email']);
});

test('profile update allows user to keep their own email', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create(['email' => 'mine@example.com']);

    $response = $this->actingAs($user)->patch('/profile', [
        'name' => 'Updated Name',
        'email' => 'mine@example.com',
    ]);

    $response->assertRedirect(route('profile.index'));
    $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => 'Updated Name']);
});

// --- Password Change ---

test('profile update changes password when current password is correct', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create(['password' => Hash::make('current-password')]);

    $response = $this->actingAs($user)->patch('/profile', [
        'name' => $user->name,
        'email' => $user->email,
        'current_password' => 'current-password',
        'password' => 'new-password-123',
        'password_confirmation' => 'new-password-123',
    ]);

    $response->assertRedirect(route('profile.index'));
    $user->refresh();
    expect(Hash::check('new-password-123', $user->password))->toBeTrue();
});

test('profile update returns error when current password is wrong', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create(['password' => Hash::make('real-password')]);

    $response = $this->actingAs($user)->patch('/profile', [
        'name' => $user->name,
        'email' => $user->email,
        'current_password' => 'wrong-password',
        'password' => 'new-password-123',
        'password_confirmation' => 'new-password-123',
    ]);

    $response->assertSessionHasErrors(['current_password']);
});

test('profile update requires current password when setting new password', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create(['password' => Hash::make('original-password')]);

    $response = $this->actingAs($user)->patch('/profile', [
        'name' => $user->name,
        'email' => $user->email,
        'password' => 'new-password-123',
        'password_confirmation' => 'new-password-123',
    ]);

    $response->assertSessionHasErrors(['current_password']);
    $user->refresh();
    expect(Hash::check('original-password', $user->password))->toBeTrue();
});

test('profile update does not change password when fields are empty', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create(['password' => Hash::make('unchanged-password')]);

    $this->actingAs($user)->patch('/profile', [
        'name' => $user->name,
        'email' => $user->email,
    ]);

    $user->refresh();
    expect(Hash::check('unchanged-password', $user->password))->toBeTrue();
});

// --- Avatar Upload ---

test('profile avatar upload stores file and updates user', function () {
    /** @var \Tests\TestCase $this */
    Storage::fake('public');
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/profile/avatar', [
        'avatar' => UploadedFile::fake()->image('photo.jpg', 300, 300),
    ]);

    $response->assertRedirect(route('profile.index'));

    $user->refresh();
    expect($user->avatar_path)->not->toBeNull();
    Storage::disk('public')->assertExists($user->avatar_path);
});

test('profile avatar upload validates file is an image', function () {
    /** @var \Tests\TestCase $this */
    Storage::fake('public');
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/profile/avatar', [
        'avatar' => UploadedFile::fake()->create('document.pdf', 100),
    ]);

    $response->assertSessionHasErrors(['avatar']);
});

test('profile avatar upload validates file size max 2MB', function () {
    /** @var \Tests\TestCase $this */
    Storage::fake('public');
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/profile/avatar', [
        'avatar' => UploadedFile::fake()->image('huge.jpg')->size(3000),
    ]);

    $response->assertSessionHasErrors(['avatar']);
});

test('profile avatar upload deletes old avatar when uploading new one', function () {
    /** @var \Tests\TestCase $this */
    Storage::fake('public');
    $user = User::factory()->create(['avatar_path' => 'avatars/old-photo.jpg']);
    Storage::disk('public')->put('avatars/old-photo.jpg', 'old-content');

    $this->actingAs($user)->post('/profile/avatar', [
        'avatar' => UploadedFile::fake()->image('new-photo.jpg', 300, 300),
    ]);

    $user->refresh();
    Storage::disk('public')->assertMissing('avatars/old-photo.jpg');
    Storage::disk('public')->assertExists($user->avatar_path);
});

test('profile avatar delete removes avatar path from user', function () {
    /** @var \Tests\TestCase $this */
    Storage::fake('public');
    $user = User::factory()->create(['avatar_path' => 'avatars/photo.jpg']);
    Storage::disk('public')->put('avatars/photo.jpg', 'content');

    $response = $this->actingAs($user)->delete('/profile/avatar');

    $response->assertRedirect(route('profile.index'));
    $user->refresh();
    expect($user->avatar_path)->toBeNull();
    Storage::disk('public')->assertMissing('avatars/photo.jpg');
});

test('profile avatar upload redirects unauthenticated user to login', function () {
    /** @var \Tests\TestCase $this */
    $response = $this->post('/profile/avatar', [
        'avatar' => UploadedFile::fake()->image('photo.jpg'),
    ]);

    $response->assertRedirect('/login');
});
