<?php
/**
 * C-Can Sam Backup API
 *
 * Creates tar.gz backups of data files and emails them.
 *
 * Endpoints:
 *   POST ?action=create   - Create backup and email it
 *   POST ?action=delete   - Delete a specific backup (with confirmation)
 *   GET  ?action=list     - List existing backups
 */

header('Content-Type: application/json');

// Load configuration
$configPath = dirname(__DIR__) . '/config.yaml';
$localConfigPath = dirname(__DIR__) . '/config.local.yaml';
$dataDir = dirname(__DIR__) . '/data';
$backupsDir = $dataDir . '/backups';

// Simple YAML parser (same as admin.php)
function parseYaml($content) {
    $config = [];
    $lines = explode("\n", $content);
    $currentSection = null;

    foreach ($lines as $line) {
        $trimmed = trim($line);
        if (empty($trimmed) || $trimmed[0] === '#') continue;

        // Section header
        if (!preg_match('/^\s/', $line) && substr($trimmed, -1) === ':' && strpos($trimmed, '"') === false) {
            $currentSection = rtrim($trimmed, ':');
            $config[$currentSection] = [];
            continue;
        }

        // Key-value pair
        if (preg_match('/^(\w+):\s*["\']?([^"\'\n]+)["\']?$/', $trimmed, $match)) {
            if ($currentSection) {
                $config[$currentSection][$match[1]] = trim($match[2]);
            } else {
                $config[$match[1]] = trim($match[2]);
            }
        }
    }

    return $config;
}

// Load config
$config = [];
if (file_exists($configPath)) {
    $config = parseYaml(file_get_contents($configPath));
}
if (file_exists($localConfigPath)) {
    $localConfig = parseYaml(file_get_contents($localConfigPath));
    $config = array_replace_recursive($config, $localConfig);
}

// Auth check
$secretPath = $config['admin']['secret_path'] ?? 'ccan-admin-2024';
$providedKey = $_GET['key'] ?? $_POST['key'] ?? '';

if ($providedKey !== $secretPath) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Files to backup (unique data)
$backupFiles = [
    'data/submissions.json',
    'data/reviews.json',
    'data/inventory.json',
    'data/spam-log.json',
    'data/quote-requests.json',
    'config.yaml'
];

// Max backups to keep
$maxBackups = 3;

// Create backups directory if needed
if (!is_dir($backupsDir)) {
    mkdir($backupsDir, 0755, true);
}

$action = $_GET['action'] ?? $_POST['action'] ?? 'list';

/**
 * List existing backups
 */
function listBackups($backupsDir) {
    $backups = [];

    if (!is_dir($backupsDir)) {
        return $backups;
    }

    $files = glob($backupsDir . '/*.tgz');
    foreach ($files as $file) {
        $backups[] = [
            'filename' => basename($file),
            'path' => $file,
            'size' => filesize($file),
            'sizeFormatted' => formatSize(filesize($file)),
            'created' => filemtime($file),
            'createdFormatted' => date('Y-m-d H:i:s', filemtime($file))
        ];
    }

    // Sort by creation time descending (newest first)
    usort($backups, fn($a, $b) => $b['created'] - $a['created']);

    return $backups;
}

/**
 * Format file size
 */
function formatSize($bytes) {
    if ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    }
    return $bytes . ' bytes';
}

/**
 * Get file stats for backup summary
 */
function getFileStats($backupFiles, $rootDir) {
    $stats = [];

    foreach ($backupFiles as $file) {
        $fullPath = $rootDir . '/' . $file;
        $stat = [
            'file' => $file,
            'exists' => file_exists($fullPath),
            'size' => 0,
            'count' => 0
        ];

        if ($stat['exists']) {
            $stat['size'] = filesize($fullPath);

            // Try to get record count for JSON files
            if (substr($file, -5) === '.json') {
                $content = file_get_contents($fullPath);
                $data = json_decode($content, true);
                if (is_array($data)) {
                    if (isset($data['reviews'])) {
                        $stat['count'] = count($data['reviews']);
                        $stat['type'] = 'reviews';
                    } elseif (isset($data['items'])) {
                        $stat['count'] = count($data['items']);
                        $stat['type'] = 'items';
                    } else {
                        $stat['count'] = count($data);
                        $stat['type'] = 'entries';
                    }
                }
            } else {
                $stat['type'] = 'config';
            }
        }

        $stats[] = $stat;
    }

    return $stats;
}

