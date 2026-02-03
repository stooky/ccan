<?php
/**
 * Visitor Tracking Endpoint
 *
 * Receives page view beacons, classifies traffic sources (with special
 * attention to LLM referrers), and stores data for analytics.
 *
 * Privacy-conscious: No PII stored, IPs are hashed, respects DNT.
 */

// CORS headers for beacon requests
header('Access-Control-Allow-Origin: ' . ($_SERVER['HTTP_ORIGIN'] ?? '*'));
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// Load configuration
$config = loadConfig();
$trackingConfig = $config['visitor_tracking'] ?? [];

// Check if tracking is enabled
if (empty($trackingConfig['enabled'])) {
    returnPixel();
    exit;
}

// Respect Do Not Track header
if (isset($_SERVER['HTTP_DNT']) && $_SERVER['HTTP_DNT'] === '1') {
    returnPixel();
    exit;
}

// Get tracking data from request
$data = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Beacon API sends as form data
    parse_str(file_get_contents('php://input'), $data);
    if (empty($data)) {
        $data = $_POST;
    }
} else {
    $data = $_GET;
}

// Extract parameters
$page = sanitize($data['p'] ?? '/');
$referrer = sanitize($data['r'] ?? '');
$utmSource = sanitize($data['s'] ?? '');
$utmMedium = sanitize($data['m'] ?? '');
$utmCampaign = sanitize($data['c'] ?? '');

// Skip tracking for admin pages and API calls
if (strpos($page, '/api/') === 0 || strpos($page, 'admin') !== false) {
    returnPixel();
    exit;
}

// Classify traffic source
$classification = classifyTraffic($referrer, $utmSource, $utmMedium, $trackingConfig);

// Generate anonymized session hash (changes daily)
$sessionHash = generateSessionHash();

// Detect device type
$device = detectDevice();

// Create page view record
$pageView = [
    'id' => 'v_' . bin2hex(random_bytes(8)),
    'ts' => date('c'),
    'page' => $page,
    'source' => $classification['source'],
    'sourceDetail' => $classification['detail'],
    'sessionHash' => $sessionHash,
    'device' => $device
];

// Add UTM data if present
if ($utmSource) $pageView['utmSource'] = $utmSource;
if ($utmMedium) $pageView['utmMedium'] = $utmMedium;
if ($utmCampaign) $pageView['utmCampaign'] = $utmCampaign;

// Store the page view
storePageView($pageView, $trackingConfig);

// Return tracking pixel
returnPixel();

// ============================================================================
// Functions
// ============================================================================

/**
 * Load configuration from YAML files
 */
function loadConfig() {
    $configPath = dirname(__DIR__) . '/config.yaml';
    $localConfigPath = dirname(__DIR__) . '/config.local.yaml';

    $config = [];

    if (file_exists($configPath)) {
        $config = parseYaml(file_get_contents($configPath));
    }

    if (file_exists($localConfigPath)) {
        $localConfig = parseYaml(file_get_contents($localConfigPath));
        $config = array_merge_recursive($config, $localConfig);
    }

    return $config;
}

/**
 * Simple YAML parser (handles basic nested structures)
 */
function parseYaml($content) {
    if (function_exists('yaml_parse')) {
        return yaml_parse($content) ?: [];
    }

    // Basic parser for simple YAML
    $result = [];
    $lines = explode("\n", $content);
    $stack = [&$result];
    $indentStack = [-1];

    foreach ($lines as $line) {
        // Skip comments and empty lines
        if (preg_match('/^\s*#/', $line) || trim($line) === '') continue;

        // Get indentation level
        preg_match('/^(\s*)/', $line, $matches);
        $indent = strlen($matches[1]);
        $line = trim($line);

        // Pop stack for decreased indentation
        while ($indent <= end($indentStack) && count($stack) > 1) {
            array_pop($stack);
            array_pop($indentStack);
        }

        // Parse key: value
        if (preg_match('/^([^:]+):\s*(.*)$/', $line, $matches)) {
            $key = trim($matches[1]);
            $value = trim($matches[2]);

            // Remove quotes
            $value = trim($value, '"\'');

            if ($value === '' || $value === '|' || $value === '>') {
                // Nested structure
                $current = &$stack[count($stack) - 1];
                $current[$key] = [];
                $stack[] = &$current[$key];
                $indentStack[] = $indent;
            } else {
                // Simple value
                $current = &$stack[count($stack) - 1];
                // Handle booleans
                if ($value === 'true') $value = true;
                elseif ($value === 'false') $value = false;
                elseif (is_numeric($value)) $value = $value + 0;
                $current[$key] = $value;
            }
        } elseif (preg_match('/^-\s*(.+)$/', $line, $matches)) {
            // Array item
            $value = trim($matches[1], '"\'');
            $current = &$stack[count($stack) - 1];
            $current[] = $value;
        }
    }

    return $result;
}

