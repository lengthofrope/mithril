<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\DB;

describe('user:disable command', function (): void {
    it('disables an active user', function (): void {
        $user = User::factory()->create(['email' => 'john@example.com']);

        $this->artisan('user:disable', ['email' => 'john@example.com'])
            ->assertExitCode(0);

        $user->refresh();
        expect($user->is_active)->toBeFalse();
    });

    it('shows error for non-existent email', function (): void {
        $this->artisan('user:disable', ['email' => 'nobody@example.com'])
            ->assertExitCode(1);
    });

    it('shows info message when user is already disabled', function (): void {
        User::factory()->create([
            'email' => 'john@example.com',
            'is_active' => false,
        ]);

        $this->artisan('user:disable', ['email' => 'john@example.com'])
            ->assertExitCode(0);
    });

    it('invalidates all sessions for the disabled user', function (): void {
        $user = User::factory()->create(['email' => 'john@example.com']);

        DB::table('sessions')->insert([
            'id' => 'session-1',
            'user_id' => $user->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Test',
            'payload' => base64_encode('test'),
            'last_activity' => time(),
        ]);

        $this->artisan('user:disable', ['email' => 'john@example.com'])
            ->assertExitCode(0);

        expect(DB::table('sessions')->where('user_id', $user->id)->count())->toBe(0);
    });

    it('does not affect other users sessions', function (): void {
        $target = User::factory()->create(['email' => 'john@example.com']);
        $other = User::factory()->create();

        DB::table('sessions')->insert([
            ['id' => 'session-target', 'user_id' => $target->id, 'ip_address' => '127.0.0.1', 'user_agent' => 'Test', 'payload' => base64_encode('test'), 'last_activity' => time()],
            ['id' => 'session-other', 'user_id' => $other->id, 'ip_address' => '127.0.0.1', 'user_agent' => 'Test', 'payload' => base64_encode('test'), 'last_activity' => time()],
        ]);

        $this->artisan('user:disable', ['email' => 'john@example.com']);

        expect(DB::table('sessions')->where('user_id', $other->id)->count())->toBe(1);
    });
});
