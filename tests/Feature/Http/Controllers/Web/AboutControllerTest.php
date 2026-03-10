<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('about page returns 200 for authenticated user', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/about');

    $response->assertOk();
});

test('about page redirects unauthenticated user to login', function () {
    /** @var \Tests\TestCase $this */
    $response = $this->get('/about');

    $response->assertRedirect('/login');
});

test('about page renders the correct view', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/about');

    $response->assertViewIs('pages.about.index');
});

test('about page passes currentVersion and releases to view', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/about');

    $response->assertViewHas('currentVersion');
    $response->assertViewHas('releases');
    expect($response->viewData('currentVersion'))->toBeString()->not->toBeEmpty();
    expect($response->viewData('releases'))->toBeArray()->not->toBeEmpty();
});

test('about page uses first changelog entry as current version including unreleased', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/about');

    $version = $response->viewData('currentVersion');
    $releases = $response->viewData('releases');

    expect($version)->toMatch('/^\d+\.\d+\.\d+$/');
    expect($version)->toBe($releases[0]['version']);
});