/**
 * Classify traffic source based on referrer and UTM parameters
 */
function classifyTraffic($referrer, $utmSource, $utmMedium, $config) {
    $llmDomains = $config['llm_domains'] ?? [];
    $llmUtmSources = $config['llm_utm_sources'] ?? [];
    $searchEngines = $config['search_engines'] ?? [];
    $socialDomains = $config['social_domains'] ?? [];

    // Priority 1: UTM parameters indicating LLM
    if ($utmMedium === 'llm' || $utmMedium === 'ai') {
        return ['source' => 'llm', 'detail' => $utmSource ?: 'utm_tagged'];
    }

    $utmSourceLower = strtolower($utmSource);
    foreach ($llmUtmSources as $llmSource) {
        if (strpos($utmSourceLower, strtolower($llmSource)) !== false) {
            return ['source' => 'llm', 'detail' => $utmSource];
        }
    }

    // Parse referrer domain
    if (empty($referrer)) {
        return ['source' => 'direct', 'detail' => 'no_referrer'];
    }

    $referrerHost = parse_url($referrer, PHP_URL_HOST);
    if (!$referrerHost) {
        return ['source' => 'direct', 'detail' => 'invalid_referrer'];
    }

    $referrerHost = strtolower($referrerHost);
    $referrerPath = strtolower(parse_url($referrer, PHP_URL_PATH) ?? '');

    // Priority 2: LLM referrer domains
    foreach ($llmDomains as $domain) {
        $domain = strtolower($domain);
        // Handle domains with paths (e.g., "bing.com/chat")
        if (strpos($domain, '/') !== false) {
            list($domainPart, $pathPart) = explode('/', $domain, 2);
            if (strpos($referrerHost, $domainPart) !== false &&
                strpos($referrerPath, '/' . $pathPart) !== false) {
                return ['source' => 'llm', 'detail' => $domain];
            }
        } else {
            if (strpos($referrerHost, $domain) !== false) {
                return ['source' => 'llm', 'detail' => $referrerHost];
            }
        }
    }

    // Priority 3: Search engines (organic)
    foreach ($searchEngines as $engine) {
        if (strpos($referrerHost, strtolower($engine)) !== false) {
            return ['source' => 'organic', 'detail' => $referrerHost];
        }
    }

    // Priority 4: Social media
    foreach ($socialDomains as $social) {
        if (strpos($referrerHost, strtolower($social)) !== false) {
            return ['source' => 'social', 'detail' => $referrerHost];
        }
    }

    // Priority 5: Self-referral (same site)
    $ownDomain = $_SERVER['HTTP_HOST'] ?? '';
    if ($referrerHost === strtolower($ownDomain)) {
        return ['source' => 'internal', 'detail' => 'self_referral'];
    }

    // Priority 6: Other (unknown referrer)
    return ['source' => 'referral', 'detail' => $referrerHost];
}

/**
 * Generate anonymized session hash
 * Changes daily to prevent long-term tracking
 */
function generateSessionHash() {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    $date = date('Y-m-d');
    $salt = 'ccansam_tracking_salt'; // Static salt for consistency

    return substr(hash('sha256', $ip . $ua . $date . $salt), 0, 16);
}

/**
 * Detect device type from User-Agent
 */
function detectDevice() {
    $ua = strtolower($_SERVER['HTTP_USER_AGENT'] ?? '');

    if (preg_match('/mobile|android|iphone|ipod|blackberry|opera mini|iemobile/i', $ua)) {
        return 'mobile';
    }
    if (preg_match('/tablet|ipad|playbook|silk/i', $ua)) {
        return 'tablet';
    }
    if (preg_match('/bot|crawl|spider|slurp|googlebot|bingbot/i', $ua)) {
        return 'bot';
    }

    return 'desktop';
}

/**
 * Store page view in traffic.json
 */
