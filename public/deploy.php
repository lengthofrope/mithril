<?php

declare(strict_types=1);

/**
 * Deployment webhook for Mithril.
 *
 * Pulls the latest code from GitHub and runs Laravel post-deploy commands.
 * Authenticates via Bearer token matched against DEPLOY_TOKEN in .env.
 *
 * This script intentionally runs outside Laravel's request lifecycle
 * because the framework may not be bootable mid-deploy (e.g. after
 * a composer.lock change). Procedural style is acceptable here.
 */

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

$repoPath = dirname(__DIR__);
$env = loadEnv($repoPath . '/.env');

$expectedToken = $env['DEPLOY_TOKEN'] ?? '';
if ($expectedToken === '') {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'DEPLOY_TOKEN not configured.']);
    exit;
}

$providedToken = extractBearerToken();
if ($providedToken === null || !hash_equals($expectedToken, $providedToken)) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Invalid or missing token.']);
    exit;
}

$steps = [
    'git_status'       => 'git status --porcelain',
    'git_fetch'        => 'git fetch origin',
    'git_pull'         => null,
    'composer_install'  => 'composer install --no-dev --optimize-autoloader --no-interaction --ignore-platform-reqs',
    'migrate'          => 'php artisan migrate --force',
    'cache_clear'      => 'php artisan optimize:clear',
    'cache_warm'       => 'php artisan optimize',
    'npm_install'      => 'npm ci',
    'npm_build'        => 'npm run build',
    'npm_prune'        => 'npm prune --omit=dev',
];

$log = [];

$statusOutput = run('git status --porcelain', $log, 'git_status');
$hasLocalChanges = $statusOutput['code'] === 0 && trim($statusOutput['output']) !== '';

if ($hasLocalChanges) {
    if (!runStep('git_stash', 'git stash --include-untracked', $log)) {
        respond(500, 'Failed to stash local changes. Cannot deploy.', $log);
    }
}

if (!runStep('git_fetch', $steps['git_fetch'], $log)) {
    respond(500, 'Git fetch failed.', $log);
}

$branch = trim(shell_exec(sprintf('cd %s && git rev-parse --abbrev-ref HEAD 2>/dev/null', escapeshellarg($repoPath))) ?: '') ?: 'main';
$pullCmd = 'git pull origin ' . escapeshellarg($branch);
if (!runStep('git_pull', $pullCmd, $log)) {
    respond(500, 'Git pull failed.', $log);
}

if ($hasLocalChanges) {
    runStep('git_stash_pop', 'git stash pop', $log);
}

if (!runStep('composer_install', $steps['composer_install'], $log)) {
    respond(500, 'Composer install failed.', $log);
}

if (!runStep('migrate', $steps['migrate'], $log)) {
    respond(500, 'Database migration failed.', $log);
}

runStep('storage_link', 'php artisan storage:link 2>/dev/null || true', $log);
runStep('cache_clear', $steps['cache_clear'], $log);
runStep('cache_warm', $steps['cache_warm'], $log);

if (!runStep('npm_install', $steps['npm_install'], $log)) {
    respond(500, 'npm install failed.', $log);
}

if (!runStep('npm_build', $steps['npm_build'], $log)) {
    respond(500, 'npm build failed.', $log);
}

runStep('npm_prune', $steps['npm_prune'], $log);

respond(200, 'Deployment successful.', $log, $branch);

// ---------------------------------------------------------------------------
// Helper functions
// ---------------------------------------------------------------------------

/**
 * Parse a .env file into a key-value array.
 *
 * @param string $path Absolute path to the .env file.
 * @return array<string, string>
 */
function loadEnv(string $path): array
{
    if (!file_exists($path)) {
        return [];
    }

    $env = [];

    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $trimmed = trim($line);
        if ($trimmed === '' || str_starts_with($trimmed, '#')) {
            continue;
        }

        if (!str_contains($trimmed, '=')) {
            continue;
        }

        [$key, $value] = explode('=', $trimmed, 2);
        $key = trim($key);
        $value = trim($value);

        if (
            (str_starts_with($value, '"') && str_ends_with($value, '"'))
            || (str_starts_with($value, "'") && str_ends_with($value, "'"))
        ) {
            $value = substr($value, 1, -1);
        }

        $env[$key] = $value;
    }

    return $env;
}

/**
 * Extract a Bearer token from the Authorization header.
 *
 * @return string|null
 */
function extractBearerToken(): ?string
{
    $header = '';

    if (function_exists('getallheaders')) {
        $headers = getallheaders();
        $header = $headers['Authorization'] ?? $headers['authorization'] ?? '';
    }

    if ($header === '' && isset($_SERVER['HTTP_AUTHORIZATION'])) {
        $header = $_SERVER['HTTP_AUTHORIZATION'];
    }

    if ($header !== '' && preg_match('/^Bearer\s+(.+)$/i', $header, $matches)) {
        return $matches[1];
    }

    return null;
}

/**
 * Execute a shell command from the repository root and capture output.
 *
 * Explicitly sets PATH and changes to the repo directory so commands
 * work regardless of the web server's default environment.
 *
 * @param string $command
 * @param array<string, string> $log
 * @param string $stepName
 * @return array{code: int, output: string}
 */
function run(string $command, array &$log, string $stepName): array
{
    $repoPath = dirname(__DIR__);
    $home = $_SERVER['HOME'] ?? getenv('HOME') ?: dirname($repoPath, 2);

    $innerCommand = sprintf(
        'export HOME=%s && cd %s && source $HOME/.bashrc 2>/dev/null; source $HOME/.nvm/nvm.sh 2>/dev/null; %s',
        escapeshellarg($home),
        escapeshellarg($repoPath),
        $command,
    );

    $wrappedCommand = sprintf('bash -c %s 2>&1', escapeshellarg($innerCommand));

    $output = [];
    $code = 0;
    exec($wrappedCommand, $output, $code);

    $outputStr = implode("\n", $output);
    $log[$stepName] = $outputStr;

    return ['code' => $code, 'output' => $outputStr];
}

/**
 * Run a deploy step and return whether it succeeded.
 *
 * @param string $name
 * @param string $command
 * @param array<string, string> $log
 * @return bool
 */
function runStep(string $name, string $command, array &$log): bool
{
    $result = run($command, $log, $name);

    return $result['code'] === 0;
}

/**
 * Send a JSON response and terminate.
 *
 * @param int $status
 * @param string $message
 * @param array<string, string> $log
 * @param string|null $branch
 * @return never
 */
function respond(int $status, string $message, array $log, ?string $branch = null): never
{
    http_response_code($status);

    $payload = [
        'success'   => $status === 200,
        'message'   => $message,
        'timestamp' => date('c'),
        'log'       => $log,
    ];

    if ($branch !== null) {
        $payload['branch'] = $branch;
    }

    echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}
