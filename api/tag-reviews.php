<?php
/**
 * Tag Reviews API Endpoint
 *
 * Runs the OpenAI tagging script and returns the result.
 * Called from the admin panel "Tag Reviews" button.
 */

header('Content-Type: application/json');

// Load config for auth
$configPath = dirname(__DIR__) . '/config.yaml';
$localConfigPath = dirname(__DIR__) . '/config.local.yaml';

function parseSimpleYaml($path) {
    $content = file_get_contents($path);
    $config = [];

    if (preg_match('/secret_path:\s*["\']?([^"\'\n]+)["\']?/', $content, $matches)) {
        $config['admin']['secret_path'] = trim($matches[1]);
    }

    return $config;
}

$config = parseSimpleYaml($configPath);
if (file_exists($localConfigPath)) {
    $localConfig = parseSimpleYaml($localConfigPath);
    $config = array_replace_recursive($config, $localConfig);
}

// Check admin auth
$secret = $config['admin']['secret_path'] ?? '';
$providedKey = $_GET['key'] ?? '';

if (!$secret || $providedKey !== $secret) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Run the tag-reviews script
$rootDir = dirname(__DIR__);
$output = [];
$returnCode = 0;

// Try npm run tag-reviews first, fall back to node directly
$commands = [
    "cd \"$rootDir\" && npm run tag-reviews 2>&1",
    "cd \"$rootDir\" && node scripts/tag-reviews.js 2>&1"
];

$success = false;
$finalOutput = '';

foreach ($commands as $cmd) {
    exec($cmd, $output, $returnCode);
    $finalOutput = implode("\n", $output);

    if ($returnCode === 0) {
        $success = true;
        break;
    }
    $output = []; // Reset for next attempt
}

if ($success) {
    echo json_encode([
        'success' => true,
        'message' => 'Reviews tagged successfully',
        'output' => $finalOutput
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Failed to tag reviews',
        'output' => $finalOutput,
        'hint' => 'Make sure OPENAI_API_KEY is set in .env.local and Node.js is installed'
    ]);
}
