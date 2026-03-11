<?php

declare(strict_types=1);

use App\Models\User;

describe('user:list command', function (): void {
    it('returns success exit code', function (): void {
        User::factory()->create();

        $this->artisan('user:list')
            ->assertExitCode(0);
    });

    it('displays user name, email, and status', function (): void {
        User::factory()->create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);

        $this->artisan('user:list')
            ->expectsTable(
                ['Name', 'Email', 'Active'],
                [['John Doe', 'john@example.com', 'Yes']],
            )
            ->assertExitCode(0);
    });

    it('shows disabled users as No', function (): void {
        User::factory()->disabled()->create([
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
        ]);

        $this->artisan('user:list')
            ->expectsTable(
                ['Name', 'Email', 'Active'],
                [['Jane Doe', 'jane@example.com', 'No']],
            )
            ->assertExitCode(0);
    });

    it('lists multiple users', function (): void {
        User::factory()->create(['name' => 'Alice', 'email' => 'alice@example.com']);
        User::factory()->disabled()->create(['name' => 'Bob', 'email' => 'bob@example.com']);

        $this->artisan('user:list')
            ->expectsTable(
                ['Name', 'Email', 'Active'],
                [
                    ['Alice', 'alice@example.com', 'Yes'],
                    ['Bob', 'bob@example.com', 'No'],
                ],
            )
            ->assertExitCode(0);
    });

    it('shows info message when no users exist', function (): void {
        $this->artisan('user:list')
            ->expectsOutputToContain('No users found')
            ->assertExitCode(0);
    });
});
