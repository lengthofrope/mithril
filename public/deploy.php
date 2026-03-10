<?php
/**
 * Deployment Script
 * 
 * This script updates the repository to the latest version from GitHub.
 * It requires a valid DEPLOY_TOKEN to be provided via POST request.
 */

// Set content type
header('Content-Type: application/json');

// Load environment variables from .env file
function loadEnv($path) {
    if (!file_exists($path)) {
        return [];
    }
    
    $env = [];
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    
    foreach ($lines as $line) {
        // Skip comments
        if (strpos(trim($line), '#') === 0) {
            continue;
        }
        
        // Parse KEY=VALUE
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            
            // Remove surrounding quotes if present
            if ((substr($value, 0, 1) === '"' && substr($value, -1) === '"') ||
                (substr($value, 0, 1) === "'" && substr($value, -1) === "'")) {
                $value = substr($value, 1, -1);
            }
            
            $env[$key] = $value;
        }
    }
    
    return $env;
}

// Load .env file from project root
$envPath = dirname(__DIR__) . '/.env';
$env = loadEnv($envPath);

// Get the expected token from environment
$expectedToken = $env['DEPLOY_TOKEN'] ?? null;

if (!$expectedToken) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'DEPLOY_TOKEN not configured in .env file'
    ]);
    exit;
}

// Get the token from POST request or Authorization header
$providedToken = null;

// Check Authorization header first (more secure)
if (function_exists('getallheaders')) {
    $headers = getallheaders();
    if (isset($headers['Authorization'])) {
        $authHeader = $headers['Authorization'];
        if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            $providedToken = $matches[1];
        }
    }
} elseif (isset($_SERVER['HTTP_AUTHORIZATION'])) {
    // Fallback for environments where getallheaders() is not available
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'];
    if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
        $providedToken = $matches[1];
    }
}

// Fall back to POST/GET parameters
if (!$providedToken) {
    $providedToken = $_POST['token'] ?? $_GET['token'] ?? null;
}

// Validate the token using constant-time comparison to prevent timing attacks
if (!$providedToken || !hash_equals($expectedToken, $providedToken)) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid or missing token'
    ]);
    exit;
}

// Change to the repository root directory
$repoPath = dirname(__DIR__);
chdir($repoPath);

// Check git status first
$statusOutput = [];
exec('git status --porcelain 2>&1', $statusOutput);

if (!empty($statusOutput)) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Repository has uncommitted changes or conflicts. Cannot deploy.',
    ]);
    exit;
}

// Execute git pull
$output = [];
$returnCode = 0;

// First, fetch the latest changes
exec('git fetch origin 2>&1', $output, $returnCode);

if ($returnCode !== 0) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Git fetch failed',
    ]);
    exit;
}

// Get the current branch name
$currentBranchRaw = trim(shell_exec('git rev-parse --abbrev-ref HEAD 2>/dev/null') ?: '') ?: 'main';

// Sanitize branch name for use in shell command
$currentBranch = escapeshellarg($currentBranchRaw);

// Clear output array before pull command
$output = [];

// Then, pull the changes from current branch
exec("git pull origin {$currentBranch} 2>&1", $output, $returnCode);

if ($returnCode !== 0) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Git pull failed',
    ]);
    exit;
}

// Return success response (sanitized output)
http_response_code(200);
echo json_encode([
    'success' => true,
    'message' => 'Repository updated successfully',
    'branch' => $currentBranchRaw,
    'timestamp' => date('Y-m-d H:i:s')
]);