<?php

declare(strict_types=1);

use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;

uses(RefreshDatabase::class);

test('export returns 200 with success flag', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();

    $response = $this->actingAs($user)->getJson('/api/v1/export');

    $response->assertOk()->assertJson(['success' => true]);
});

test('export returns structured data with all entity keys', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();

    $response = $this->actingAs($user)->getJson('/api/v1/export');

    $response->assertOk()
        ->assertJsonStructure([
            'success',
            'data' => [
                'exported_at',
                'version',
                'data' => [
                    'teams',
                    'team_members',
                    'task_categories',
                    'task_groups',
                    'tasks',
                    'follow_ups',
                    'bilas',
                    'bila_prep_items',
                    'agreements',
                    'notes',
                    'note_tags',
                    'weekly_reflections',
                ],
            ],
        ]);
});

test('export includes existing tasks in response', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();
    Task::factory()->count(2)->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->getJson('/api/v1/export');

    $response->assertOk();
    expect($response->json('data.data.tasks'))->toHaveCount(2);
});

test('export includes existing teams in response', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();
    Team::factory()->count(3)->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->getJson('/api/v1/export');

    expect($response->json('data.data.teams'))->toHaveCount(3);
});

test('export includes version field', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();

    $response = $this->actingAs($user)->getJson('/api/v1/export');

    expect($response->json('data.version'))->toBe('1.0');
});

test('export includes exported_at timestamp', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();

    $response = $this->actingAs($user)->getJson('/api/v1/export');

    expect($response->json('data.exported_at'))->not->toBeNull()->toBeString();
});

test('export returns success message', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();

    $response = $this->actingAs($user)->getJson('/api/v1/export');

    $response->assertJson(['message' => 'Export successful.']);
});

test('import returns 422 when data field is missing', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();

    $response = $this->actingAs($user)->postJson('/api/v1/import', []);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['data']);
});

test('import returns 422 when data is not an array', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();

    $response = $this->actingAs($user)->postJson('/api/v1/import', ['data' => 'not-an-array']);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['data']);
});

test('import returns 422 when data is an empty array', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();

    $response = $this->actingAs($user)->postJson('/api/v1/import', ['data' => []]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['data']);
});

test('import creates teams from payload', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();

    $response = $this->actingAs($user)->postJson('/api/v1/import', [
        'data' => [
            'teams' => [
                ['id' => 1, 'name' => 'Alpha Team', 'created_at' => now()->toDateTimeString(), 'updated_at' => now()->toDateTimeString()],
            ],
        ],
    ]);

    $response->assertOk();
    expect(Team::count())->toBe(1);
    expect(Team::first()->name)->toBe('Alpha Team');
});

test('import replaces existing records with imported data', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();
    Team::factory()->create(['name' => 'Old Team', 'user_id' => $user->id]);

    $response = $this->actingAs($user)->postJson('/api/v1/import', [
        'data' => [
            'teams' => [
                ['id' => 99, 'name' => 'New Team', 'created_at' => now()->toDateTimeString(), 'updated_at' => now()->toDateTimeString()],
            ],
        ],
    ]);

    $response->assertOk();
    expect(Team::count())->toBe(1);
    expect(Team::first()->name)->toBe('New Team');
});

test('import handles empty data keys gracefully', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();

    $response = $this->actingAs($user)->postJson('/api/v1/import', [
        'data' => [
            'teams' => [],
            'tasks' => [],
        ],
    ]);

    $response->assertOk();
});

test('web export returns a downloadable json file with content-disposition header', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/settings/export');

    $response->assertOk();
    $response->assertHeader('Content-Type', 'application/json');
    $response->assertHeader('Content-Disposition');
    expect($response->headers->get('Content-Disposition'))->toContain('attachment');
    expect($response->headers->get('Content-Disposition'))->toContain('.json');
});

test('web export file contains valid export structure', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();
    Team::factory()->count(2)->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->get('/settings/export');

    $json = json_decode($response->streamedContent(), true);
    expect($json)->toHaveKeys(['exported_at', 'version', 'data']);
    expect($json['data']['teams'])->toHaveCount(2);
});

test('web import accepts a json file upload and imports data', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();

    $payload = json_encode([
        'exported_at' => now()->toIso8601String(),
        'version' => '1.0',
        'data' => [
            'teams' => [
                ['id' => 1, 'name' => 'Imported Team', 'created_at' => now()->toDateTimeString(), 'updated_at' => now()->toDateTimeString()],
            ],
        ],
    ]);

    $file = UploadedFile::fake()->createWithContent('export.json', $payload);

    $response = $this->actingAs($user)->post('/settings/import', [
        'import_file' => $file,
    ]);

    $response->assertRedirect(route('settings.index'));
    expect(Team::count())->toBe(1);
    expect(Team::first()->name)->toBe('Imported Team');
});

test('web import shows error when no file is uploaded', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/settings/import', []);

    $response->assertSessionHasErrors(['import_file']);
});

test('web import shows error when file contains invalid json', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();

    $file = UploadedFile::fake()->createWithContent('export.json', 'not-valid-json');

    $response = $this->actingAs($user)->post('/settings/import', [
        'import_file' => $file,
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('error');
});

test('web import shows error when json has no data key', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();

    $file = UploadedFile::fake()->createWithContent('export.json', json_encode(['version' => '1.0']));

    $response = $this->actingAs($user)->post('/settings/import', [
        'import_file' => $file,
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('error');
});
