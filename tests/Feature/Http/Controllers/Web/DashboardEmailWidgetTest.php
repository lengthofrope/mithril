<?php

declare(strict_types=1);

use App\Models\Email;
use App\Models\User;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('shows email widget on dashboard when user has microsoft connection', function (): void {
    $user = User::factory()->create(['microsoft_id' => 'ms-123']);

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertOk();
    $response->assertSee('Flagged emails');
});

it('hides email widget on dashboard when user has no microsoft connection', function (): void {
    $user = User::factory()->create(['microsoft_id' => null]);

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertOk();
    $response->assertDontSee('Flagged emails');
});

it('dashboard email API returns flagged emails ordered by due date', function (): void {
    $user = User::factory()->create(['microsoft_id' => 'ms-123']);

    Email::factory()->for($user)->flagged()->create([
        'subject' => 'Due soon',
        'flag_due_date' => now()->addDay(),
    ]);
    Email::factory()->for($user)->flagged()->create([
        'subject' => 'No due date',
        'flag_due_date' => null,
    ]);
    Email::factory()->for($user)->create([
        'subject' => 'Not flagged',
        'is_flagged' => false,
    ]);

    $response = $this->actingAs($user)->getJson('/api/v1/emails/dashboard');

    $response->assertOk();
    $response->assertJsonCount(2, 'data');
    expect($response->json('data.0.subject'))->toBe('Due soon');
    expect($response->json('data.1.subject'))->toBe('No due date');
});