/**
 * Create a tar.gz backup
 */
function createBackup($backupFiles, $rootDir, $backupsDir) {
    $timestamp = date('Y-m-d-His');
    $filename = "ccansam-backup-{$timestamp}.tgz";
    $filepath = $backupsDir . '/' . $filename;

    // Collect existing files
    $filesToBackup = [];
    foreach ($backupFiles as $file) {
        $fullPath = $rootDir . '/' . $file;
        if (file_exists($fullPath)) {
            $filesToBackup[] = $file;
        }
    }

    if (empty($filesToBackup)) {
        return ['success' => false, 'message' => 'No files to backup'];
    }

    // Create tar.gz using PharData
    try {
        $tarPath = $backupsDir . "/ccansam-backup-{$timestamp}.tar";

        // Create tar archive
        $phar = new PharData($tarPath);

        foreach ($filesToBackup as $file) {
            $fullPath = $rootDir . '/' . $file;
            // Add file with its relative path
            $phar->addFile($fullPath, $file);
        }

        // Compress to gzip
        $phar->compress(Phar::GZ);

        // Remove the uncompressed tar
        unlink($tarPath);

        // PharData creates .tar.gz, rename to .tgz
        $tgzPath = $tarPath . '.gz';
        if (file_exists($tgzPath)) {
            rename($tgzPath, $filepath);
        }

        if (!file_exists($filepath)) {
            return ['success' => false, 'message' => 'Failed to create backup archive'];
        }

        return [
            'success' => true,
            'filename' => $filename,
            'path' => $filepath,
            'size' => filesize($filepath),
            'sizeFormatted' => formatSize(filesize($filepath)),
            'files' => $filesToBackup
        ];

    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Backup creation failed: ' . $e->getMessage()];
    }
}

/**
 * Send backup email via Resend
 */
function sendBackupEmail($config, $backupPath, $filename, $stats) {
    $apiKey = $config['email']['resend_api_key'] ?? '';
    $recipient = $config['contact_form']['recipient_email'] ?? $config['contact']['email'] ?? '';
    $fromEmail = $config['email']['from_email'] ?? 'C-Can Sam <onboarding@resend.dev>';

    if (empty($apiKey)) {
        return ['success' => false, 'message' => 'No Resend API key configured'];
    }

    if (empty($recipient)) {
        return ['success' => false, 'message' => 'No recipient email configured'];
    }

    // Read backup file as base64
    $backupContent = base64_encode(file_get_contents($backupPath));
    $backupSize = filesize($backupPath);

    // Build stats HTML
    $statsHtml = '';
    foreach ($stats as $stat) {
        if ($stat['exists']) {
            $countStr = isset($stat['count']) && $stat['count'] > 0 ? " ({$stat['count']} {$stat['type']})" : '';
            $statsHtml .= "<li>{$stat['file']}: " . formatSize($stat['size']) . "{$countStr}</li>";
        }
    }

    $today = date('Y-m-d');
    $time = date('H:i:s');

    $payload = json_encode([
        'from' => $fromEmail,
        'to' => [$recipient],
        'subject' => "[C-Can Sam Backup] {$today}",
        'html' => "
            <h2>C-Can Sam Data Backup</h2>
            <p>Attached is your backup from <strong>{$today} at {$time}</strong>.</p>
            <p>Backup size: <strong>" . formatSize($backupSize) . "</strong></p>
            <h3>Contents:</h3>
            <ul>{$statsHtml}</ul>
            <h3>To Restore:</h3>
            <ol>
                <li>Extract the .tgz file: <code>tar -xzf {$filename}</code></li>
                <li>Upload files to <code>/var/www/ccan/</code> on your server</li>
                <li>Or commit to the repo</li>
            </ol>
            <p style=\"color: #666; font-size: 12px;\">
                This backup was created from the admin panel. Store it somewhere safe!
            </p>
        ",
        'attachments' => [
            [
                'filename' => $filename,
                'content' => $backupContent
            ]
        ]
    ]);

    $ch = curl_init('https://api.resend.com/emails');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json'
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        return ['success' => false, 'message' => 'Curl error: ' . $error];
    }

    if ($httpCode >= 200 && $httpCode < 300) {
        $data = json_decode($response, true);
        return ['success' => true, 'emailId' => $data['id'] ?? 'unknown'];
    } else {
        return ['success' => false, 'message' => "Resend API error: {$httpCode} - {$response}"];
    }
}

