<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\JiraCloudService;
use App\Services\JiraUserService;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    $this->user = User::factory()->create([
        'jira_cloud_id'         => 'cloud-123',
        'jira_account_id'       => 'account-abc',
        'jira_access_token'     => 'valid-token',
        'jira_refresh_token'    => 'valid-refresh',
        'jira_token_expires_at' => now()->addHour(),
    ]);
});

test('resolveDisplayNames returns display names for given account IDs', function () {
    $mockCloud = Mockery::mock(JiraCloudService::class);
    $mockCloud->shouldReceive('fetchUsersBulk')
        ->once()
        ->with($this->user, ['acc-1', 'acc-2'])
        ->andReturn(collect([
            ['accountId' => 'acc-1', 'displayName' => 'Alice Smith'],
            ['accountId' => 'acc-2', 'displayName' => 'Bob Jones'],
        ]));

    $service = new JiraUserService($mockCloud);
    $result  = $service->resolveDisplayNames($this->user, ['acc-1', 'acc-2']);

    expect($result)
        ->toHaveKey('acc-1', 'Alice Smith')
        ->toHaveKey('acc-2', 'Bob Jones');
});

test('resolveDisplayNames returns cached values without API call', function () {
    Cache::put('jira_user:cloud-123:acc-1', 'Cached Alice', 3600);

    $mockCloud = Mockery::mock(JiraCloudService::class);
    $mockCloud->shouldNotReceive('fetchUsersBulk');

    $service = new JiraUserService($mockCloud);
    $result  = $service->resolveDisplayNames($this->user, ['acc-1']);

    expect($result)->toHaveKey('acc-1', 'Cached Alice');
});

test('resolveDisplayNames fetches only uncached IDs from API', function () {
    Cache::put('jira_user:cloud-123:acc-1', 'Cached Alice', 3600);

    $mockCloud = Mockery::mock(JiraCloudService::class);
    $mockCloud->shouldReceive('fetchUsersBulk')
        ->once()
        ->with($this->user, ['acc-2'])
        ->andReturn(collect([
            ['accountId' => 'acc-2', 'displayName' => 'Bob Jones'],
        ]));

    $service = new JiraUserService($mockCloud);
    $result  = $service->resolveDisplayNames($this->user, ['acc-1', 'acc-2']);

    expect($result)
        ->toHaveKey('acc-1', 'Cached Alice')
        ->toHaveKey('acc-2', 'Bob Jones');
});

test('resolveDisplayNames caches fetched values with 1-hour TTL', function () {
    $mockCloud = Mockery::mock(JiraCloudService::class);
    $mockCloud->shouldReceive('fetchUsersBulk')
        ->once()
        ->andReturn(collect([
            ['accountId' => 'acc-1', 'displayName' => 'Alice Smith'],
        ]));

    $service = new JiraUserService($mockCloud);
    $service->resolveDisplayNames($this->user, ['acc-1']);

    expect(Cache::get('jira_user:cloud-123:acc-1'))->toBe('Alice Smith');
});

test('resolveDisplayNames returns fallback for missing accounts', function () {
    $mockCloud = Mockery::mock(JiraCloudService::class);
    $mockCloud->shouldReceive('fetchUsersBulk')
        ->once()
        ->andReturn(collect([]));

    $service = new JiraUserService($mockCloud);
    $result  = $service->resolveDisplayNames($this->user, ['acc-unknown']);

    expect($result)->toHaveKey('acc-unknown', 'Unknown user');
});

test('resolveDisplayNames handles API failure gracefully', function () {
    $mockCloud = Mockery::mock(JiraCloudService::class);
    $mockCloud->shouldReceive('fetchUsersBulk')
        ->once()
        ->andThrow(new RuntimeException('Jira Cloud API rate limit exceeded (429). Retry-After: 30 seconds.'));

    $service = new JiraUserService($mockCloud);
    $result  = $service->resolveDisplayNames($this->user, ['acc-1', 'acc-2']);

    expect($result)
        ->toHaveKey('acc-1', 'Unknown user')
        ->toHaveKey('acc-2', 'Unknown user');
});

test('resolveDisplayNames deduplicates input account IDs', function () {
    $mockCloud = Mockery::mock(JiraCloudService::class);
    $mockCloud->shouldReceive('fetchUsersBulk')
        ->once()
        ->with($this->user, ['acc-1'])
        ->andReturn(collect([
            ['accountId' => 'acc-1', 'displayName' => 'Alice'],
        ]));

    $service = new JiraUserService($mockCloud);
    $result  = $service->resolveDisplayNames($this->user, ['acc-1', 'acc-1', 'acc-1']);

    expect($result)->toHaveCount(1)
        ->toHaveKey('acc-1', 'Alice');
});

test('resolveDisplayNames filters out null account IDs', function () {
    $mockCloud = Mockery::mock(JiraCloudService::class);
    $mockCloud->shouldNotReceive('fetchUsersBulk');

    $service = new JiraUserService($mockCloud);
    $result  = $service->resolveDisplayNames($this->user, [null, '', null]);

    expect($result)->toBeEmpty();
});

test('resolveDisplayNames returns empty array for empty input', function () {
    $mockCloud = Mockery::mock(JiraCloudService::class);
    $mockCloud->shouldNotReceive('fetchUsersBulk');

    $service = new JiraUserService($mockCloud);
    $result  = $service->resolveDisplayNames($this->user, []);

    expect($result)->toBeEmpty();
});
