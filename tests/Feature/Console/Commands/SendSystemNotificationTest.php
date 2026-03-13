<?php

declare(strict_types=1);

use App\Models\SystemNotification;

describe('notification:send command', function (): void {
    it('creates a notification with all options', function (): void {
        $this->artisan('notification:send', [
            '--title' => 'Jira reconnect required',
            '--message' => 'Please reconnect your Jira account.',
            '--variant' => 'warning',
            '--link-url' => '/settings',
            '--link-text' => 'Go to Settings',
        ])->assertExitCode(0);

        $notification = SystemNotification::first();
        expect($notification)->not->toBeNull();
        expect($notification->title)->toBe('Jira reconnect required');
        expect($notification->message)->toBe('Please reconnect your Jira account.');
        expect($notification->variant->value)->toBe('warning');
        expect($notification->link_url)->toBe('/settings');
        expect($notification->link_text)->toBe('Go to Settings');
        expect($notification->is_active)->toBeTrue();
    });

    it('creates a notification with only required fields', function (): void {
        $this->artisan('notification:send', [
            '--title' => 'New feature available',
            '--message' => 'Check out the analytics dashboard.',
        ])->assertExitCode(0);

        $notification = SystemNotification::first();
        expect($notification->variant->value)->toBe('info');
        expect($notification->link_url)->toBeNull();
        expect($notification->link_text)->toBeNull();
        expect($notification->expires_at)->toBeNull();
    });

    it('accepts an expires-at option', function (): void {
        $this->artisan('notification:send', [
            '--title' => 'Temporary notice',
            '--message' => 'Scheduled maintenance tonight.',
            '--expires-at' => '2026-12-31 23:59:59',
        ])->assertExitCode(0);

        $notification = SystemNotification::first();
        expect($notification->expires_at->toDateTimeString())->toBe('2026-12-31 23:59:59');
    });

    it('rejects invalid variant', function (): void {
        $this->artisan('notification:send', [
            '--title' => 'Test',
            '--message' => 'Test message',
            '--variant' => 'invalid',
        ])->assertExitCode(1);

        expect(SystemNotification::count())->toBe(0);
    });

    it('requires title', function (): void {
        $this->artisan('notification:send', [
            '--message' => 'No title provided',
        ])->assertExitCode(1);
    });

    it('requires message', function (): void {
        $this->artisan('notification:send', [
            '--title' => 'No message provided',
        ])->assertExitCode(1);
    });
});