/**
 * Delete old backups, keeping the most recent N
 */
function cleanupOldBackups($backupsDir, $maxBackups) {
    $backups = listBackups($backupsDir);
    $deleted = [];

    // Keep only the most recent $maxBackups
    if (count($backups) > $maxBackups) {
        $toDelete = array_slice($backups, $maxBackups);
        foreach ($toDelete as $backup) {
            if (unlink($backup['path'])) {
                $deleted[] = $backup['filename'];
            }
        }
    }

    return $deleted;
}

// Handle actions
$rootDir = dirname(__DIR__);

switch ($action) {
    case 'list':
        $backups = listBackups($backupsDir);
        $stats = getFileStats($backupFiles, $rootDir);

        // Calculate total data size
        $totalSize = array_sum(array_map(fn($s) => $s['size'], $stats));

        echo json_encode([
            'success' => true,
            'backups' => $backups,
            'stats' => $stats,
            'totalSize' => $totalSize,
            'totalSizeFormatted' => formatSize($totalSize),
            'maxBackups' => $maxBackups,
            'warning' => $totalSize > 2097152 ? 'Data size exceeds 2MB - backups may be large' : null
        ]);
        break;

    case 'create':
        // Check if we need to delete old backup first
        $existingBackups = listBackups($backupsDir);
        $needsConfirmation = count($existingBackups) >= $maxBackups;
        $oldestBackup = $needsConfirmation ? end($existingBackups) : null;

        // If confirmation required and not confirmed, return prompt
        $confirmed = isset($_POST['confirmed']) && $_POST['confirmed'] === 'true';

        if ($needsConfirmation && !$confirmed) {
            echo json_encode([
                'success' => false,
                'needsConfirmation' => true,
                'message' => "Creating a new backup will delete the oldest backup: {$oldestBackup['filename']} ({$oldestBackup['createdFormatted']})",
                'oldestBackup' => $oldestBackup
            ]);
            break;
        }

        // Get file stats before backup
        $stats = getFileStats($backupFiles, $rootDir);

        // Create the backup
        $result = createBackup($backupFiles, $rootDir, $backupsDir);

        if (!$result['success']) {
            echo json_encode($result);
            break;
        }

        // Send email
        $emailResult = sendBackupEmail($config, $result['path'], $result['filename'], $stats);

        // Cleanup old backups if needed
        $deleted = [];
        if ($needsConfirmation) {
            $deleted = cleanupOldBackups($backupsDir, $maxBackups);
        }

        // Check size warning
        $warning = null;
        if ($result['size'] > 2097152) {
            $warning = 'Backup size exceeds 2MB. Consider cleaning up old data.';
        }

        echo json_encode([
            'success' => true,
            'backup' => [
                'filename' => $result['filename'],
                'size' => $result['size'],
                'sizeFormatted' => $result['sizeFormatted'],
                'files' => $result['files']
            ],
            'email' => $emailResult,
            'deleted' => $deleted,
            'warning' => $warning,
            'stats' => $stats
        ]);
        break;

    case 'delete':
        $filename = $_POST['filename'] ?? '';

        if (empty($filename)) {
            echo json_encode(['success' => false, 'message' => 'No filename specified']);
            break;
        }

        // Sanitize filename to prevent directory traversal
        $filename = basename($filename);
        $filepath = $backupsDir . '/' . $filename;

        if (!file_exists($filepath)) {
            echo json_encode(['success' => false, 'message' => 'Backup not found']);
            break;
        }

        if (unlink($filepath)) {
            echo json_encode(['success' => true, 'deleted' => $filename]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to delete backup']);
        }
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}