function storePageView($pageView, $config) {
    // Skip bot traffic
    if ($pageView['device'] === 'bot') {
        return;
    }

    // Skip internal navigation
    if ($pageView['source'] === 'internal') {
        return;
    }

    $dataFile = dirname(__DIR__) . '/data/traffic.json';
    $retentionDays = $config['retention_days'] ?? 30;

    // Lock file for concurrent writes
    $lockFile = $dataFile . '.lock';
    $fp = fopen($lockFile, 'w');
    if (!flock($fp, LOCK_EX)) {
        fclose($fp);
        return;
    }

    try {
        // Load existing data
        $data = loadTrafficData($dataFile);

        // Add new page view to raw events
        array_unshift($data['raw'], $pageView);

        // Update daily aggregates
        $today = date('Y-m-d');
        if (!isset($data['daily'][$today])) {
            $data['daily'][$today] = [
                'total' => 0,
                'bySource' => [],
                'byPage' => [],
                'llmBreakdown' => [],
                'sessions' => []
            ];
        }

        $daily = &$data['daily'][$today];
        $daily['total']++;

        // By source
        $source = $pageView['source'];
        $daily['bySource'][$source] = ($daily['bySource'][$source] ?? 0) + 1;

        // By page
        $page = $pageView['page'];
        $daily['byPage'][$page] = ($daily['byPage'][$page] ?? 0) + 1;

        // LLM breakdown
        if ($source === 'llm') {
            $detail = $pageView['sourceDetail'];
            $daily['llmBreakdown'][$detail] = ($daily['llmBreakdown'][$detail] ?? 0) + 1;
        }

        // Track unique sessions (use a temporary set, convert to count when finalizing)
        if (!isset($daily['_sessionSet'])) {
            $daily['_sessionSet'] = [];
        }
        $daily['_sessionSet'][$pageView['sessionHash']] = true;
        $daily['uniqueSessions'] = count($daily['_sessionSet']);

        // Update totals
        $data['totals']['allTime'] = ($data['totals']['allTime'] ?? 0) + 1;
        $data['totals']['bySource'][$source] = ($data['totals']['bySource'][$source] ?? 0) + 1;

        // Cleanup old raw events (keep last N days)
        $cutoffDate = date('c', strtotime("-{$retentionDays} days"));
        $data['raw'] = array_filter($data['raw'], function($event) use ($cutoffDate) {
            return $event['ts'] >= $cutoffDate;
        });
        $data['raw'] = array_values($data['raw']); // Re-index

        // Limit raw events to prevent file bloat (max 10000)
        if (count($data['raw']) > 10000) {
            $data['raw'] = array_slice($data['raw'], 0, 10000);
        }

        // Cleanup old daily buckets (keep last 90 days)
        $dailyRetentionDays = 90;
        $dailyCutoff = date('Y-m-d', strtotime("-{$dailyRetentionDays} days"));
        foreach (array_keys($data['daily']) as $dateKey) {
            if ($dateKey < $dailyCutoff) {
                unset($data['daily'][$dateKey]);
            }
        }

        // Finalize daily buckets older than today - convert session sets to counts
        foreach ($data['daily'] as $dateKey => &$dayData) {
            if ($dateKey < $today && isset($dayData['_sessionSet'])) {
                // Convert to count and remove the set
                $dayData['uniqueSessions'] = count($dayData['_sessionSet']);
                unset($dayData['_sessionSet']);
            }
            // Also remove legacy 'sessions' object if present
            if (isset($dayData['sessions'])) {
                if (!isset($dayData['uniqueSessions'])) {
                    $dayData['uniqueSessions'] = count($dayData['sessions']);
                }
                unset($dayData['sessions']);
            }
        }
        unset($dayData); // Break reference

        // Calculate rolling totals
        $data['totals']['last7Days'] = calculateRollingTotal($data['daily'], 7);
        $data['totals']['last30Days'] = calculateRollingTotal($data['daily'], 30);

        // Update timestamp
        $data['lastUpdated'] = date('c');

        // Save data
        file_put_contents($dataFile, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

    } finally {
        flock($fp, LOCK_UN);
        fclose($fp);
    }
}

/**
 * Load traffic data from JSON file
 */
function loadTrafficData($dataFile) {
    if (file_exists($dataFile)) {
        $content = file_get_contents($dataFile);
        $data = json_decode($content, true);
        if ($data) {
            return $data;
        }
    }

    // Return default structure
    return [
        'version' => 1,
        'lastUpdated' => date('c'),
        'raw' => [],
        'daily' => [],
        'totals' => [
            'allTime' => 0,
            'last7Days' => 0,
            'last30Days' => 0,
            'bySource' => []
        ]
    ];
}

/**
 * Calculate rolling total for last N days
 */
function calculateRollingTotal($daily, $days) {
    $total = 0;
    $startDate = date('Y-m-d', strtotime("-{$days} days"));

    foreach ($daily as $date => $data) {
        if ($date >= $startDate) {
            $total += $data['total'] ?? 0;
        }
    }

    return $total;
}

/**
 * Sanitize input string
 */
function sanitize($input) {
    if (!is_string($input)) return '';
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

/**
 * Return 1x1 transparent GIF
 */
function returnPixel() {
    header('Content-Type: image/gif');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('Pragma: no-cache');

    // 1x1 transparent GIF
    echo base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');
}
