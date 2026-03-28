<?php

declare(strict_types=1);

use App\Enums\ApiAbility;
use App\Enums\ApiScope;
use App\Models\User;

describe('GET /settings/api', function (): void {
    it('renders the API settings page for authenticated users', function (): void {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/settings/api');

        $response->assertOk()
            ->assertViewIs('pages.settings.api');
    });

    it('redirects unauthenticated users to login', function (): void {
        $response = $this->get('/settings/api');

        $response->assertRedirect('/login');
    });

    it('passes existing tokens to the view', function (): void {
        $user = User::factory()->create();
        $user->createToken('My Token', ['tasks:read']);

        $response = $this->actingAs($user)->get('/settings/api');

        $response->assertViewHas('tokens');
        expect($response->viewData('tokens'))->toHaveCount(1);
    });

    it('passes ability and scope data to the view', function (): void {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/settings/api');

        $response->assertViewHas('groupedAbilities');
        $response->assertViewHas('scopes');
    });
});

describe('POST /settings/api/tokens', function (): void {
    it('creates a token with a scope tier and returns plaintext', function (): void {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/settings/api/tokens', [
            'name' => 'My API Key',
            'scope' => 'read-only',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['success', 'data' => ['plaintext_token', 'token']]);
        expect($response->json('success'))->toBeTrue();
        expect($response->json('data.plaintext_token'))->toBeString()->not->toBeEmpty();
    });

    it('expands read-only scope to correct abilities', function (): void {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/settings/api/tokens', [
            'name' => 'Read Only Key',
            'scope' => 'read-only',
        ]);

        $token = $user->tokens()->first();
        $expectedAbilities = ApiScope::ReadOnly->abilityValues();
        expect($token->abilities)->toBe($expectedAbilities);
    });

    it('expands read-write scope to correct abilities', function (): void {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/settings/api/tokens', [
            'name' => 'RW Key',
            'scope' => 'read-write',
        ]);

        $token = $user->tokens()->first();
        $expectedAbilities = ApiScope::ReadWrite->abilityValues();
        expect($token->abilities)->toBe($expectedAbilities);
    });

    it('expands full-access scope to correct abilities', function (): void {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/settings/api/tokens', [
            'name' => 'Full Key',
            'scope' => 'full-access',
        ]);

        $token = $user->tokens()->first();
        $expectedAbilities = ApiScope::FullAccess->abilityValues();
        expect($token->abilities)->toBe($expectedAbilities);
    });

    it('creates a token with custom abilities', function (): void {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/settings/api/tokens', [
            'name' => 'Custom Key',
            'abilities' => ['tasks:read', 'notes:read', 'notes:write'],
        ]);

        $response->assertOk();
        $token = $user->tokens()->first();
        expect($token->abilities)->toBe(['tasks:read', 'notes:read', 'notes:write']);
    });

    it('returns 422 when name is empty', function (): void {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/settings/api/tokens', [
            'name' => '',
            'scope' => 'read-only',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    });

    it('returns 422 when name exceeds 100 characters', function (): void {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/settings/api/tokens', [
            'name' => str_repeat('a', 101),
            'scope' => 'read-only',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    });

    it('returns 422 when neither scope nor abilities are provided', function (): void {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/settings/api/tokens', [
            'name' => 'Incomplete Token',
        ]);

        $response->assertUnprocessable();
    });

    it('returns 422 for an invalid scope value', function (): void {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/settings/api/tokens', [
            'name' => 'Bad Scope',
            'scope' => 'admin',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['scope']);
    });

    it('returns 422 for invalid ability values', function (): void {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/settings/api/tokens', [
            'name' => 'Bad Abilities',
            'abilities' => ['tasks:read', 'invalid:ability'],
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['abilities.1']);
    });

    it('allows multiple tokens with the same name', function (): void {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/settings/api/tokens', [
            'name' => 'Duplicate Name',
            'scope' => 'read-only',
        ]);

        $response = $this->actingAs($user)->postJson('/settings/api/tokens', [
            'name' => 'Duplicate Name',
            'scope' => 'read-write',
        ]);

        $response->assertOk();
        expect($user->tokens()->count())->toBe(2);
    });

    it('requires authentication', function (): void {
        $response = $this->postJson('/settings/api/tokens', [
            'name' => 'Ghost Key',
            'scope' => 'read-only',
        ]);

        $response->assertUnauthorized();
    });
});

describe('DELETE /settings/api/tokens/{tokenId}', function (): void {
    it('revokes a specific token', function (): void {
        $user = User::factory()->create();
        $token = $user->createToken('Revoke Me', ['tasks:read']);
        $tokenId = $token->accessToken->id;

        $response = $this->actingAs($user)->deleteJson("/settings/api/tokens/{$tokenId}");

        $response->assertOk()
            ->assertJson(['success' => true]);
        expect($user->tokens()->count())->toBe(0);
    });

    it('cannot revoke another user token', function (): void {
        $owner = User::factory()->create();
        $attacker = User::factory()->create();
        $token = $owner->createToken('Secret Key', ['tasks:read']);
        $tokenId = $token->accessToken->id;

        $response = $this->actingAs($attacker)->deleteJson("/settings/api/tokens/{$tokenId}");

        $response->assertNotFound();
        expect($owner->tokens()->count())->toBe(1);
    });

    it('returns 404 for a non-existent token', function (): void {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->deleteJson('/settings/api/tokens/999');

        $response->assertNotFound();
    });

    it('requires authentication', function (): void {
        $response = $this->deleteJson('/settings/api/tokens/1');

        $response->assertUnauthorized();
    });
});

describe('DELETE /settings/api/tokens (revoke all)', function (): void {
    it('revokes all tokens for the authenticated user', function (): void {
        $user = User::factory()->create();
        $user->createToken('Key 1', ['tasks:read']);
        $user->createToken('Key 2', ['notes:read']);

        $response = $this->actingAs($user)->deleteJson('/settings/api/tokens');

        $response->assertOk()
            ->assertJson(['success' => true]);
        expect($user->tokens()->count())->toBe(0);
    });

    it('does not affect other users tokens', function (): void {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $user->createToken('User Key', ['tasks:read']);
        $other->createToken('Other Key', ['tasks:read']);

        $this->actingAs($user)->deleteJson('/settings/api/tokens');

        expect($other->tokens()->count())->toBe(1);
    });

    it('requires authentication', function (): void {
        $response = $this->deleteJson('/settings/api/tokens');

        $response->assertUnauthorized();
    });
});

describe('token list metadata', function (): void {
    it('returns token metadata with scope description', function (): void {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/settings/api/tokens', [
            'name' => 'Metadata Token',
            'scope' => 'read-only',
        ]);

        $response = $this->actingAs($user)->get('/settings/api');

        $tokens = $response->viewData('tokens');
        expect($tokens)->toHaveCount(1);
        expect($tokens->first()->name)->toBe('Metadata Token');
    });
});
