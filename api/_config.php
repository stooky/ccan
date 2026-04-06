<?php
/**
 * Shared config loader for multi-site PHP backend
 *
 * Determines the site root directory:
 * 1. SITE_ROOT env var (set by nginx fastcgi_param for multi-site)
 * 2. Falls back to dirname(__DIR__) (single-site, current behavior)
 *
 * Usage in any api/*.php file:
 *   require_once __DIR__ . '/_config.php';
 *   // $siteRoot, $configPath, $localConfigPath are now available
 *
 * For multi-site: nginx sets SITE_ROOT via fastcgi_param:
 *   fastcgi_param SITE_ROOT /var/www/sites/example.com;
 */

$siteRoot = getenv('SITE_ROOT') ?: dirname(__DIR__);
$configPath = $siteRoot . '/config.yaml';
$localConfigPath = $siteRoot . '/config.local.yaml';

// Define constant so deeply nested code can also use it
if (!defined('SITE_ROOT')) {
    define('SITE_ROOT', $siteRoot);
}

/**
 * Log an admin action for audit trail.
 * Writes to data/admin-audit.log (one JSON line per action).
 */
function auditLog($action, $details = []) {
    $logFile = (defined('SITE_ROOT') ? SITE_ROOT : dirname(__DIR__)) . '/data/admin-audit.log';
    $entry = json_encode([
        'ts' => date('c'),
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'action' => $action,
        'details' => $details,
    ]) . "\n";
    @file_put_contents($logFile, $entry, FILE_APPEND | LOCK_EX);
}
