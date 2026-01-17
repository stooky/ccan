<?php
/**
 * C-Can Sam Admin Panel
 *
 * View contact form submissions and Google reviews
 * Access via: /api/admin.php?key=YOUR_SECRET_PATH
 */

// Load configuration
$configPath = dirname(__DIR__) . '/config.yaml';
$localConfigPath = dirname(__DIR__) . '/config.local.yaml';
$reviewsPath = dirname(__DIR__) . '/data/reviews.json';
$config = null;

if (file_exists($configPath)) {
    if (function_exists('yaml_parse_file')) {
        $config = yaml_parse_file($configPath);
    } else {
        // Simple parser
        $content = file_get_contents($configPath);
        $config = ['admin' => ['secret_path' => 'ccan-admin-2024', 'per_page' => 50]];
        if (preg_match('/secret_path:\s*["\']?([^"\'\n]+)["\']?/', $content, $matches)) {
            $config['admin']['secret_path'] = trim($matches[1]);
        }
        if (preg_match('/per_page:\s*(\d+)/', $content, $matches)) {
            $config['admin']['per_page'] = (int)$matches[1];
        }
        if (preg_match('/submissions_file:\s*["\']?([^"\'\n]+)["\']?/', $content, $matches)) {
            $config['logging']['submissions_file'] = trim($matches[1]);
        }
    }
}

// Merge local overrides if they exist
if (file_exists($localConfigPath)) {
    if (function_exists('yaml_parse_file')) {
        $localConfig = yaml_parse_file($localConfigPath);
    } else {
        $content = file_get_contents($localConfigPath);
        $localConfig = [];
        if (preg_match('/secret_path:\s*["\']?([^"\'\n]+)["\']?/', $content, $matches)) {
            $localConfig['admin']['secret_path'] = trim($matches[1]);
        }
    }
    if ($localConfig) {
        $config = array_replace_recursive($config ?? [], $localConfig);
    }
}

$secretPath = $config['admin']['secret_path'] ?? 'ccan-admin-2024';
$perPage = $config['admin']['per_page'] ?? 100;
$logFile = dirname(__DIR__) . '/' . ($config['logging']['submissions_file'] ?? 'data/submissions.json');

// Check authentication
$providedKey = $_GET['key'] ?? '';
if ($providedKey !== $secretPath) {
    http_response_code(404);
    echo '<!DOCTYPE html><html><head><title>404 Not Found</title></head><body><h1>Not Found</h1></body></html>';
    exit();
}

// Load all submissions
$allSubmissions = [];
if (file_exists($logFile)) {
    $content = file_get_contents($logFile);
    $allSubmissions = json_decode($content, true) ?? [];
}

// Handle delete action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $deleteId = $_POST['delete_id'];
    $allSubmissions = array_filter($allSubmissions, fn($s) => $s['id'] !== $deleteId);
    $allSubmissions = array_values($allSubmissions);
    file_put_contents($logFile, json_encode($allSubmissions, JSON_PRETTY_PRINT));
    header('Location: ?key=' . urlencode($secretPath) . '&deleted=1');
    exit();
}

// Handle add review action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_review') {
    header('Content-Type: application/json');

    $author = trim($_POST['author'] ?? '');
    $rating = (int)($_POST['rating'] ?? 0);
    $date = trim($_POST['date'] ?? date('Y-m-d'));
    $text = trim($_POST['text'] ?? '');
    $ownerResponse = trim($_POST['ownerResponse'] ?? '');

    // Validate
    if (empty($author)) {
        echo json_encode(['success' => false, 'message' => 'Name is required']);
        exit();
    }
    if ($rating < 1 || $rating > 5) {
        echo json_encode(['success' => false, 'message' => 'Rating must be between 1 and 5']);
        exit();
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        echo json_encode(['success' => false, 'message' => 'Invalid date format']);
        exit();
    }

    // Load reviews
    $reviewsPath = dirname(__DIR__) . '/data/reviews.json';
    $reviewsData = ['reviews' => [], 'lastSync' => null, 'totalCount' => 0, 'averageRating' => 0, 'source' => 'Google Business Profile'];
    if (file_exists($reviewsPath)) {
        $reviewsData = json_decode(file_get_contents($reviewsPath), true) ?? $reviewsData;
    }

    // Generate new review ID
    $maxId = 0;
    foreach ($reviewsData['reviews'] as $review) {
        if (preg_match('/review_(\d+)/', $review['id'], $matches)) {
            $maxId = max($maxId, (int)$matches[1]);
        }
    }
    $newId = 'review_' . str_pad($maxId + 1, 3, '0', STR_PAD_LEFT);

    // Calculate relative date
    $reviewDate = new DateTime($date);
    $now = new DateTime();
    $diff = $now->diff($reviewDate);
    if ($diff->days == 0) {
        $relativeDate = 'Today';
    } elseif ($diff->days == 1) {
        $relativeDate = 'Yesterday';
    } elseif ($diff->days < 7) {
        $relativeDate = $diff->days . ' days ago';
    } elseif ($diff->days < 30) {
        $weeks = floor($diff->days / 7);
        $relativeDate = $weeks . ' week' . ($weeks > 1 ? 's' : '') . ' ago';
    } elseif ($diff->days < 365) {
        $months = floor($diff->days / 30);
        $relativeDate = $months . ' month' . ($months > 1 ? 's' : '') . ' ago';
    } else {
        $years = floor($diff->days / 365);
        $relativeDate = $years . ' year' . ($years > 1 ? 's' : '') . ' ago';
    }

    // Create new review
    $newReview = [
        'id' => $newId,
        'author' => $author,
        'rating' => $rating,
        'date' => $date,
        'relativeDate' => $relativeDate,
        'text' => $text ?: null,
        'hasPhoto' => false,
        'isNew' => true,
        'source' => 'manual'
    ];

    // Add owner response if provided
    if (!empty($ownerResponse)) {
        $newReview['ownerResponse'] = [
            'date' => date('Y-m-d'),
            'text' => $ownerResponse
        ];
    }

    // Remove null text if empty
    if ($newReview['text'] === null) {
        unset($newReview['text']);
    }

    // Add to beginning of reviews array
    array_unshift($reviewsData['reviews'], $newReview);

    // Update stats
    $reviewsData['totalCount'] = count($reviewsData['reviews']);
    $totalRating = 0;
    foreach ($reviewsData['reviews'] as $review) {
        $totalRating += $review['rating'];
    }
    $reviewsData['averageRating'] = round($totalRating / $reviewsData['totalCount'], 2);

    // Save
    file_put_contents($reviewsPath, json_encode($reviewsData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    echo json_encode([
        'success' => true,
        'review' => $newReview,
        'totalCount' => $reviewsData['totalCount'],
        'averageRating' => $reviewsData['averageRating']
    ]);
    exit();
}

// Handle delete review action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_review') {
    header('Content-Type: application/json');

    $reviewId = trim($_POST['review_id'] ?? '');

    if (empty($reviewId)) {
        echo json_encode(['success' => false, 'message' => 'Review ID is required']);
        exit();
    }

    // Load reviews
    $reviewsPath = dirname(__DIR__) . '/data/reviews.json';
    if (!file_exists($reviewsPath)) {
        echo json_encode(['success' => false, 'message' => 'Reviews file not found']);
        exit();
    }

    $reviewsData = json_decode(file_get_contents($reviewsPath), true);

    // Find and remove the review
    $found = false;
    $reviewsData['reviews'] = array_values(array_filter($reviewsData['reviews'], function($review) use ($reviewId, &$found) {
        if ($review['id'] === $reviewId) {
            $found = true;
            return false;
        }
        return true;
    }));

    if (!$found) {
        echo json_encode(['success' => false, 'message' => 'Review not found']);
        exit();
    }

    // Update stats
    $reviewsData['totalCount'] = count($reviewsData['reviews']);
    if ($reviewsData['totalCount'] > 0) {
        $totalRating = 0;
        foreach ($reviewsData['reviews'] as $review) {
            $totalRating += $review['rating'];
        }
        $reviewsData['averageRating'] = round($totalRating / $reviewsData['totalCount'], 2);
    } else {
        $reviewsData['averageRating'] = 0;
    }

    // Save
    file_put_contents($reviewsPath, json_encode($reviewsData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    echo json_encode([
        'success' => true,
        'totalCount' => $reviewsData['totalCount'],
        'averageRating' => $reviewsData['averageRating']
    ]);
    exit();
}

// Handle edit review action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_review') {
    header('Content-Type: application/json');

    $reviewId = trim($_POST['review_id'] ?? '');
    $author = trim($_POST['author'] ?? '');
    $rating = (int)($_POST['rating'] ?? 0);
    $date = trim($_POST['date'] ?? date('Y-m-d'));
    $text = trim($_POST['text'] ?? '');
    $ownerResponse = trim($_POST['ownerResponse'] ?? '');

    // Validate
    if (empty($reviewId)) {
        echo json_encode(['success' => false, 'message' => 'Review ID is required']);
        exit();
    }
    if (empty($author)) {
        echo json_encode(['success' => false, 'message' => 'Name is required']);
        exit();
    }
    if ($rating < 1 || $rating > 5) {
        echo json_encode(['success' => false, 'message' => 'Rating must be between 1 and 5']);
        exit();
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        echo json_encode(['success' => false, 'message' => 'Invalid date format']);
        exit();
    }

    // Load reviews
    $reviewsPath = dirname(__DIR__) . '/data/reviews.json';
    if (!file_exists($reviewsPath)) {
        echo json_encode(['success' => false, 'message' => 'Reviews file not found']);
        exit();
    }

    $reviewsData = json_decode(file_get_contents($reviewsPath), true);

    // Calculate relative date
    $reviewDate = new DateTime($date);
    $now = new DateTime();
    $diff = $now->diff($reviewDate);
    if ($diff->days == 0) {
        $relativeDate = 'Today';
    } elseif ($diff->days == 1) {
        $relativeDate = 'Yesterday';
    } elseif ($diff->days < 7) {
        $relativeDate = $diff->days . ' days ago';
    } elseif ($diff->days < 30) {
        $weeks = floor($diff->days / 7);
        $relativeDate = $weeks . ' week' . ($weeks > 1 ? 's' : '') . ' ago';
    } elseif ($diff->days < 365) {
        $months = floor($diff->days / 30);
        $relativeDate = $months . ' month' . ($months > 1 ? 's' : '') . ' ago';
    } else {
        $years = floor($diff->days / 365);
        $relativeDate = $years . ' year' . ($years > 1 ? 's' : '') . ' ago';
    }

    // Find and update the review
    $found = false;
    foreach ($reviewsData['reviews'] as &$review) {
        if ($review['id'] === $reviewId) {
            $found = true;
            $review['author'] = $author;
            $review['rating'] = $rating;
            $review['date'] = $date;
            $review['relativeDate'] = $relativeDate;

            if (!empty($text)) {
                $review['text'] = $text;
            } else {
                unset($review['text']);
            }

            if (!empty($ownerResponse)) {
                $review['ownerResponse'] = [
                    'date' => $review['ownerResponse']['date'] ?? date('Y-m-d'),
                    'text' => $ownerResponse
                ];
            } else {
                unset($review['ownerResponse']);
            }
            break;
        }
    }
    unset($review);

    if (!$found) {
        echo json_encode(['success' => false, 'message' => 'Review not found']);
        exit();
    }

    // Update stats
    $totalRating = 0;
    foreach ($reviewsData['reviews'] as $review) {
        $totalRating += $review['rating'];
    }
    $reviewsData['averageRating'] = round($totalRating / $reviewsData['totalCount'], 2);

    // Save
    file_put_contents($reviewsPath, json_encode($reviewsData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    echo json_encode([
        'success' => true,
        'totalCount' => $reviewsData['totalCount'],
        'averageRating' => $reviewsData['averageRating']
    ]);
    exit();
}

// ============================================================================
// Spam Detection Functions (for re-analyzing manual entries)
// ============================================================================

function analyzeGibberishName($name) {
    $issues = [];

    $vowelCount = preg_match_all('/[aeiouAEIOU]/', $name);
    $consonantCount = preg_match_all('/[bcdfghjklmnpqrstvwxyzBCDFGHJKLMNPQRSTVWXYZ]/', $name);
    $letterCount = $vowelCount + $consonantCount;

    if ($letterCount > 5 && $vowelCount == 0) {
        $issues[] = 'no_vowels';
    }

    if ($letterCount > 8) {
        $vowelRatio = $vowelCount / $letterCount;
        if ($vowelRatio < 0.15) {
            $issues[] = 'low_vowel_ratio';
        }
    }

    if (preg_match('/[bcdfghjklmnpqrstvwxyz]{5,}/i', $name)) {
        $issues[] = 'consonant_cluster';
    }

    $words = preg_split('/[\s\'-]+/', $name);
    foreach ($words as $word) {
        if (strlen($word) > 3) {
            $midCaps = preg_match_all('/(?!^)[A-Z]/', $word);
            if ($midCaps > 2) {
                $issues[] = 'random_caps';
                break;
            }
        }
    }

    return $issues;
}

function analyzePhoneAreaCode($phone, $validCodes) {
    $phone = preg_replace('/\D/', '', $phone);

    if (strlen($phone) == 11 && $phone[0] == '1') {
        $phone = substr($phone, 1);
    }

    if (strlen($phone) < 10) {
        return ['valid' => true, 'areaCode' => ''];
    }

    $areaCode = substr($phone, 0, 3);

    return [
        'valid' => in_array($areaCode, $validCodes),
        'areaCode' => $areaCode
    ];
}

function analyzeMessageWords($message) {
    $commonWords = [
        'the', 'be', 'to', 'of', 'and', 'a', 'in', 'that', 'have', 'i',
        'it', 'for', 'not', 'on', 'with', 'he', 'as', 'you', 'do', 'at',
        'this', 'but', 'his', 'by', 'from', 'they', 'we', 'say', 'her', 'she',
        'or', 'an', 'will', 'my', 'one', 'all', 'would', 'there', 'their', 'what',
        'so', 'up', 'out', 'if', 'about', 'who', 'get', 'which', 'go', 'me',
        'container', 'shipping', 'storage', 'delivery', 'pickup', 'quote', 'price',
        'buy', 'sell', 'rent', 'lease', 'need', 'looking', 'interested', 'please',
        'thanks', 'thank', 'hello', 'hi', 'hey', 'call', 'contact', 'email', 'phone',
        'question', 'help', 'information', 'info', 'size', 'condition', 'new', 'used',
        'yes', 'no', 'maybe', 'ok', 'okay', 'week', 'today', 'tomorrow', 'soon',
    ];

    $words = preg_split('/[\s\p{P}]+/u', strtolower($message));
    $words = array_filter($words, fn($w) => strlen($w) >= 2);

    $realWordCount = 0;
    foreach ($words as $word) {
        if (in_array($word, $commonWords)) {
            $realWordCount++;
        }
    }

    return ['count' => $realWordCount, 'hasRealWords' => $realWordCount > 0];
}

function analyzeRandomString($str) {
    if (strlen($str) < 6) return false;

    $str = strtolower($str);
    $chars = count_chars($str, 1);
    $len = strlen($str);

    $entropy = 0;
    foreach ($chars as $count) {
        $p = $count / $len;
        $entropy -= $p * log($p, 2);
    }

    if ($entropy > 4.3) {
        $hasCommonPatterns = preg_match('/(th|he|in|er|an|re|on|at|en|es|or|te|of|ed|is|it|al|ar|st|to|nt|ng|se|ha|as|ou|io|le|ve|co|me|de|hi|ri|ro|ic|ne|ea|ra|ce)/i', $str);
        if (!$hasCommonPatterns) {
            return true;
        }
    }

    return false;
}

function analyzeGmailDots($email) {
    if (!str_contains(strtolower($email), '@gmail.com')) {
        return ['dots' => 0, 'suspicious' => false];
    }
    $username = explode('@', $email)[0];
    $dots = substr_count($username, '.');
    return ['dots' => $dots, 'suspicious' => $dots >= 3];
}

// Handle re-analyze manual entries action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reanalyze_manual') {
    $spamLogFile = dirname(__DIR__) . '/data/spam-log.json';
    $spamLog = [];
    if (file_exists($spamLogFile)) {
        $spamLog = json_decode(file_get_contents($spamLogFile), true) ?? [];
    }

    // Get valid area codes from config
    $validAreaCodes = ['306', '639', '403', '587', '780', '825', '204', '431', '236', '250', '604', '672', '778', '416', '647', '905', '437', '514', '438'];
    if ($config && isset($config['security']['valid_area_codes'])) {
        $validAreaCodes = $config['security']['valid_area_codes'];
    }

    $updated = 0;
    foreach ($spamLog as &$entry) {
        if (!str_starts_with($entry['reason'] ?? '', 'manual_review')) {
            continue;
        }

        $data = $entry['original_data'] ?? [];
        $detections = [];

        // Get name (handle both 'name' and 'firstName'+'lastName' formats)
        $name = $data['name'] ?? '';
        if (empty($name)) {
            $name = trim(($data['firstName'] ?? '') . ' ' . ($data['lastName'] ?? ''));
        }

        $phone = preg_replace('/\D/', '', $data['phone'] ?? '');
        $email = $data['email'] ?? '';
        $message = $data['message'] ?? '';

        // Analyze name
        if (!empty($name)) {
            $nameIssues = analyzeGibberishName($name);
            if (!empty($nameIssues)) {
                $detections[] = 'invalid_name:' . implode(',', $nameIssues);
            }
        }

        // Analyze phone
        if (!empty($phone)) {
            $phoneResult = analyzePhoneAreaCode($phone, $validAreaCodes);
            if (!$phoneResult['valid']) {
                $detections[] = 'invalid_phone:invalid_area_code:' . $phoneResult['areaCode'];
            }
        }

        // Analyze message
        if (!empty($message)) {
            $msgResult = analyzeMessageWords($message);
            if (!$msgResult['hasRealWords']) {
                $detections[] = 'gibberish_message:no_real_words';
            }
            if (analyzeRandomString($message)) {
                $detections[] = 'gibberish_message:high_entropy';
            }
        }

        // Analyze Gmail dots
        if (!empty($email)) {
            $gmailResult = analyzeGmailDots($email);
            if ($gmailResult['suspicious']) {
                $detections[] = 'gmail_dot_pattern:' . $gmailResult['dots'] . '_dots';
            }
        }

        // Update reason with detections
        if (!empty($detections)) {
            // Preserve the manual note after the colon
            $manualNote = '';
            if (preg_match('/^manual_review:(.+)$/', $entry['reason'], $matches)) {
                $manualNote = $matches[1];
            }
            $entry['reason'] = 'manual_review:' . $manualNote;
            $entry['detected_issues'] = $detections;
            $updated++;
        }
    }
    unset($entry);

    file_put_contents($spamLogFile, json_encode($spamLog, JSON_PRETTY_PRINT));
    header('Location: ?key=' . urlencode($secretPath) . '&tab=spam&reanalyzed=' . $updated);
    exit();
}

// Helper: Get display name for submission
function getDisplayName($sub) {
    if (!empty($sub['name'])) {
        return $sub['name'];
    }
    return trim(($sub['firstName'] ?? '') . ' ' . ($sub['lastName'] ?? ''));
}

// Handle export action (exports filtered data)
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="submissions-' . date('Y-m-d') . '.csv"');

    $output = fopen('php://output', 'w');
    fputcsv($output, [
        'ID', 'Form Type', 'Date', 'Time', 'Name', 'Email', 'Phone',
        'Container Size', 'Condition', 'Intention', 'Delivery',
        'Location Type', 'Street Address', 'City', 'Postal Code',
        'Land Location', 'Additional Directions',
        'Subject', 'Message', 'IP'
    ]);

    foreach ($allSubmissions as $sub) {
        fputcsv($output, [
            $sub['id'],
            $sub['formType'] ?? 'message',
            $sub['date'],
            $sub['time'],
            getDisplayName($sub),
            $sub['email'],
            $sub['phone'] ?? '',
            $sub['containerSize'] ?? '',
            $sub['condition'] ?? '',
            $sub['intention'] ?? '',
            $sub['delivery'] ?? '',
            $sub['locationType'] ?? '',
            $sub['streetAddress'] ?? '',
            $sub['city'] ?? '',
            $sub['postalCode'] ?? '',
            $sub['landLocation'] ?? '',
            $sub['additionalDirections'] ?? '',
            $sub['subject'] ?? '',
            $sub['message'] ?? '',
            $sub['ip']
        ]);
    }

    fclose($output);
    exit();
}

// Prepare submissions data as JSON for client-side filtering
$submissionsJson = json_encode($allSubmissions);
$totalSubmissions = count($allSubmissions);
$quoteCount = count(array_filter($allSubmissions, fn($s) => ($s['formType'] ?? 'message') === 'quote'));
$todayCount = count(array_filter($allSubmissions, fn($s) => ($s['date'] ?? '') === date('Y-m-d')));
$last7DaysCount = count(array_filter($allSubmissions, fn($s) => strtotime($s['date'] ?? '1970-01-01') >= strtotime('-7 days')));

// Load reviews
$reviewsData = ['reviews' => [], 'lastSync' => null, 'totalCount' => 0, 'averageRating' => 0];
if (file_exists($reviewsPath)) {
    $reviewsContent = file_get_contents($reviewsPath);
    $reviewsData = json_decode($reviewsContent, true) ?? $reviewsData;
}
$reviewsJson = json_encode($reviewsData['reviews'] ?? []);
$reviewsLastSync = $reviewsData['lastSync'] ?? 'Never';
$reviewsTotalCount = $reviewsData['totalCount'] ?? count($reviewsData['reviews'] ?? []);
$reviewsAvgRating = $reviewsData['averageRating'] ?? 0;

// Get current tab
$currentTab = $_GET['tab'] ?? 'submissions';

// Load spam log
$spamLogFile = dirname(__DIR__) . '/data/spam-log.json';
$spamLog = [];
if (file_exists($spamLogFile)) {
    $spamContent = file_get_contents($spamLogFile);
    $spamLog = json_decode($spamContent, true) ?? [];
}
$spamCount = count($spamLog);
$spamToday = count(array_filter($spamLog, fn($s) => date('Y-m-d', strtotime($s['timestamp'] ?? '1970-01-01')) === date('Y-m-d')));
$spamJson = json_encode($spamLog);

// Load products config for Rich Snippets tab
$productsConfig = [];
if ($config && isset($config['products']['containers'])) {
    $productsConfig = $config['products']['containers'];
}

// Handle Rich Snippets form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_products') {
    // Build updated products array from POST data
    $updatedProducts = [];
    foreach ($_POST['products'] as $index => $product) {
        $updatedProducts[] = [
            'size' => $product['size'],
            'slug' => $product['slug'],
            'name' => $product['name'],
            'description' => $product['description'],
            'new' => !empty($product['new_min']) ? [
                'min' => (int)$product['new_min'],
                'max' => (int)$product['new_max']
            ] : null,
            'used' => !empty($product['used_min']) ? [
                'min' => (int)$product['used_min'],
                'max' => (int)$product['used_max']
            ] : null,
        ];
    }

    // Read config file and update products section
    $configContent = file_get_contents($configPath);

    // Build YAML for products
    $productsYaml = "products:\n  currency: \"CAD\"\n  containers:\n";
    foreach ($updatedProducts as $product) {
        $productsYaml .= "    - size: \"{$product['size']}\"\n";
        $productsYaml .= "      slug: \"{$product['slug']}\"\n";
        $productsYaml .= "      name: \"{$product['name']}\"\n";
        $productsYaml .= "      description: \"{$product['description']}\"\n";
        if ($product['new']) {
            $productsYaml .= "      new: { min: {$product['new']['min']}, max: {$product['new']['max']} }\n";
        } else {
            $productsYaml .= "      new: null\n";
        }
        if ($product['used']) {
            $productsYaml .= "      used: { min: {$product['used']['min']}, max: {$product['used']['max']} }\n";
        } else {
            $productsYaml .= "      used: null\n";
        }
    }

    // Replace products section in config
    $pattern = '/products:\s*\n\s+currency:.*?(?=\n# |\n[a-z]+:|\z)/s';
    $configContent = preg_replace($pattern, $productsYaml, $configContent);

    file_put_contents($configPath, $configContent);

    // Reload config
    if (function_exists('yaml_parse_file')) {
        $config = yaml_parse_file($configPath);
    }
    $productsConfig = $config['products']['containers'] ?? [];

    $productsSaved = true;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - C-Can Sam</title>
    <meta name="robots" content="noindex, nofollow">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            background: #f3f4f6;
            color: #1f2937;
            line-height: 1.5;
            padding: 2rem;
        }
        .container { max-width: 1200px; margin: 0 auto; }
        h1 { font-size: 1.875rem; font-weight: 700; margin-bottom: 0.5rem; }
        .subtitle { color: #6b7280; margin-bottom: 2rem; }
        .stats {
            display: flex; gap: 1rem; margin-bottom: 1.5rem; flex-wrap: wrap;
        }
        .stat {
            background: white; padding: 1rem 1.5rem; border-radius: 0.5rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .stat-value { font-size: 1.5rem; font-weight: 700; color: #d97706; }
        .stat-label { font-size: 0.875rem; color: #6b7280; }

        /* Filter bar */
        .filter-bar {
            background: white; padding: 1rem 1.5rem; border-radius: 0.5rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1); margin-bottom: 1.5rem;
            display: flex; gap: 1rem; flex-wrap: wrap; align-items: center;
        }
        .filter-group { display: flex; align-items: center; gap: 0.5rem; }
        .filter-group label { font-size: 0.875rem; font-weight: 500; color: #374151; }
        .filter-group input[type="date"], .filter-group input[type="text"], .filter-group select {
            padding: 0.5rem 0.75rem; border: 1px solid #d1d5db; border-radius: 0.375rem;
            font-size: 0.875rem; background: white;
        }
        .filter-group input[type="text"] { width: 200px; }
        .filter-group input:focus, .filter-group select:focus {
            outline: none; border-color: #d97706; box-shadow: 0 0 0 2px rgba(217, 119, 6, 0.2);
        }
        .quick-filters { display: flex; gap: 0.5rem; }
        .quick-filter {
            padding: 0.375rem 0.75rem; border-radius: 0.375rem; font-size: 0.75rem;
            background: #f3f4f6; color: #374151; border: none; cursor: pointer;
            transition: all 0.15s;
        }
        .quick-filter:hover { background: #e5e7eb; }
        .quick-filter.active { background: #d97706; color: white; }
        .results-count { font-size: 0.875rem; color: #6b7280; margin-left: auto; }

        .actions { margin-bottom: 1.5rem; display: flex; gap: 0.5rem; }
        .btn {
            display: inline-block; padding: 0.5rem 1rem; border-radius: 0.375rem;
            text-decoration: none; font-weight: 500; font-size: 0.875rem;
            border: none; cursor: pointer;
        }
        .btn-primary { background: #d97706; color: white; }
        .btn-primary:hover { background: #b45309; }
        .btn-secondary { background: #e5e7eb; color: #374151; }
        .btn-secondary:hover { background: #d1d5db; }
        .btn-danger { background: #dc2626; color: white; font-size: 0.75rem; padding: 0.25rem 0.5rem; }
        .btn-danger:hover { background: #b91c1c; }
        .alert {
            padding: 1rem; border-radius: 0.5rem; margin-bottom: 1rem;
            background: #d1fae5; color: #065f46; border: 1px solid #6ee7b7;
        }
        .submissions { display: flex; flex-direction: column; gap: 0.5rem; }

        /* Expandable row styles */
        .submission-row {
            background: white; border-radius: 0.5rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .submission-row.hidden { display: none; }
        .submission-summary {
            display: flex; align-items: center; padding: 0.75rem 1rem;
            cursor: pointer; gap: 1rem; transition: background 0.15s;
        }
        .submission-summary:hover { background: #f9fafb; }
        .expand-icon {
            font-size: 0.75rem; color: #9ca3af; transition: transform 0.2s;
            flex-shrink: 0; width: 1rem;
        }
        .submission-row.expanded .expand-icon { transform: rotate(90deg); }
        .badge {
            display: inline-block; padding: 0.125rem 0.5rem; border-radius: 9999px;
            font-size: 0.625rem; font-weight: 600; text-transform: uppercase;
            flex-shrink: 0;
        }
        .badge-quote { background: #fef3c7; color: #92400e; }
        .badge-spam { background: #fee2e2; color: #dc2626; }
        .badge-manual { background: #f3f4f6; color: #4b5563; }
        .badge-message { background: #dbeafe; color: #1e40af; }
        .summary-name { font-weight: 600; min-width: 120px; flex-shrink: 0; }
        .summary-email { color: #d97706; min-width: 180px; flex-shrink: 0; }
        .summary-phone { color: #6b7280; min-width: 120px; flex-shrink: 0; }
        .summary-preview {
            color: #6b7280; font-size: 0.875rem; flex: 1;
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }
        .summary-date { color: #9ca3af; font-size: 0.75rem; flex-shrink: 0; text-align: right; min-width: 80px; }

        /* Expanded details */
        .submission-details {
            display: none; padding: 1rem 1.5rem; background: #f9fafb;
            border-top: 1px solid #e5e7eb;
        }
        .submission-row.expanded .submission-details { display: block; }
        .details-grid {
            display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 1rem; margin-bottom: 1rem;
        }
        .detail-item { }
        .detail-label { font-size: 0.625rem; text-transform: uppercase; color: #9ca3af; font-weight: 600; margin-bottom: 0.125rem; }
        .detail-value { font-size: 0.875rem; color: #1f2937; }
        .detail-value:empty::after { content: '—'; color: #d1d5db; }
        .message-section { margin-top: 1rem; }
        .message-label { font-size: 0.625rem; text-transform: uppercase; color: #9ca3af; font-weight: 600; margin-bottom: 0.25rem; }
        .message-content {
            background: white; padding: 0.75rem; border-radius: 0.375rem;
            white-space: pre-wrap; font-size: 0.875rem; border: 1px solid #e5e7eb;
        }
        .details-footer {
            display: flex; justify-content: space-between; align-items: center;
            margin-top: 1rem; padding-top: 0.75rem; border-top: 1px solid #e5e7eb;
            font-size: 0.75rem; color: #9ca3af;
        }

        .empty {
            text-align: center; padding: 3rem; background: white;
            border-radius: 0.5rem; color: #6b7280;
        }
        @media (max-width: 768px) {
            body { padding: 1rem; }
            .filter-bar { flex-direction: column; align-items: stretch; }
            .filter-group { flex-wrap: wrap; }
            .results-count { margin-left: 0; margin-top: 0.5rem; }
            .summary-phone, .summary-preview { display: none; }
            .details-grid { grid-template-columns: 1fr 1fr; }
        }

        /* Tab Navigation */
        .tabs {
            display: flex; gap: 0; margin-bottom: 1.5rem;
            border-bottom: 2px solid #e5e7eb;
        }
        .tab {
            padding: 0.75rem 1.5rem; font-weight: 500; font-size: 0.9375rem;
            color: #6b7280; text-decoration: none; border-bottom: 2px solid transparent;
            margin-bottom: -2px; transition: all 0.15s;
        }
        .tab:hover { color: #d97706; }
        .tab.active { color: #d97706; border-bottom-color: #d97706; }
        .tab-content { display: none; }
        .tab-content.active { display: block; }

        /* Reviews styles */
        .reviews-header {
            display: flex; justify-content: space-between; align-items: center;
            margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;
        }
        .reviews-stats {
            display: flex; gap: 1.5rem; align-items: center;
        }
        .reviews-stat {
            display: flex; align-items: center; gap: 0.5rem;
        }
        .reviews-stat-value { font-size: 1.25rem; font-weight: 700; color: #d97706; }
        .reviews-stat-label { font-size: 0.875rem; color: #6b7280; }
        .stars { color: #fbbf24; font-size: 1.125rem; }

        .reviews-filters {
            background: white; padding: 1rem 1.5rem; border-radius: 0.5rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1); margin-bottom: 1.5rem;
            display: flex; gap: 1rem; flex-wrap: wrap; align-items: center;
        }
        .reviews-filter-group {
            display: flex; align-items: center; gap: 0.5rem;
        }
        .reviews-filter-group label {
            font-size: 0.875rem; font-weight: 500; color: #374151;
        }
        .reviews-filters input[type="date"],
        .reviews-filters input[type="text"],
        .reviews-filters select {
            padding: 0.5rem 0.75rem; border: 1px solid #d1d5db; border-radius: 0.375rem;
            font-size: 0.875rem; background: white;
        }
        .reviews-filters input[type="text"] { width: 200px; }
        .reviews-filters input:focus,
        .reviews-filters select:focus {
            outline: none; border-color: #d97706; box-shadow: 0 0 0 2px rgba(217, 119, 6, 0.2);
        }
        .reviews-results-count {
            font-size: 0.875rem; color: #6b7280; margin-left: auto;
        }

        .reviews-list {
            display: flex; flex-direction: column; gap: 0.75rem;
        }
        .review-card {
            background: white; border-radius: 0.5rem; padding: 1.25rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .review-card.hidden { display: none; }
        .review-header {
            display: flex; justify-content: space-between; align-items: flex-start;
            margin-bottom: 0.75rem;
        }
        .review-author {
            display: flex; align-items: center; gap: 0.75rem;
        }
        .review-avatar {
            width: 40px; height: 40px; border-radius: 50%; background: #d97706;
            display: flex; align-items: center; justify-content: center;
            color: white; font-weight: 600; font-size: 1rem;
        }
        .review-author-info { }
        .review-author-name { font-weight: 600; color: #1f2937; }
        .review-author-meta { font-size: 0.75rem; color: #9ca3af; display: flex; gap: 0.5rem; align-items: center; }
        .review-rating { display: flex; gap: 0.125rem; }
        .review-star { color: #fbbf24; font-size: 0.875rem; }
        .review-star.empty { color: #e5e7eb; }
        .review-date { font-size: 0.75rem; color: #9ca3af; text-align: right; }
        .review-text { color: #374151; line-height: 1.6; margin-bottom: 0.75rem; }
        .review-response {
            background: #f9fafb; border-left: 3px solid #d97706; padding: 0.75rem 1rem;
            margin-top: 0.75rem; border-radius: 0 0.375rem 0.375rem 0;
        }
        .review-response-header {
            display: flex; align-items: center; gap: 0.5rem;
            font-size: 0.75rem; font-weight: 600; color: #d97706; margin-bottom: 0.5rem;
        }
        .review-response-avatar {
            width: 24px; height: 24px; border-radius: 50%; object-fit: cover;
            border: 1px solid #e5e7eb;
        }
        .review-response-text { font-size: 0.875rem; color: #6b7280; }
        .review-badges { display: flex; gap: 0.5rem; margin-top: 0.5rem; }
        .review-badge {
            font-size: 0.625rem; padding: 0.125rem 0.375rem; border-radius: 0.25rem;
            background: #e5e7eb; color: #6b7280; text-transform: uppercase; font-weight: 600;
        }
        .review-badge.new { background: #dcfce7; color: #166534; }
        .review-badge.photo { background: #dbeafe; color: #1e40af; }
        .review-badge.guide { background: #fef3c7; color: #92400e; }

        .reviews-pagination {
            display: flex; justify-content: center; align-items: center; gap: 0.5rem;
            margin-top: 1.5rem;
        }
        .page-btn {
            padding: 0.5rem 0.75rem; border: 1px solid #d1d5db; border-radius: 0.375rem;
            background: white; cursor: pointer; font-size: 0.875rem;
        }
        .page-btn:hover { background: #f3f4f6; }
        .page-btn.active { background: #d97706; color: white; border-color: #d97706; }
        .page-btn:disabled { opacity: 0.5; cursor: not-allowed; }
        .page-info { font-size: 0.875rem; color: #6b7280; margin: 0 0.5rem; }

        /* Modal styles */
        .modal {
            position: fixed; top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0,0,0,0.5); z-index: 1000;
            display: flex; align-items: center; justify-content: center;
        }
        .modal-content {
            background: white; border-radius: 0.5rem; width: 90%; max-width: 500px;
            max-height: 80vh; overflow: auto; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25);
        }
        .modal-header {
            display: flex; justify-content: space-between; align-items: center;
            padding: 1rem 1.5rem; border-bottom: 1px solid #e5e7eb;
        }
        .modal-header h3 { margin: 0; font-size: 1.125rem; }
        .modal-close {
            background: none; border: none; font-size: 1.5rem; cursor: pointer;
            color: #9ca3af; line-height: 1;
        }
        .modal-close:hover { color: #6b7280; }
        .modal-body { padding: 1.5rem; }
        .spinner {
            width: 40px; height: 40px; margin: 1rem auto;
            border: 3px solid #e5e7eb; border-top-color: #d97706;
            border-radius: 50%; animation: spin 1s linear infinite;
        }
        @keyframes spin { to { transform: rotate(360deg); } }
        .tag-result { white-space: pre-wrap; font-family: monospace; font-size: 0.75rem; background: #f3f4f6; padding: 1rem; border-radius: 0.375rem; max-height: 300px; overflow: auto; }
        .tag-success { color: #065f46; }
        .tag-error { color: #dc2626; }
    </style>
</head>
<body>
    <div class="container">
        <h1>C-Can Sam Admin</h1>
        <p class="subtitle">Manage submissions and reviews</p>

        <?php if (isset($_GET['deleted'])): ?>
            <div class="alert">Submission deleted successfully.</div>
        <?php endif; ?>

        <!-- Tab Navigation -->
        <div class="tabs">
            <a href="?key=<?= urlencode($secretPath) ?>&tab=submissions" class="tab <?= $currentTab === 'submissions' ? 'active' : '' ?>">
                Submissions (<?= $totalSubmissions ?>)
            </a>
            <a href="?key=<?= urlencode($secretPath) ?>&tab=spam" class="tab <?= $currentTab === 'spam' ? 'active' : '' ?>">
                Spam (<?= $spamCount ?>)
            </a>
            <a href="?key=<?= urlencode($secretPath) ?>&tab=reviews" class="tab <?= $currentTab === 'reviews' ? 'active' : '' ?>">
                Reviews (<?= $reviewsTotalCount ?>)
            </a>
            <a href="?key=<?= urlencode($secretPath) ?>&tab=rich-snippets" class="tab <?= $currentTab === 'rich-snippets' ? 'active' : '' ?>">
                Rich Snippets
            </a>
            <a href="?key=<?= urlencode($secretPath) ?>&tab=backup" class="tab <?= $currentTab === 'backup' ? 'active' : '' ?>">
                Backup
            </a>
        </div>

        <!-- Submissions Tab -->
        <div class="tab-content <?= $currentTab === 'submissions' ? 'active' : '' ?>" id="submissions-tab">

        <div class="stats">
            <div class="stat">
                <div class="stat-value" id="stat-total"><?= $totalSubmissions ?></div>
                <div class="stat-label">Total Submissions</div>
            </div>
            <div class="stat">
                <div class="stat-value" id="stat-quotes"><?= $quoteCount ?></div>
                <div class="stat-label">Quote Requests</div>
            </div>
            <div class="stat">
                <div class="stat-value" id="stat-today"><?= $todayCount ?></div>
                <div class="stat-label">Today</div>
            </div>
            <div class="stat">
                <div class="stat-value" id="stat-week"><?= $last7DaysCount ?></div>
                <div class="stat-label">Last 7 Days</div>
            </div>
        </div>

        <!-- Filter Bar -->
        <div class="filter-bar">
            <div class="filter-group">
                <label>From:</label>
                <input type="date" id="date-from" />
            </div>
            <div class="filter-group">
                <label>To:</label>
                <input type="date" id="date-to" />
            </div>
            <div class="filter-group">
                <label>Type:</label>
                <select id="type-filter">
                    <option value="all">All</option>
                    <option value="quote">Quotes</option>
                    <option value="message">Messages</option>
                </select>
            </div>
            <div class="filter-group">
                <label>Search:</label>
                <input type="text" id="search-input" placeholder="Name, email, phone..." />
            </div>
            <div class="quick-filters">
                <button class="quick-filter active" data-days="30">Last 30 Days</button>
                <button class="quick-filter" data-days="7">Last 7 Days</button>
                <button class="quick-filter" data-days="0">Today</button>
                <button class="quick-filter" data-days="-1">All Time</button>
            </div>
            <span class="results-count"><span id="filtered-count">0</span> results</span>
        </div>

        <div class="actions">
            <a href="?key=<?= urlencode($secretPath) ?>&export=csv" class="btn btn-secondary">Export CSV</a>
            <a href="?key=<?= urlencode($secretPath) ?>" class="btn btn-secondary">Refresh</a>
        </div>

        <div class="submissions" id="submissions-container">
            <!-- Submissions will be rendered by JavaScript -->
        </div>

        <div class="empty" id="empty-state" style="display: none;">
            <p>No submissions match your filters.</p>
        </div>

        </div><!-- End Submissions Tab -->

        <!-- Spam Tab -->
        <div class="tab-content <?= $currentTab === 'spam' ? 'active' : '' ?>" id="spam-tab">
            <div class="stats">
                <div class="stat">
                    <div class="stat-value"><?= $spamCount ?></div>
                    <div class="stat-label">Total Blocked</div>
                </div>
                <div class="stat">
                    <div class="stat-value"><?= $spamToday ?></div>
                    <div class="stat-label">Today</div>
                </div>
            </div>

            <div style="background: #fef3c7; border: 1px solid #fcd34d; border-radius: 0.5rem; padding: 1rem; margin-bottom: 1.5rem;">
                <p style="font-size: 0.875rem; color: #92400e;">
                    <strong>Spam Protection Active:</strong> These submissions were blocked by the spam filters.
                    Blocked users see a fake "success" message so they don't know they were caught.
                </p>
            </div>

            <?php if (isset($_GET['reanalyzed'])): ?>
            <div style="background: #d1fae5; border: 1px solid #10b981; border-radius: 0.5rem; padding: 1rem; margin-bottom: 1.5rem;">
                <p style="font-size: 0.875rem; color: #065f46;">
                    ✓ Re-analyzed <?= (int)$_GET['reanalyzed'] ?> manual entries. Detection details have been added.
                </p>
            </div>
            <?php endif; ?>

            <!-- Re-analyze Button -->
            <form method="POST" style="margin-bottom: 1rem;">
                <input type="hidden" name="action" value="reanalyze_manual">
                <button type="submit" style="background: #6366f1; color: white; padding: 0.5rem 1rem; border: none; border-radius: 0.375rem; cursor: pointer; font-size: 0.875rem;">
                    🔄 Re-analyze Manual Entries
                </button>
                <span style="margin-left: 0.75rem; font-size: 0.8rem; color: #6b7280;">
                    Runs detection analysis on manually-moved spam to populate detailed issue data
                </span>
            </form>

            <!-- Spam Filter Bar -->
            <div class="filter-bar">
                <div class="filter-group">
                    <label>Reason:</label>
                    <select id="spam-reason-filter">
                        <option value="all">All</option>
                        <optgroup label="Basic Protection">
                            <option value="honeypot">Honeypot</option>
                            <option value="time_check">Time Check</option>
                            <option value="rate_limit">Rate Limit</option>
                        </optgroup>
                        <optgroup label="Content Filtering">
                            <option value="content_filter:spam_phrase">Spam Phrases</option>
                            <option value="content_filter:excessive_urls">Excessive URLs</option>
                            <option value="excessive_caps">Excessive Caps</option>
                            <option value="disposable_email">Disposable Email</option>
                        </optgroup>
                        <optgroup label="Advanced Bot Detection">
                            <option value="gmail_dot_pattern">Gmail Dot Pattern</option>
                            <option value="invalid_name">Name Validation</option>
                            <option value="invalid_phone">Phone Area Code</option>
                            <option value="gibberish_message">Gibberish Message</option>
                            <option value="gibberish_">Gibberish Detection</option>
                        </optgroup>
                        <optgroup label="Manual">
                            <option value="manual_review">Manual Review</option>
                        </optgroup>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Search:</label>
                    <input type="text" id="spam-search-input" placeholder="Email, IP..." />
                </div>
                <span class="results-count"><span id="spam-filtered-count">0</span> blocked</span>
            </div>

            <div class="submissions" id="spam-container">
                <!-- Spam entries will be rendered by JavaScript -->
            </div>

            <div class="empty" id="spam-empty-state" style="display: none;">
                <p>No spam caught yet. That's a good thing!</p>
            </div>
        </div><!-- End Spam Tab -->

        <!-- Reviews Tab -->
        <div class="tab-content <?= $currentTab === 'reviews' ? 'active' : '' ?>" id="reviews-tab">
            <div class="reviews-header">
                <div class="reviews-stats">
                    <div class="reviews-stat">
                        <span class="reviews-stat-value"><?= $reviewsTotalCount ?></span>
                        <span class="reviews-stat-label">Reviews</span>
                    </div>
                    <div class="reviews-stat">
                        <span class="stars">★★★★★</span>
                        <span class="reviews-stat-value"><?= number_format($reviewsAvgRating, 2) ?></span>
                    </div>
                    <div class="reviews-stat">
                        <span class="reviews-stat-label">Last sync: <?= htmlspecialchars($reviewsLastSync) ?></span>
                    </div>
                </div>
                <div class="reviews-actions">
                    <button type="button" class="btn btn-secondary" id="add-review-btn" onclick="openAddReviewModal()">
                        + Add Review
                    </button>
                    <button type="button" class="btn btn-primary" id="tag-reviews-btn" onclick="tagReviews()">
                        Tag Reviews with AI
                    </button>
                </div>
            </div>

            <!-- Add/Edit Review Modal -->
            <div id="add-review-modal" class="modal" style="display: none;">
                <div class="modal-content" style="max-width: 550px;">
                    <div class="modal-header">
                        <h3 id="review-modal-title">Add New Review</h3>
                        <button class="modal-close" onclick="closeAddReviewModal()">&times;</button>
                    </div>
                    <div class="modal-body">
                        <form id="add-review-form" onsubmit="submitReviewForm(event)">
                            <input type="hidden" name="review_id" id="edit-review-id" value="">
                            <div style="margin-bottom: 1rem;">
                                <label style="display: block; font-weight: 600; margin-bottom: 0.25rem; font-size: 0.875rem;">
                                    Name <span style="color: #dc2626;">*</span>
                                </label>
                                <input type="text" name="author" id="review-author" required style="width: 100%; padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 0.375rem; font-size: 0.875rem;" placeholder="Customer name">
                            </div>
                            <div style="display: flex; gap: 1rem; margin-bottom: 1rem;">
                                <div style="flex: 1;">
                                    <label style="display: block; font-weight: 600; margin-bottom: 0.25rem; font-size: 0.875rem;">
                                        Star Rating <span style="color: #dc2626;">*</span>
                                    </label>
                                    <div id="star-rating-input" style="display: flex; gap: 0.25rem; font-size: 1.5rem; cursor: pointer;">
                                        <span class="star-input" data-rating="1" style="color: #d1d5db;">★</span>
                                        <span class="star-input" data-rating="2" style="color: #d1d5db;">★</span>
                                        <span class="star-input" data-rating="3" style="color: #d1d5db;">★</span>
                                        <span class="star-input" data-rating="4" style="color: #d1d5db;">★</span>
                                        <span class="star-input" data-rating="5" style="color: #d1d5db;">★</span>
                                    </div>
                                    <input type="hidden" name="rating" id="rating-value" value="" required>
                                </div>
                                <div style="flex: 1;">
                                    <label style="display: block; font-weight: 600; margin-bottom: 0.25rem; font-size: 0.875rem;">
                                        Date <span style="color: #dc2626;">*</span>
                                    </label>
                                    <input type="date" name="date" id="review-date" required style="width: 100%; padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 0.375rem; font-size: 0.875rem;" value="<?= date('Y-m-d') ?>">
                                </div>
                            </div>
                            <div style="margin-bottom: 1rem;">
                                <label style="display: block; font-weight: 600; margin-bottom: 0.25rem; font-size: 0.875rem;">
                                    Review Text <span style="color: #9ca3af; font-weight: normal;">(optional)</span>
                                </label>
                                <textarea name="text" id="review-text" rows="4" style="width: 100%; padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 0.375rem; font-size: 0.875rem; resize: vertical;" placeholder="Customer's review..."></textarea>
                            </div>
                            <div style="margin-bottom: 1rem;">
                                <label style="display: block; font-weight: 600; margin-bottom: 0.25rem; font-size: 0.875rem;">
                                    Owner Response <span style="color: #9ca3af; font-weight: normal;">(optional)</span>
                                </label>
                                <textarea name="ownerResponse" id="review-owner-response" rows="3" style="width: 100%; padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 0.375rem; font-size: 0.875rem; resize: vertical;" placeholder="Your response to the review..."></textarea>
                            </div>
                            <div style="display: flex; gap: 0.5rem; justify-content: flex-end; margin-top: 1.5rem;">
                                <button type="button" class="btn btn-secondary" onclick="closeAddReviewModal()">Cancel</button>
                                <button type="submit" class="btn btn-primary" id="review-submit-btn">Add Review</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Tag Reviews Modal -->
            <div id="tag-modal" class="modal" style="display: none;">
                <div class="modal-content">
                    <div class="modal-header">
                        <h3>Tag Reviews with AI</h3>
                        <button class="modal-close" onclick="closeTagModal()">&times;</button>
                    </div>
                    <div class="modal-body" id="tag-modal-body">
                        <p>Analyzing reviews with OpenAI to determine which pages they should appear on...</p>
                        <div class="spinner"></div>
                    </div>
                </div>
            </div>

            <!-- Reviews Filter Bar -->
            <div class="reviews-filters">
                <div class="reviews-filter-group">
                    <label>From:</label>
                    <input type="date" id="reviews-date-from" />
                </div>
                <div class="reviews-filter-group">
                    <label>To:</label>
                    <input type="date" id="reviews-date-to" />
                </div>
                <div class="reviews-filter-group">
                    <label>Sort:</label>
                    <select id="reviews-sort">
                        <option value="desc">Newest First</option>
                        <option value="asc">Oldest First</option>
                    </select>
                </div>
                <div class="reviews-filter-group">
                    <label>Search:</label>
                    <input type="text" id="reviews-search" placeholder="Name, review text..." />
                </div>
                <span class="reviews-results-count"><span id="reviews-count">0</span> reviews</span>
            </div>

            <div class="reviews-list" id="reviews-container">
                <!-- Reviews will be rendered by JavaScript -->
            </div>

            <div class="reviews-pagination" id="reviews-pagination">
                <!-- Pagination will be rendered by JavaScript -->
            </div>
        </div><!-- End Reviews Tab -->

        <!-- Rich Snippets Tab -->
        <div class="tab-content <?= $currentTab === 'rich-snippets' ? 'active' : '' ?>" id="rich-snippets-tab">
            <?php if (isset($productsSaved) && $productsSaved): ?>
                <div class="alert">Product pricing saved successfully! <strong>Rebuild and deploy to apply changes.</strong></div>
            <?php endif; ?>

            <div class="rich-snippets-header" style="margin-bottom: 1.5rem;">
                <h2 style="font-size: 1.25rem; font-weight: 600; margin-bottom: 0.5rem;">Rich Snippets Configuration</h2>
                <p style="color: #6b7280; font-size: 0.875rem;">Manage product pricing and structured data that appears in Google search results.</p>
            </div>

            <!-- Review Stats Card -->
            <div style="background: white; padding: 1.5rem; border-radius: 0.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1); margin-bottom: 1.5rem;">
                <h3 style="font-size: 1rem; font-weight: 600; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;">
                    <span style="color: #fbbf24;">★</span> Aggregate Rating
                </h3>
                <div style="display: flex; gap: 2rem; flex-wrap: wrap;">
                    <div>
                        <div style="font-size: 2rem; font-weight: 700; color: #d97706;"><?= number_format($reviewsAvgRating, 1) ?></div>
                        <div style="font-size: 0.75rem; color: #6b7280;">Average Rating</div>
                    </div>
                    <div>
                        <div style="font-size: 2rem; font-weight: 700; color: #d97706;"><?= $reviewsTotalCount ?></div>
                        <div style="font-size: 0.75rem; color: #6b7280;">Total Reviews</div>
                    </div>
                    <div>
                        <div style="font-size: 0.875rem; color: #6b7280; margin-bottom: 0.25rem;">Last Sync</div>
                        <div style="font-size: 0.875rem; font-weight: 500;"><?= htmlspecialchars($reviewsLastSync) ?></div>
                    </div>
                </div>
                <p style="font-size: 0.75rem; color: #9ca3af; margin-top: 1rem;">
                    This rating appears on all product pages in search results. Update reviews in the Reviews tab.
                </p>
            </div>

            <!-- Products Form -->
            <form method="POST" action="?key=<?= urlencode($secretPath) ?>&tab=rich-snippets">
                <input type="hidden" name="action" value="save_products">

                <div style="background: white; padding: 1.5rem; border-radius: 0.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                        <h3 style="font-size: 1rem; font-weight: 600;">Container Products</h3>
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </div>

                    <div style="overflow-x: auto;">
                        <table style="width: 100%; border-collapse: collapse; min-width: 800px;">
                            <thead>
                                <tr style="background: #f9fafb; border-bottom: 1px solid #e5e7eb;">
                                    <th style="padding: 0.75rem 1rem; text-align: left; font-size: 0.75rem; font-weight: 600; color: #6b7280; text-transform: uppercase;">Size</th>
                                    <th style="padding: 0.75rem 1rem; text-align: left; font-size: 0.75rem; font-weight: 600; color: #6b7280; text-transform: uppercase;">Name</th>
                                    <th style="padding: 0.75rem 1rem; text-align: center; font-size: 0.75rem; font-weight: 600; color: #6b7280; text-transform: uppercase; background: #ecfdf5;">New Price Range</th>
                                    <th style="padding: 0.75rem 1rem; text-align: center; font-size: 0.75rem; font-weight: 600; color: #6b7280; text-transform: uppercase; background: #fef3c7;">Used Price Range</th>
                                    <th style="padding: 0.75rem 1rem; text-align: left; font-size: 0.75rem; font-weight: 600; color: #6b7280; text-transform: uppercase;">Page</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($productsConfig as $index => $product): ?>
                                    <tr style="border-bottom: 1px solid #e5e7eb;">
                                        <td style="padding: 0.75rem 1rem;">
                                            <input type="hidden" name="products[<?= $index ?>][size]" value="<?= htmlspecialchars($product['size'] ?? '') ?>">
                                            <input type="hidden" name="products[<?= $index ?>][slug]" value="<?= htmlspecialchars($product['slug'] ?? '') ?>">
                                            <input type="hidden" name="products[<?= $index ?>][description]" value="<?= htmlspecialchars($product['description'] ?? '') ?>">
                                            <span style="font-weight: 600;"><?= htmlspecialchars($product['size'] ?? '') ?></span>
                                        </td>
                                        <td style="padding: 0.75rem 1rem;">
                                            <input type="text" name="products[<?= $index ?>][name]" value="<?= htmlspecialchars($product['name'] ?? '') ?>" style="width: 100%; padding: 0.375rem 0.5rem; border: 1px solid #d1d5db; border-radius: 0.25rem; font-size: 0.875rem;">
                                        </td>
                                        <td style="padding: 0.75rem 0.5rem; background: #ecfdf5;">
                                            <div style="display: flex; gap: 0.25rem; align-items: center; justify-content: center;">
                                                <span style="font-size: 0.75rem; color: #6b7280;">$</span>
                                                <input type="number" name="products[<?= $index ?>][new_min]" value="<?= htmlspecialchars($product['new']['min'] ?? '') ?>" placeholder="Min" style="width: 70px; padding: 0.375rem 0.5rem; border: 1px solid #d1d5db; border-radius: 0.25rem; font-size: 0.875rem; text-align: right;">
                                                <span style="font-size: 0.75rem; color: #6b7280;">-</span>
                                                <input type="number" name="products[<?= $index ?>][new_max]" value="<?= htmlspecialchars($product['new']['max'] ?? '') ?>" placeholder="Max" style="width: 70px; padding: 0.375rem 0.5rem; border: 1px solid #d1d5db; border-radius: 0.25rem; font-size: 0.875rem; text-align: right;">
                                            </div>
                                        </td>
                                        <td style="padding: 0.75rem 0.5rem; background: #fef3c7;">
                                            <div style="display: flex; gap: 0.25rem; align-items: center; justify-content: center;">
                                                <span style="font-size: 0.75rem; color: #6b7280;">$</span>
                                                <input type="number" name="products[<?= $index ?>][used_min]" value="<?= htmlspecialchars($product['used']['min'] ?? '') ?>" placeholder="Min" style="width: 70px; padding: 0.375rem 0.5rem; border: 1px solid #d1d5db; border-radius: 0.25rem; font-size: 0.875rem; text-align: right;">
                                                <span style="font-size: 0.75rem; color: #6b7280;">-</span>
                                                <input type="number" name="products[<?= $index ?>][used_max]" value="<?= htmlspecialchars($product['used']['max'] ?? '') ?>" placeholder="Max" style="width: 70px; padding: 0.375rem 0.5rem; border: 1px solid #d1d5db; border-radius: 0.25rem; font-size: 0.875rem; text-align: right;">
                                            </div>
                                        </td>
                                        <td style="padding: 0.75rem 1rem;">
                                            <a href="/containers/<?= htmlspecialchars($product['slug'] ?? '') ?>" target="_blank" style="color: #d97706; font-size: 0.75rem;">
                                                /containers/<?= htmlspecialchars($product['slug'] ?? '') ?>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <p style="font-size: 0.75rem; color: #9ca3af; margin-top: 1rem;">
                        Leave price fields empty if that condition is not available. Prices shown are the starting prices that appear in Google search results.
                    </p>
                </div>

                <div style="margin-top: 1rem; display: flex; gap: 0.5rem; justify-content: flex-end;">
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>

            <!-- Testing Links -->
            <div style="background: #fffbeb; border: 1px solid #fcd34d; border-radius: 0.5rem; padding: 1rem; margin-top: 1.5rem;">
                <p style="font-size: 0.875rem; font-weight: 500; color: #92400e; margin-bottom: 0.75rem;">
                    🔍 Test Your Rich Snippets
                </p>
                <div style="display: flex; flex-wrap: wrap; gap: 0.5rem;">
                    <a href="https://search.google.com/test/rich-results?url=https://ccansam.com" target="_blank" rel="noopener" class="btn btn-secondary" style="font-size: 0.75rem; padding: 0.375rem 0.75rem;">Test Homepage</a>
                    <?php foreach ($productsConfig as $product): ?>
                        <a href="https://search.google.com/test/rich-results?url=https://ccansam.com/containers/<?= urlencode($product['slug'] ?? '') ?>" target="_blank" rel="noopener" class="btn btn-secondary" style="font-size: 0.75rem; padding: 0.375rem 0.75rem;">Test <?= htmlspecialchars($product['size'] ?? '') ?></a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div><!-- End Rich Snippets Tab -->

        <!-- Backup Tab -->
        <div class="tab-content <?= $currentTab === 'backup' ? 'active' : '' ?>" id="backup-tab">
            <div class="rich-snippets-header" style="margin-bottom: 1.5rem;">
                <h2 style="font-size: 1.25rem; font-weight: 600; margin-bottom: 0.5rem;">Data Backup</h2>
                <p style="color: #6b7280; font-size: 0.875rem;">Backup your data files and have them emailed to you.</p>
            </div>

            <!-- Size Warning (shown dynamically) -->
            <div id="backup-size-warning" style="background: #fef3c7; border: 1px solid #fcd34d; border-radius: 0.5rem; padding: 1rem; margin-bottom: 1.5rem; display: none;">
                <p style="font-size: 0.875rem; color: #92400e; margin: 0;">
                    <strong>Warning:</strong> <span id="backup-warning-text"></span>
                </p>
            </div>

            <!-- Stats Summary -->
            <div style="background: white; padding: 1.5rem; border-radius: 0.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1); margin-bottom: 1.5rem;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                    <h3 style="font-size: 1rem; font-weight: 600;">Data Summary</h3>
                    <button type="button" class="btn btn-primary" id="create-backup-btn" onclick="createBackup()">
                        Create Backup
                    </button>
                </div>

                <div id="backup-stats-loading" style="text-align: center; padding: 2rem;">
                    <div class="spinner"></div>
                    <p style="color: #6b7280; margin-top: 0.5rem;">Loading...</p>
                </div>

                <div id="backup-stats" style="display: none;">
                    <div style="display: flex; gap: 2rem; flex-wrap: wrap; margin-bottom: 1rem;">
                        <div>
                            <div id="backup-total-size" style="font-size: 2rem; font-weight: 700; color: #d97706;">--</div>
                            <div style="font-size: 0.75rem; color: #6b7280;">Total Data Size</div>
                        </div>
                        <div>
                            <div id="backup-file-count" style="font-size: 2rem; font-weight: 700; color: #d97706;">--</div>
                            <div style="font-size: 0.75rem; color: #6b7280;">Files to Backup</div>
                        </div>
                        <div>
                            <div id="backup-count" style="font-size: 2rem; font-weight: 700; color: #d97706;">--</div>
                            <div style="font-size: 0.75rem; color: #6b7280;">Existing Backups</div>
                        </div>
                    </div>

                    <div style="overflow-x: auto;">
                        <table style="width: 100%; border-collapse: collapse;">
                            <thead>
                                <tr style="background: #f9fafb; border-bottom: 1px solid #e5e7eb;">
                                    <th style="padding: 0.5rem 0.75rem; text-align: left; font-size: 0.75rem; font-weight: 600; color: #6b7280;">File</th>
                                    <th style="padding: 0.5rem 0.75rem; text-align: right; font-size: 0.75rem; font-weight: 600; color: #6b7280;">Size</th>
                                    <th style="padding: 0.5rem 0.75rem; text-align: right; font-size: 0.75rem; font-weight: 600; color: #6b7280;">Records</th>
                                    <th style="padding: 0.5rem 0.75rem; text-align: center; font-size: 0.75rem; font-weight: 600; color: #6b7280;">Status</th>
                                </tr>
                            </thead>
                            <tbody id="backup-stats-table">
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Backup History -->
            <div style="background: white; padding: 1.5rem; border-radius: 0.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                <h3 style="font-size: 1rem; font-weight: 600; margin-bottom: 1rem;">Backup History</h3>
                <p style="font-size: 0.75rem; color: #9ca3af; margin-bottom: 1rem;">
                    Up to 3 backups are kept. Creating a 4th backup will delete the oldest one.
                </p>

                <div id="backup-history-loading" style="text-align: center; padding: 2rem; display: none;">
                    <div class="spinner"></div>
                </div>

                <div id="backup-history-empty" style="text-align: center; padding: 2rem; color: #9ca3af; display: none;">
                    <p>No backups yet. Click "Create Backup" to create your first backup.</p>
                </div>

                <div id="backup-history" style="display: none;">
                    <table style="width: 100%; border-collapse: collapse;">
                        <thead>
                            <tr style="background: #f9fafb; border-bottom: 1px solid #e5e7eb;">
                                <th style="padding: 0.5rem 0.75rem; text-align: left; font-size: 0.75rem; font-weight: 600; color: #6b7280;">Filename</th>
                                <th style="padding: 0.5rem 0.75rem; text-align: right; font-size: 0.75rem; font-weight: 600; color: #6b7280;">Size</th>
                                <th style="padding: 0.5rem 0.75rem; text-align: left; font-size: 0.75rem; font-weight: 600; color: #6b7280;">Created</th>
                                <th style="padding: 0.5rem 0.75rem; text-align: center; font-size: 0.75rem; font-weight: 600; color: #6b7280;">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="backup-history-table">
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Help Info -->
            <div style="background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 0.5rem; padding: 1rem; margin-top: 1.5rem;">
                <p style="font-size: 0.875rem; font-weight: 500; color: #1e40af; margin-bottom: 0.5rem;">
                    How Backups Work
                </p>
                <ul style="font-size: 0.75rem; color: #1e40af; margin: 0; padding-left: 1.25rem;">
                    <li>Backups include all data files (submissions, reviews, inventory, spam log) and config.yaml</li>
                    <li>Backups are compressed as .tgz files and emailed to <?= htmlspecialchars($config['contact_form']['recipient_email'] ?? $config['contact']['email'] ?? 'the admin') ?></li>
                    <li>Extract with: <code style="background: #dbeafe; padding: 0.125rem 0.375rem; border-radius: 0.25rem;">tar -xzf backup.tgz</code></li>
                    <li>Maximum 3 backups are stored on the server</li>
                </ul>
            </div>
        </div><!-- End Backup Tab -->

        <!-- Backup Confirmation Modal -->
        <div id="backup-confirm-modal" class="modal" style="display: none;">
            <div class="modal-content">
                <div class="modal-header">
                    <h3>Confirm Backup</h3>
                    <button class="modal-close" onclick="closeBackupModal()">&times;</button>
                </div>
                <div class="modal-body" id="backup-confirm-body">
                    <p id="backup-confirm-message"></p>
                    <div style="display: flex; gap: 0.5rem; justify-content: flex-end; margin-top: 1rem;">
                        <button type="button" class="btn btn-secondary" onclick="closeBackupModal()">Cancel</button>
                        <button type="button" class="btn btn-primary" id="backup-confirm-btn" onclick="confirmBackup()">
                            Create Backup Anyway
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Backup Progress Modal -->
        <div id="backup-progress-modal" class="modal" style="display: none;">
            <div class="modal-content">
                <div class="modal-header">
                    <h3>Creating Backup</h3>
                </div>
                <div class="modal-body" id="backup-progress-body">
                    <div class="spinner"></div>
                    <p style="text-align: center; margin-top: 1rem;" id="backup-progress-text">Creating backup and sending email...</p>
                </div>
            </div>
        </div>

    </div>

    <script>
        // All submissions data from PHP
        const allSubmissions = <?= $submissionsJson ?>;
        const secretPath = '<?= urlencode($secretPath) ?>';

        // DOM elements
        const dateFromInput = document.getElementById('date-from');
        const dateToInput = document.getElementById('date-to');
        const typeFilter = document.getElementById('type-filter');
        const searchInput = document.getElementById('search-input');
        const quickFilters = document.querySelectorAll('.quick-filter');
        const container = document.getElementById('submissions-container');
        const emptyState = document.getElementById('empty-state');
        const filteredCountEl = document.getElementById('filtered-count');

        // Set default dates (last 30 days)
        function setDateRange(days) {
            const today = new Date();
            const toDate = today.toISOString().split('T')[0];
            dateToInput.value = toDate;

            if (days === -1) {
                // All time
                dateFromInput.value = '';
            } else if (days === 0) {
                // Today
                dateFromInput.value = toDate;
            } else {
                const fromDate = new Date(today);
                fromDate.setDate(fromDate.getDate() - days);
                dateFromInput.value = fromDate.toISOString().split('T')[0];
            }
            filterSubmissions();
        }

        // Initialize with last 30 days
        setDateRange(30);

        // Helper: get display name
        function getDisplayName(sub) {
            if (sub.name) return sub.name;
            return ((sub.firstName || '') + ' ' + (sub.lastName || '')).trim();
        }

        // Filter submissions based on current filters
        function filterSubmissions() {
            const fromDate = dateFromInput.value;
            const toDate = dateToInput.value;
            const type = typeFilter.value;
            const search = searchInput.value.toLowerCase().trim();

            const filtered = allSubmissions.filter(sub => {
                // Date filter
                if (fromDate && sub.date < fromDate) return false;
                if (toDate && sub.date > toDate) return false;

                // Type filter
                const formType = sub.formType || 'message';
                if (type !== 'all' && formType !== type) return false;

                // Search filter
                if (search) {
                    const searchFields = [
                        getDisplayName(sub),
                        sub.email || '',
                        sub.phone || '',
                        sub.message || '',
                        sub.containerSize || '',
                        sub.city || '',
                        sub.subject || ''
                    ].join(' ').toLowerCase();
                    if (!searchFields.includes(search)) return false;
                }

                return true;
            });

            renderSubmissions(filtered);
            filteredCountEl.textContent = filtered.length;
        }

        // Render submissions to DOM
        function renderSubmissions(submissions) {
            if (submissions.length === 0) {
                container.innerHTML = '';
                emptyState.style.display = 'block';
                return;
            }

            emptyState.style.display = 'none';

            container.innerHTML = submissions.map((sub, index) => {
                const formType = sub.formType || 'message';
                const isQuote = formType === 'quote';
                const displayName = getDisplayName(sub);
                const preview = isQuote
                    ? [sub.containerSize, sub.condition, sub.intention].filter(Boolean).join(' · ')
                    : (sub.subject || 'General Inquiry');

                let detailsHtml = `
                    <div class="detail-item">
                        <div class="detail-label">Name</div>
                        <div class="detail-value">${escapeHtml(displayName)}</div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Email</div>
                        <div class="detail-value"><a href="mailto:${escapeHtml(sub.email)}">${escapeHtml(sub.email)}</a></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Phone</div>
                        <div class="detail-value">${escapeHtml(sub.phone || '')}</div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Date & Time</div>
                        <div class="detail-value">${escapeHtml(sub.date + ' ' + sub.time)}</div>
                    </div>
                `;

                if (isQuote) {
                    detailsHtml += `
                        <div class="detail-item">
                            <div class="detail-label">Container Size</div>
                            <div class="detail-value">${escapeHtml(sub.containerSize || '')}</div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Condition</div>
                            <div class="detail-value">${escapeHtml(sub.condition || '')}</div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Intention</div>
                            <div class="detail-value">${escapeHtml(sub.intention || '')}</div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Delivery</div>
                            <div class="detail-value">${escapeHtml(sub.delivery || '')}</div>
                        </div>
                    `;
                    if (sub.locationType) {
                        detailsHtml += `
                            <div class="detail-item">
                                <div class="detail-label">Location Type</div>
                                <div class="detail-value">${escapeHtml(sub.locationType)}</div>
                            </div>
                        `;
                        if (sub.locationType === 'Urban') {
                            detailsHtml += `
                                <div class="detail-item">
                                    <div class="detail-label">Street Address</div>
                                    <div class="detail-value">${escapeHtml(sub.streetAddress || '')}</div>
                                </div>
                                <div class="detail-item">
                                    <div class="detail-label">City</div>
                                    <div class="detail-value">${escapeHtml(sub.city || '')}</div>
                                </div>
                                <div class="detail-item">
                                    <div class="detail-label">Postal Code</div>
                                    <div class="detail-value">${escapeHtml(sub.postalCode || '')}</div>
                                </div>
                            `;
                        } else {
                            detailsHtml += `
                                <div class="detail-item">
                                    <div class="detail-label">Land Location</div>
                                    <div class="detail-value">${escapeHtml(sub.landLocation || '')}</div>
                                </div>
                            `;
                            if (sub.additionalDirections) {
                                detailsHtml += `
                                    <div class="detail-item">
                                        <div class="detail-label">Additional Directions</div>
                                        <div class="detail-value">${escapeHtml(sub.additionalDirections)}</div>
                                    </div>
                                `;
                            }
                        }
                    }
                } else {
                    detailsHtml += `
                        <div class="detail-item">
                            <div class="detail-label">Subject</div>
                            <div class="detail-value">${escapeHtml(sub.subject || 'General Inquiry')}</div>
                        </div>
                    `;
                }

                let messageHtml = '';
                if (sub.message) {
                    messageHtml = `
                        <div class="message-section">
                            <div class="message-label">Message</div>
                            <div class="message-content">${escapeHtml(sub.message)}</div>
                        </div>
                    `;
                }

                return `
                    <div class="submission-row" id="row-${index}">
                        <div class="submission-summary" onclick="toggleRow(${index})">
                            <span class="expand-icon">▶</span>
                            <span class="badge ${isQuote ? 'badge-quote' : 'badge-message'}">
                                ${isQuote ? 'Quote' : 'Message'}
                            </span>
                            <span class="summary-name">${escapeHtml(displayName)}</span>
                            <span class="summary-email">${escapeHtml(sub.email)}</span>
                            <span class="summary-phone">${escapeHtml(sub.phone || '')}</span>
                            <span class="summary-preview">${escapeHtml(preview)}</span>
                            <span class="summary-date">${escapeHtml(sub.date)}</span>
                        </div>
                        <div class="submission-details">
                            <div class="details-grid">
                                ${detailsHtml}
                            </div>
                            ${messageHtml}
                            <div class="details-footer">
                                <span>ID: ${escapeHtml(sub.id)} · IP: ${escapeHtml(sub.ip)}</span>
                                <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this submission?');">
                                    <input type="hidden" name="delete_id" value="${escapeHtml(sub.id)}">
                                    <button type="submit" class="btn btn-danger">Delete</button>
                                </form>
                            </div>
                        </div>
                    </div>
                `;
            }).join('');
        }

        // Toggle row expansion
        function toggleRow(index) {
            const row = document.getElementById('row-' + index);
            row.classList.toggle('expanded');
        }

        // Escape HTML
        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // Event listeners
        dateFromInput.addEventListener('change', () => {
            clearQuickFilterActive();
            filterSubmissions();
        });
        dateToInput.addEventListener('change', () => {
            clearQuickFilterActive();
            filterSubmissions();
        });
        typeFilter.addEventListener('change', filterSubmissions);
        searchInput.addEventListener('input', filterSubmissions);

        quickFilters.forEach(btn => {
            btn.addEventListener('click', () => {
                quickFilters.forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                setDateRange(parseInt(btn.dataset.days));
            });
        });

        function clearQuickFilterActive() {
            quickFilters.forEach(b => b.classList.remove('active'));
        }

        // Initial render
        filterSubmissions();

        // ============================================
        // Reviews Tab Functionality
        // ============================================
        const allReviews = <?= $reviewsJson ?>;
        const reviewsContainer = document.getElementById('reviews-container');
        const reviewsPagination = document.getElementById('reviews-pagination');
        const reviewsSearchInput = document.getElementById('reviews-search');
        const reviewsDateFromInput = document.getElementById('reviews-date-from');
        const reviewsDateToInput = document.getElementById('reviews-date-to');
        const reviewsSortSelect = document.getElementById('reviews-sort');
        const reviewsCountEl = document.getElementById('reviews-count');

        const REVIEWS_PER_PAGE = 10;
        let currentReviewsPage = 1;
        let filteredReviews = [...allReviews];

        // Set default date range (all time - leave empty for no restriction)
        function initReviewsDateRange() {
            const today = new Date().toISOString().split('T')[0];
            reviewsDateToInput.value = today;
            // Leave from date empty to show all
        }

        function getInitials(name) {
            if (!name) return '?';
            const parts = name.trim().split(' ');
            if (parts.length === 1) return parts[0].charAt(0).toUpperCase();
            return (parts[0].charAt(0) + parts[parts.length - 1].charAt(0)).toUpperCase();
        }

        function renderStars(rating) {
            let stars = '';
            for (let i = 1; i <= 5; i++) {
                stars += `<span class="review-star ${i <= rating ? '' : 'empty'}">★</span>`;
            }
            return stars;
        }

        function getRelativeTime(dateStr) {
            if (!dateStr) return '';
            const date = new Date(dateStr);
            const now = new Date();
            const diffMs = now - date;
            const diffDays = Math.floor(diffMs / (1000 * 60 * 60 * 24));

            if (diffDays < 1) return 'today';
            if (diffDays === 1) return 'yesterday';
            if (diffDays < 7) return `${diffDays} days ago`;
            if (diffDays < 14) return 'a week ago';
            if (diffDays < 30) return `${Math.floor(diffDays / 7)} weeks ago`;
            if (diffDays < 60) return 'a month ago';
            if (diffDays < 365) return `${Math.floor(diffDays / 30)} months ago`;
            if (diffDays < 730) return 'a year ago';
            return `${Math.floor(diffDays / 365)} years ago`;
        }

        function filterReviews() {
            const search = reviewsSearchInput.value.toLowerCase().trim();
            const fromDate = reviewsDateFromInput.value;
            const toDate = reviewsDateToInput.value;
            const sortOrder = reviewsSortSelect.value;

            // Filter
            filteredReviews = allReviews.filter(review => {
                // Date filter
                if (fromDate && review.date < fromDate) return false;
                if (toDate && review.date > toDate) return false;

                // Search filter
                if (search) {
                    const searchText = [
                        review.author || '',
                        review.text || '',
                        review.ownerResponse?.text || ''
                    ].join(' ').toLowerCase();
                    if (!searchText.includes(search)) return false;
                }

                return true;
            });

            // Sort
            filteredReviews.sort((a, b) => {
                const dateA = new Date(a.date);
                const dateB = new Date(b.date);
                return sortOrder === 'desc' ? dateB - dateA : dateA - dateB;
            });

            currentReviewsPage = 1;
            renderReviews();
            renderReviewsPagination();
            reviewsCountEl.textContent = filteredReviews.length;
        }

        function renderReviews() {
            const start = (currentReviewsPage - 1) * REVIEWS_PER_PAGE;
            const end = start + REVIEWS_PER_PAGE;
            const pageReviews = filteredReviews.slice(start, end);

            if (pageReviews.length === 0) {
                reviewsContainer.innerHTML = '<div class="empty"><p>No reviews match your search.</p></div>';
                return;
            }

            reviewsContainer.innerHTML = pageReviews.map(review => {
                let badges = '';
                if (review.isNew) badges += '<span class="review-badge new">New</span>';
                if (review.hasPhoto) badges += '<span class="review-badge photo">Photo</span>';
                if (review.isLocalGuide) badges += '<span class="review-badge guide">Local Guide</span>';
                if (review.isEdited) badges += '<span class="review-badge">Edited</span>';

                let responseHtml = '';
                if (review.ownerResponse) {
                    responseHtml = `
                        <div class="review-response">
                            <div class="review-response-header">
                                <img src="/favicon-32.png" alt="C-Can Sam" class="review-response-avatar" />
                                <span>C-Can Sam (Owner)</span>
                            </div>
                            <div class="review-response-text">${escapeHtml(review.ownerResponse.text)}</div>
                        </div>
                    `;
                }

                const sourceLabel = review.source === 'manual' ? '<span style="background: #dbeafe; color: #1e40af; font-size: 0.625rem; padding: 0.125rem 0.375rem; border-radius: 0.25rem; margin-left: 0.5rem;">Manual</span>' : '';

                return `
                    <div class="review-card" data-review-id="${escapeHtml(review.id)}">
                        <div class="review-header">
                            <div class="review-author">
                                <div class="review-avatar">${getInitials(review.author)}</div>
                                <div class="review-author-info">
                                    <div class="review-author-name">${escapeHtml(review.author)}${sourceLabel}</div>
                                    <div class="review-author-meta">
                                        <div class="review-rating">${renderStars(review.rating)}</div>
                                    </div>
                                </div>
                            </div>
                            <div class="review-date" style="display: flex; align-items: center; gap: 0.5rem;">
                                <span>${getRelativeTime(review.date)}</span>
                                <button type="button" class="btn btn-secondary" style="font-size: 0.625rem; padding: 0.125rem 0.375rem; color: #059669;" onclick="editReview('${escapeHtml(review.id)}')">Edit</button>
                                <button type="button" class="btn btn-secondary" style="font-size: 0.625rem; padding: 0.125rem 0.375rem; color: #dc2626;" onclick="deleteReview('${escapeHtml(review.id)}', '${escapeHtml(review.author)}')">Delete</button>
                            </div>
                        </div>
                        ${review.text ? `<div class="review-text">${escapeHtml(review.text)}</div>` : '<div class="review-text" style="color: #9ca3af; font-style: italic;">(No written review)</div>'}
                        ${badges ? `<div class="review-badges">${badges}</div>` : ''}
                        ${responseHtml}
                    </div>
                `;
            }).join('');
        }

        function renderReviewsPagination() {
            const totalPages = Math.ceil(filteredReviews.length / REVIEWS_PER_PAGE);

            if (totalPages <= 1) {
                reviewsPagination.innerHTML = '';
                return;
            }

            let paginationHtml = `
                <button class="page-btn" onclick="goToReviewsPage(${currentReviewsPage - 1})" ${currentReviewsPage === 1 ? 'disabled' : ''}>← Prev</button>
            `;

            const startPage = Math.max(1, currentReviewsPage - 2);
            const endPage = Math.min(totalPages, startPage + 4);

            for (let i = startPage; i <= endPage; i++) {
                paginationHtml += `
                    <button class="page-btn ${i === currentReviewsPage ? 'active' : ''}" onclick="goToReviewsPage(${i})">${i}</button>
                `;
            }

            paginationHtml += `
                <span class="page-info">${filteredReviews.length} reviews</span>
                <button class="page-btn" onclick="goToReviewsPage(${currentReviewsPage + 1})" ${currentReviewsPage === totalPages ? 'disabled' : ''}>Next →</button>
            `;

            reviewsPagination.innerHTML = paginationHtml;
        }

        function goToReviewsPage(page) {
            const totalPages = Math.ceil(filteredReviews.length / REVIEWS_PER_PAGE);
            if (page < 1 || page > totalPages) return;
            currentReviewsPage = page;
            renderReviews();
            renderReviewsPagination();
            reviewsContainer.scrollIntoView({ behavior: 'smooth' });
        }

        // Reviews event listeners
        if (reviewsSearchInput) {
            reviewsSearchInput.addEventListener('input', filterReviews);
        }
        if (reviewsDateFromInput) {
            reviewsDateFromInput.addEventListener('change', filterReviews);
        }
        if (reviewsDateToInput) {
            reviewsDateToInput.addEventListener('change', filterReviews);
        }
        if (reviewsSortSelect) {
            reviewsSortSelect.addEventListener('change', filterReviews);
        }

        // Initial reviews render (only if on reviews tab)
        if (document.getElementById('reviews-tab').classList.contains('active')) {
            initReviewsDateRange();
            filterReviews();
        }

        // Render reviews when tab is clicked (for tabs without page reload)
        document.querySelectorAll('.tab').forEach(tab => {
            tab.addEventListener('click', () => {
                setTimeout(() => {
                    if (document.getElementById('reviews-tab').classList.contains('active')) {
                        initReviewsDateRange();
                        filterReviews();
                    }
                }, 0);
            });
        });

        // ============================================
        // Tag Reviews with AI
        // ============================================
        const tagModal = document.getElementById('tag-modal');
        const tagModalBody = document.getElementById('tag-modal-body');

        function tagReviews() {
            tagModal.style.display = 'flex';
            tagModalBody.innerHTML = `
                <p>Analyzing reviews with OpenAI to determine which pages they should appear on...</p>
                <div class="spinner"></div>
                <p style="font-size: 0.75rem; color: #9ca3af; text-align: center;">This may take 30-60 seconds.</p>
            `;

            fetch(`/api/tag-reviews.php?key=${secretPath}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        tagModalBody.innerHTML = `
                            <p class="tag-success"><strong>Reviews tagged successfully!</strong></p>
                            <p style="margin-top: 0.5rem;">The config.yaml file has been updated with review-to-page mappings.</p>
                            <p style="margin-top: 0.5rem; font-size: 0.875rem; color: #6b7280;">Rebuild the site to see reviews on pages.</p>
                            <details style="margin-top: 1rem;">
                                <summary style="cursor: pointer; font-size: 0.875rem;">Show output</summary>
                                <div class="tag-result">${escapeHtml(data.output || '')}</div>
                            </details>
                        `;
                    } else {
                        tagModalBody.innerHTML = `
                            <p class="tag-error"><strong>Failed to tag reviews</strong></p>
                            <p style="margin-top: 0.5rem;">${escapeHtml(data.message || 'Unknown error')}</p>
                            ${data.hint ? `<p style="margin-top: 0.5rem; font-size: 0.875rem; color: #6b7280;">${escapeHtml(data.hint)}</p>` : ''}
                            <details style="margin-top: 1rem;">
                                <summary style="cursor: pointer; font-size: 0.875rem;">Show output</summary>
                                <div class="tag-result">${escapeHtml(data.output || '')}</div>
                            </details>
                        `;
                    }
                })
                .catch(error => {
                    tagModalBody.innerHTML = `
                        <p class="tag-error"><strong>Error</strong></p>
                        <p style="margin-top: 0.5rem;">${escapeHtml(error.message)}</p>
                        <p style="margin-top: 1rem; font-size: 0.875rem; color: #6b7280;">
                            You can also run this manually:<br>
                            <code style="background: #f3f4f6; padding: 0.25rem 0.5rem; border-radius: 0.25rem;">npm run tag-reviews</code>
                        </p>
                    `;
                });
        }

        function closeTagModal() {
            tagModal.style.display = 'none';
        }

        // Close modal on escape key or click outside
        tagModal.addEventListener('click', (e) => {
            if (e.target === tagModal) closeTagModal();
        });
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && tagModal.style.display !== 'none') closeTagModal();
        });

        // ============================================
        // Spam Tab Functionality
        // ============================================
        const allSpam = <?= $spamJson ?>;
        const spamContainer = document.getElementById('spam-container');
        const spamEmptyState = document.getElementById('spam-empty-state');
        const spamReasonFilter = document.getElementById('spam-reason-filter');
        const spamSearchInput = document.getElementById('spam-search-input');
        const spamFilteredCountEl = document.getElementById('spam-filtered-count');

        function getReasonBadgeClass(reason) {
            // Basic Protection - orange
            if (reason === 'honeypot') return 'badge-quote';
            if (reason === 'time_check') return 'badge-quote';
            if (reason.startsWith('rate_limit')) return 'badge-quote';

            // Content Filtering - blue
            if (reason.startsWith('content_filter') || reason.startsWith('disposable_email') || reason === 'excessive_caps') return 'badge-message';

            // Advanced Bot Detection - red (custom)
            if (reason.startsWith('gmail_dot') || reason.startsWith('invalid_') || reason.startsWith('gibberish_')) return 'badge-spam';

            // Manual Review - gray
            if (reason.startsWith('manual_review')) return 'badge-manual';

            return 'badge-quote';
        }

        function getReasonLabel(reason) {
            // Basic Protection
            if (reason === 'honeypot') return 'Honeypot';
            if (reason === 'time_check') return 'Too Fast';
            if (reason.startsWith('rate_limit')) return 'Rate Limited';

            // Content Filtering
            if (reason.startsWith('content_filter:spam_phrase:')) return 'Spam Phrase: ' + reason.split(':')[2];
            if (reason === 'content_filter:excessive_urls') return 'Too Many URLs';
            if (reason === 'content_filter:excessive_caps' || reason === 'excessive_caps') return 'Excessive Caps';
            if (reason.startsWith('content_filter:disposable_email:') || reason.startsWith('disposable_email:')) return 'Disposable Email';
            if (reason.startsWith('content_filter:')) return 'Content: ' + reason.split(':')[1];

            // Advanced Bot Detection
            if (reason.startsWith('gmail_dot_pattern')) return 'Gmail Dot Pattern';
            if (reason.startsWith('invalid_name:')) return 'Invalid Name: ' + reason.split(':')[1].replace(/_/g, ' ');
            if (reason.startsWith('invalid_phone:invalid_area_code:')) return 'Invalid Area Code: ' + reason.split(':')[2];
            if (reason.startsWith('invalid_phone:')) return 'Invalid Phone';
            if (reason === 'gibberish_message:no_real_words') return 'No Real Words';
            if (reason.startsWith('gibberish_message')) return 'Gibberish Message';
            if (reason.startsWith('gibberish_name')) return 'Gibberish Name';
            if (reason.startsWith('gibberish_')) return 'Gibberish: ' + reason.split('_')[1];

            // Manual Review
            if (reason.startsWith('manual_review:')) return 'Manual: ' + reason.split(':')[1].replace(/_/g, ' ');
            if (reason === 'manual_review') return 'Manual Review';

            return reason;
        }

        function filterSpam() {
            const reasonFilter = spamReasonFilter?.value || 'all';
            const search = spamSearchInput?.value?.toLowerCase().trim() || '';

            const filtered = allSpam.filter(spam => {
                // Reason filter
                if (reasonFilter !== 'all') {
                    if (!spam.reason?.startsWith(reasonFilter)) return false;
                }

                // Search filter
                if (search) {
                    const searchText = [
                        spam.email || '',
                        spam.ip || '',
                        spam.reason || '',
                        spam.userAgent || ''
                    ].join(' ').toLowerCase();
                    if (!searchText.includes(search)) return false;
                }

                return true;
            });

            // Sort by timestamp descending (newest first)
            filtered.sort((a, b) => new Date(b.timestamp) - new Date(a.timestamp));

            renderSpam(filtered);
            if (spamFilteredCountEl) spamFilteredCountEl.textContent = filtered.length;
        }

        function renderSpam(spamEntries) {
            if (!spamContainer) return;

            if (spamEntries.length === 0) {
                spamContainer.innerHTML = '';
                if (spamEmptyState) spamEmptyState.style.display = 'block';
                return;
            }

            if (spamEmptyState) spamEmptyState.style.display = 'none';

            spamContainer.innerHTML = spamEntries.map((spam, index) => {
                const date = new Date(spam.timestamp);
                const dateStr = date.toLocaleDateString();
                const timeStr = date.toLocaleTimeString();
                const originalData = spam.original_data || {};

                // Build the security check matrix
                const checkMatrix = buildSecurityCheckMatrix(spam, originalData);

                return `
                    <div class="submission-row" id="spam-row-${index}">
                        <div class="submission-summary" onclick="toggleSpamRow(${index})">
                            <span class="expand-icon">▶</span>
                            <span class="badge ${getReasonBadgeClass(spam.reason)}">
                                ${escapeHtml(getReasonLabel(spam.reason))}
                            </span>
                            <span class="summary-email">${escapeHtml(spam.email || '(no email)')}</span>
                            <span class="summary-phone">${escapeHtml(spam.ip || '')}</span>
                            <span class="summary-preview">${escapeHtml((originalData.name || originalData.firstName || '').substring(0, 30))}</span>
                            <span class="summary-date">${escapeHtml(dateStr)}</span>
                        </div>
                        <div class="submission-details">
                            <!-- Blocked Reason Banner -->
                            <div style="background: #fee2e2; border: 1px solid #fecaca; border-radius: 0.5rem; padding: 0.75rem 1rem; margin-bottom: 1rem;">
                                <strong style="color: #dc2626;">Blocked:</strong>
                                <span style="color: #991b1b;">${escapeHtml(getReasonLabel(spam.reason))}</span>
                                <code style="background: #fef2f2; padding: 0.125rem 0.375rem; border-radius: 0.25rem; font-size: 0.75rem; margin-left: 0.5rem;">${escapeHtml(spam.reason)}</code>
                                ${spam.detected_issues && spam.detected_issues.length > 0 ? `
                                <div style="margin-top: 0.5rem; padding-top: 0.5rem; border-top: 1px solid #fecaca;">
                                    <strong style="font-size: 0.75rem; color: #dc2626;">Detected Issues:</strong>
                                    <div style="margin-top: 0.25rem;">
                                        ${spam.detected_issues.map(issue => `<code style="background: #fef2f2; padding: 0.125rem 0.375rem; border-radius: 0.25rem; font-size: 0.7rem; margin-right: 0.5rem; display: inline-block; margin-bottom: 0.25rem;">${escapeHtml(issue)}</code>`).join('')}
                                    </div>
                                </div>
                                ` : ''}
                            </div>

                            <!-- Original Submission Data -->
                            ${Object.keys(originalData).length > 0 ? `
                            <div style="margin-bottom: 1rem;">
                                <div style="font-weight: 600; margin-bottom: 0.5rem; color: #374151;">Original Submission</div>
                                <div class="details-grid">
                                    ${originalData.name ? `<div class="detail-item"><div class="detail-label">Name</div><div class="detail-value">${escapeHtml(originalData.name)}</div></div>` : ''}
                                    ${originalData.firstName ? `<div class="detail-item"><div class="detail-label">First Name</div><div class="detail-value">${escapeHtml(originalData.firstName)}</div></div>` : ''}
                                    ${originalData.lastName ? `<div class="detail-item"><div class="detail-label">Last Name</div><div class="detail-value">${escapeHtml(originalData.lastName)}</div></div>` : ''}
                                    <div class="detail-item"><div class="detail-label">Email</div><div class="detail-value">${escapeHtml(spam.email || originalData.email || '')}</div></div>
                                    ${originalData.phone ? `<div class="detail-item"><div class="detail-label">Phone</div><div class="detail-value">${escapeHtml(originalData.phone)}</div></div>` : ''}
                                    <div class="detail-item"><div class="detail-label">IP Address</div><div class="detail-value">${escapeHtml(spam.ip || '')}</div></div>
                                    <div class="detail-item"><div class="detail-label">Date & Time</div><div class="detail-value">${escapeHtml(dateStr + ' ' + timeStr)}</div></div>
                                    ${originalData.formType ? `<div class="detail-item"><div class="detail-label">Form Type</div><div class="detail-value">${escapeHtml(originalData.formType)}</div></div>` : ''}
                                </div>
                                ${originalData.message ? `
                                <div class="message-section" style="margin-top: 0.75rem;">
                                    <div class="message-label">Message</div>
                                    <div class="message-content">${escapeHtml(originalData.message)}</div>
                                </div>
                                ` : ''}
                            </div>
                            ` : `
                            <div class="details-grid" style="margin-bottom: 1rem;">
                                <div class="detail-item"><div class="detail-label">Email</div><div class="detail-value">${escapeHtml(spam.email || '')}</div></div>
                                <div class="detail-item"><div class="detail-label">IP Address</div><div class="detail-value">${escapeHtml(spam.ip || '')}</div></div>
                                <div class="detail-item"><div class="detail-label">Date & Time</div><div class="detail-value">${escapeHtml(dateStr + ' ' + timeStr)}</div></div>
                            </div>
                            `}

                            <!-- Security Check Matrix -->
                            <div style="margin-bottom: 1rem;">
                                <div style="font-weight: 600; margin-bottom: 0.5rem; color: #374151;">Security Check Matrix</div>
                                <table style="width: 100%; border-collapse: collapse; font-size: 0.8125rem;">
                                    <thead>
                                        <tr style="background: #f3f4f6;">
                                            <th style="padding: 0.5rem; text-align: left; border: 1px solid #e5e7eb;">Check</th>
                                            <th style="padding: 0.5rem; text-align: left; border: 1px solid #e5e7eb;">Value Tested</th>
                                            <th style="padding: 0.5rem; text-align: center; border: 1px solid #e5e7eb; width: 80px;">Result</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        ${checkMatrix}
                                    </tbody>
                                </table>
                            </div>

                            <!-- User Agent -->
                            <div class="message-section">
                                <div class="message-label">User Agent</div>
                                <div class="message-content" style="font-size: 0.75rem;">${escapeHtml(spam.userAgent || '')}</div>
                            </div>
                        </div>
                    </div>
                `;
            }).join('');
        }

        /**
         * Build the security check matrix for a spam entry
         */
        function buildSecurityCheckMatrix(spam, originalData) {
            const reason = spam.reason || '';
            const email = spam.email || originalData.email || '';
            const name = originalData.name || ((originalData.firstName || '') + ' ' + (originalData.lastName || '')).trim();
            const phone = originalData.phone || '';
            const message = originalData.message || '';

            // For manual reviews, we'll analyze the data to show what WOULD have been caught
            const isManualReview = reason.startsWith('manual_review');

            // Check for pre-computed detected_issues from re-analysis
            const detectedIssues = spam.detected_issues || [];
            const hasDetectedIssue = (pattern) => detectedIssues.some(issue => issue.startsWith(pattern));

            // Helper functions for client-side analysis
            function analyzeGibberish(str) {
                if (!str || str.length < 6) return { isGibberish: false };
                const vowels = (str.match(/[aeiouAEIOU]/g) || []).length;
                const consonants = (str.match(/[bcdfghjklmnpqrstvwxyzBCDFGHJKLMNPQRSTVWXYZ]/g) || []).length;
                const total = vowels + consonants;
                if (total === 0) return { isGibberish: false };
                const vowelRatio = vowels / total;
                const midCaps = (str.match(/(?!^|\s)[A-Z]/g) || []).length;
                const hasConsonantCluster = /[bcdfghjklmnpqrstvwxyz]{5,}/i.test(str);
                return {
                    isGibberish: vowelRatio < 0.15 || midCaps > 2 || hasConsonantCluster,
                    vowelRatio: Math.round(vowelRatio * 100),
                    midCaps: midCaps
                };
            }

            function analyzePhone(ph) {
                const digits = ph.replace(/\D/g, '');
                if (digits.length < 10) return { valid: true };
                const areaCode = digits.length === 11 && digits[0] === '1' ? digits.substring(1, 4) : digits.substring(0, 3);
                const validCodes = ['306', '639', '403', '587', '780', '825', '204', '431', '236', '250', '604', '672', '778', '416', '647', '905', '437', '514', '438'];
                return { valid: validCodes.includes(areaCode), areaCode: areaCode };
            }

            function analyzeMessage(msg) {
                const commonWords = ['the', 'be', 'to', 'of', 'and', 'a', 'in', 'that', 'have', 'i', 'it', 'for', 'not', 'on', 'with', 'you', 'do', 'at', 'this', 'container', 'shipping', 'storage', 'delivery', 'quote', 'price', 'buy', 'need', 'looking', 'please', 'thanks', 'hello', 'hi', 'help', 'new', 'used', 'yes', 'no'];
                const words = msg.toLowerCase().split(/[\s\p{P}]+/u).filter(w => w.length >= 2);
                let count = 0;
                words.forEach(w => { if (commonWords.includes(w)) count++; });
                return { realWords: count, hasRealWords: count > 0 };
            }

            // Run analysis
            const nameAnalysis = analyzeGibberish(name);
            const phoneAnalysis = phone ? analyzePhone(phone) : { valid: true };
            const messageAnalysis = message ? analyzeMessage(message) : { realWords: 0, hasRealWords: true };
            const gmailDots = email.includes('@gmail.com') ? (email.split('@')[0].match(/\./g) || []).length : 0;

            // Define all checks - use client-side analysis for manual reviews
            const checks = [
                {
                    name: 'Honeypot Field',
                    category: 'basic',
                    value: '(hidden field)',
                    triggered: reason === 'honeypot',
                    description: 'Hidden field filled by bots'
                },
                {
                    name: 'Time Check',
                    category: 'basic',
                    value: '< 3 seconds',
                    triggered: reason === 'time_check',
                    description: 'Form submitted too quickly'
                },
                {
                    name: 'Rate Limit',
                    category: 'basic',
                    value: spam.ip || '',
                    triggered: reason.startsWith('rate_limit'),
                    description: 'Too many submissions from IP'
                },
                {
                    name: 'Spam Phrases',
                    category: 'content',
                    value: message ? message.substring(0, 50) + (message.length > 50 ? '...' : '') : '(no message)',
                    triggered: reason.startsWith('content_filter:spam_phrase'),
                    description: reason.startsWith('content_filter:spam_phrase') ? reason.split(':')[2] : ''
                },
                {
                    name: 'Excessive URLs',
                    category: 'content',
                    value: message ? (message.match(/https?:\/\/|www\./gi) || []).length + ' URLs' : '0 URLs',
                    triggered: reason === 'content_filter:excessive_urls',
                    description: 'Too many links in message'
                },
                {
                    name: 'Excessive Caps',
                    category: 'content',
                    value: message ? message.substring(0, 30) : '',
                    triggered: reason === 'content_filter:excessive_caps' || reason === 'excessive_caps',
                    description: 'Message is mostly uppercase'
                },
                {
                    name: 'Disposable Email',
                    category: 'content',
                    value: email,
                    triggered: reason.startsWith('content_filter:disposable_email') || reason.startsWith('disposable_email'),
                    description: 'Throwaway email domain'
                },
                {
                    name: 'Gmail Dot Pattern',
                    category: 'advanced',
                    value: email,
                    triggered: reason.includes('gmail_dot_pattern') || hasDetectedIssue('gmail_dot_pattern') || (isManualReview && gmailDots >= 3),
                    description: gmailDots + ' dots in username' + (gmailDots >= 3 ? ' (suspicious)' : '')
                },
                {
                    name: 'Name Validation',
                    category: 'advanced',
                    value: name || '(no name)',
                    triggered: reason.startsWith('invalid_name') || hasDetectedIssue('invalid_name') || (isManualReview && nameAnalysis.isGibberish),
                    description: nameAnalysis.isGibberish ?
                        `${nameAnalysis.vowelRatio}% vowels, ${nameAnalysis.midCaps} mid-caps (suspicious)` :
                        getNameAnalysis(name)
                },
                {
                    name: 'Phone Area Code',
                    category: 'advanced',
                    value: phone || '(no phone)',
                    triggered: reason.startsWith('invalid_phone') || hasDetectedIssue('invalid_phone') || (isManualReview && phone && !phoneAnalysis.valid),
                    description: phone ? `Area code: ${phoneAnalysis.areaCode}` + (!phoneAnalysis.valid ? ' (invalid)' : '') : 'N/A'
                },
                {
                    name: 'Message Words',
                    category: 'advanced',
                    value: message ? message.substring(0, 40) + (message.length > 40 ? '...' : '') : '(no message)',
                    triggered: reason.startsWith('gibberish_message') || hasDetectedIssue('gibberish_message') || (isManualReview && message && !messageAnalysis.hasRealWords),
                    description: message ? `${messageAnalysis.realWords} real words found` + (!messageAnalysis.hasRealWords ? ' (suspicious)' : '') : 'N/A'
                },
                {
                    name: 'Gibberish Detection',
                    category: 'advanced',
                    value: name || message?.substring(0, 30) || '',
                    triggered: reason.startsWith('gibberish_') || hasDetectedIssue('gibberish_') || hasDetectedIssue('invalid_name') || (isManualReview && (nameAnalysis.isGibberish || (message && !messageAnalysis.hasRealWords))),
                    description: 'High entropy / random characters'
                }
            ];

            // Build table rows
            let html = '';
            let currentCategory = '';

            checks.forEach(check => {
                // Add category header
                if (check.category !== currentCategory) {
                    currentCategory = check.category;
                    const categoryLabel = currentCategory === 'basic' ? 'Basic Protection' :
                                         currentCategory === 'content' ? 'Content Filtering' : 'Advanced Bot Detection';
                    html += `<tr style="background: #f9fafb;"><td colspan="3" style="padding: 0.375rem 0.5rem; font-weight: 600; font-size: 0.75rem; color: #6b7280; border: 1px solid #e5e7eb;">${categoryLabel}</td></tr>`;
                }

                const bgColor = check.triggered ? '#fef2f2' : '#ffffff';
                const resultIcon = check.triggered ? '❌ DETECTED' : '—';
                const resultColor = check.triggered ? '#dc2626' : '#9ca3af';

                html += `
                    <tr style="background: ${bgColor};">
                        <td style="padding: 0.375rem 0.5rem; border: 1px solid #e5e7eb;">
                            <strong>${escapeHtml(check.name)}</strong>
                            ${check.triggered && check.description ? `<br><span style="font-size: 0.7rem; color: #dc2626;">${escapeHtml(check.description)}</span>` : ''}
                        </td>
                        <td style="padding: 0.375rem 0.5rem; border: 1px solid #e5e7eb; font-family: monospace; font-size: 0.75rem; word-break: break-all;">
                            ${escapeHtml(check.value)}
                        </td>
                        <td style="padding: 0.375rem 0.5rem; border: 1px solid #e5e7eb; text-align: center; color: ${resultColor}; font-weight: ${check.triggered ? '600' : '400'}; font-size: 0.75rem;">
                            ${resultIcon}
                        </td>
                    </tr>
                `;
            });

            return html;
        }

        /**
         * Analyze name for potential issues
         */
        function getNameAnalysis(name) {
            if (!name || name.trim() === '') return 'N/A';

            const vowels = (name.match(/[aeiouAEIOU]/g) || []).length;
            const consonants = (name.match(/[bcdfghjklmnpqrstvwxyzBCDFGHJKLMNPQRSTVWXYZ]/g) || []).length;
            const total = vowels + consonants;

            if (total === 0) return 'No letters';

            const vowelRatio = Math.round((vowels / total) * 100);
            const midCaps = (name.match(/(?!^|\s)[A-Z]/g) || []).length;

            let analysis = `${vowelRatio}% vowels`;
            if (midCaps > 2) analysis += `, ${midCaps} mid-word caps`;

            return analysis;
        }

        /**
         * Count real English words in message
         */
        function countRealWords(message) {
            const commonWords = ['the', 'be', 'to', 'of', 'and', 'a', 'in', 'that', 'have', 'i', 'it', 'for', 'not', 'on', 'with', 'he', 'as', 'you', 'do', 'at', 'this', 'but', 'his', 'by', 'from', 'they', 'we', 'say', 'her', 'she', 'or', 'an', 'will', 'my', 'one', 'all', 'would', 'there', 'their', 'what', 'so', 'up', 'out', 'if', 'about', 'who', 'get', 'which', 'go', 'me', 'container', 'shipping', 'storage', 'delivery', 'quote', 'price', 'buy', 'need', 'looking', 'interested', 'please', 'thanks', 'hello', 'hi', 'call', 'help', 'information', 'new', 'used', 'deliver', 'available', 'cost', 'much', 'yes', 'no', 'ok'];

            const words = message.toLowerCase().split(/[\s\p{P}]+/u).filter(w => w.length >= 2);
            let count = 0;
            words.forEach(word => {
                if (commonWords.includes(word)) count++;
            });
            return count;
        }

        function toggleSpamRow(index) {
            const row = document.getElementById('spam-row-' + index);
            if (row) row.classList.toggle('expanded');
        }

        // Spam event listeners
        if (spamReasonFilter) {
            spamReasonFilter.addEventListener('change', filterSpam);
        }
        if (spamSearchInput) {
            spamSearchInput.addEventListener('input', filterSpam);
        }

        // Initial spam render (if on spam tab)
        if (document.getElementById('spam-tab')?.classList.contains('active')) {
            filterSpam();
        }

        // Render spam when tab is clicked
        document.querySelectorAll('.tab').forEach(tab => {
            tab.addEventListener('click', () => {
                setTimeout(() => {
                    if (document.getElementById('spam-tab')?.classList.contains('active')) {
                        filterSpam();
                    }
                    if (document.getElementById('backup-tab')?.classList.contains('active')) {
                        loadBackupData();
                    }
                }, 0);
            });
        });

        // ============================================
        // Add/Edit Review Functionality
        // ============================================
        const addReviewModal = document.getElementById('add-review-modal');
        const addReviewForm = document.getElementById('add-review-form');
        let selectedRating = 0;
        let isEditMode = false;

        // Star rating input handling
        document.querySelectorAll('.star-input').forEach(star => {
            star.addEventListener('click', () => {
                selectedRating = parseInt(star.dataset.rating);
                document.getElementById('rating-value').value = selectedRating;
                updateStarDisplay(selectedRating);
            });

            star.addEventListener('mouseenter', () => {
                updateStarDisplay(parseInt(star.dataset.rating));
            });
        });

        document.getElementById('star-rating-input')?.addEventListener('mouseleave', () => {
            updateStarDisplay(selectedRating);
        });

        function updateStarDisplay(rating) {
            document.querySelectorAll('.star-input').forEach(star => {
                const starRating = parseInt(star.dataset.rating);
                star.style.color = starRating <= rating ? '#fbbf24' : '#d1d5db';
            });
        }

        function openAddReviewModal() {
            isEditMode = false;
            // Reset form
            addReviewForm.reset();
            document.getElementById('edit-review-id').value = '';
            document.getElementById('review-date').value = '<?= date('Y-m-d') ?>';
            selectedRating = 0;
            document.getElementById('rating-value').value = '';
            updateStarDisplay(0);

            // Update modal for add mode
            document.getElementById('review-modal-title').textContent = 'Add New Review';
            document.getElementById('review-submit-btn').textContent = 'Add Review';

            addReviewModal.style.display = 'flex';
        }

        function editReview(reviewId) {
            isEditMode = true;

            // Find the review data
            const review = allReviews.find(r => r.id === reviewId);
            if (!review) {
                alert('Review not found');
                return;
            }

            // Populate form
            document.getElementById('edit-review-id').value = review.id;
            document.getElementById('review-author').value = review.author;
            document.getElementById('review-date').value = review.date;
            document.getElementById('review-text').value = review.text || '';
            document.getElementById('review-owner-response').value = review.ownerResponse?.text || '';

            // Set rating
            selectedRating = review.rating;
            document.getElementById('rating-value').value = selectedRating;
            updateStarDisplay(selectedRating);

            // Update modal for edit mode
            document.getElementById('review-modal-title').textContent = 'Edit Review';
            document.getElementById('review-submit-btn').textContent = 'Save Changes';

            addReviewModal.style.display = 'flex';
        }

        function closeAddReviewModal() {
            addReviewModal.style.display = 'none';
            isEditMode = false;
        }

        async function submitReviewForm(event) {
            event.preventDefault();

            const submitBtn = document.getElementById('review-submit-btn');
            const originalText = submitBtn.textContent;
            submitBtn.disabled = true;
            submitBtn.textContent = isEditMode ? 'Saving...' : 'Adding...';

            const formData = new FormData(addReviewForm);
            formData.append('key', secretPath);
            formData.append('action', isEditMode ? 'edit_review' : 'add_review');

            try {
                const response = await fetch('admin.php', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                if (!data.success) {
                    throw new Error(data.message || 'Failed to save review');
                }

                // Success - close modal and refresh
                closeAddReviewModal();
                alert(isEditMode
                    ? 'Review updated successfully! Rebuild the site to see changes on the frontend.'
                    : 'Review added successfully! Rebuild the site to see it on the frontend.');

                // Reload page to show updated reviews
                window.location.reload();

            } catch (error) {
                console.error('Save review failed:', error);
                alert('Failed to save review: ' + error.message);
            } finally {
                submitBtn.disabled = false;
                submitBtn.textContent = originalText;
            }
        }

        async function deleteReview(reviewId, authorName) {
            if (!confirm(`Are you sure you want to delete the review by "${authorName}"?\n\nThis action cannot be undone.`)) {
                return;
            }

            try {
                const formData = new FormData();
                formData.append('key', secretPath);
                formData.append('action', 'delete_review');
                formData.append('review_id', reviewId);

                const response = await fetch('admin.php', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                if (!data.success) {
                    throw new Error(data.message || 'Failed to delete review');
                }

                // Success - reload page to show updated reviews
                alert('Review deleted successfully! Rebuild the site to update the frontend.');
                window.location.reload();

            } catch (error) {
                console.error('Delete review failed:', error);
                alert('Failed to delete review: ' + error.message);
            }
        }

        // Close add review modal on escape or click outside
        addReviewModal?.addEventListener('click', (e) => {
            if (e.target === addReviewModal) closeAddReviewModal();
        });

        // ============================================
        // Backup Tab Functionality
        // ============================================
        const backupConfirmModal = document.getElementById('backup-confirm-modal');
        const backupProgressModal = document.getElementById('backup-progress-modal');
        let pendingBackupData = null;

        function formatFileSize(bytes) {
            if (bytes >= 1048576) {
                return (bytes / 1048576).toFixed(2) + ' MB';
            } else if (bytes >= 1024) {
                return (bytes / 1024).toFixed(2) + ' KB';
            }
            return bytes + ' bytes';
        }

        async function loadBackupData() {
            const statsLoading = document.getElementById('backup-stats-loading');
            const statsContainer = document.getElementById('backup-stats');
            const historyLoading = document.getElementById('backup-history-loading');
            const historyEmpty = document.getElementById('backup-history-empty');
            const historyContainer = document.getElementById('backup-history');
            const warningBox = document.getElementById('backup-size-warning');
            const warningText = document.getElementById('backup-warning-text');

            // Show loading states
            statsLoading.style.display = 'block';
            statsContainer.style.display = 'none';
            historyLoading.style.display = 'block';
            historyEmpty.style.display = 'none';
            historyContainer.style.display = 'none';

            try {
                const response = await fetch(`backup.php?key=${secretPath}&action=list`);
                const data = await response.json();

                if (!data.success) {
                    throw new Error(data.message || 'Failed to load backup data');
                }

                // Update stats
                document.getElementById('backup-total-size').textContent = data.totalSizeFormatted;
                document.getElementById('backup-file-count').textContent = data.stats.filter(s => s.exists).length;
                document.getElementById('backup-count').textContent = data.backups.length;

                // Render stats table
                const statsTable = document.getElementById('backup-stats-table');
                statsTable.innerHTML = data.stats.map(stat => `
                    <tr style="border-bottom: 1px solid #e5e7eb;">
                        <td style="padding: 0.5rem 0.75rem; font-size: 0.875rem;">
                            <code style="background: #f3f4f6; padding: 0.125rem 0.375rem; border-radius: 0.25rem;">${escapeHtml(stat.file)}</code>
                        </td>
                        <td style="padding: 0.5rem 0.75rem; text-align: right; font-size: 0.875rem;">
                            ${stat.exists ? formatFileSize(stat.size) : '-'}
                        </td>
                        <td style="padding: 0.5rem 0.75rem; text-align: right; font-size: 0.875rem;">
                            ${stat.exists && stat.count ? `${stat.count} ${stat.type}` : '-'}
                        </td>
                        <td style="padding: 0.5rem 0.75rem; text-align: center;">
                            ${stat.exists
                                ? '<span style="color: #059669; font-size: 0.75rem;">&#10004; Ready</span>'
                                : '<span style="color: #9ca3af; font-size: 0.75rem;">Not found</span>'
                            }
                        </td>
                    </tr>
                `).join('');

                // Show warning if needed
                if (data.warning) {
                    warningText.textContent = data.warning;
                    warningBox.style.display = 'block';
                } else {
                    warningBox.style.display = 'none';
                }

                // Render backup history
                if (data.backups.length === 0) {
                    historyEmpty.style.display = 'block';
                    historyContainer.style.display = 'none';
                } else {
                    const historyTable = document.getElementById('backup-history-table');
                    historyTable.innerHTML = data.backups.map((backup, index) => `
                        <tr style="border-bottom: 1px solid #e5e7eb;">
                            <td style="padding: 0.5rem 0.75rem; font-size: 0.875rem;">
                                <code style="background: #f3f4f6; padding: 0.125rem 0.375rem; border-radius: 0.25rem;">${escapeHtml(backup.filename)}</code>
                                ${index === 0 ? '<span style="background: #d1fae5; color: #065f46; font-size: 0.625rem; padding: 0.125rem 0.375rem; border-radius: 0.25rem; margin-left: 0.5rem;">Latest</span>' : ''}
                            </td>
                            <td style="padding: 0.5rem 0.75rem; text-align: right; font-size: 0.875rem;">
                                ${escapeHtml(backup.sizeFormatted)}
                            </td>
                            <td style="padding: 0.5rem 0.75rem; font-size: 0.875rem;">
                                ${escapeHtml(backup.createdFormatted)}
                            </td>
                            <td style="padding: 0.5rem 0.75rem; text-align: center;">
                                <button type="button" class="btn btn-secondary" style="font-size: 0.75rem; padding: 0.25rem 0.5rem;" onclick="deleteBackup('${escapeHtml(backup.filename)}')">
                                    Delete
                                </button>
                            </td>
                        </tr>
                    `).join('');
                    historyEmpty.style.display = 'none';
                    historyContainer.style.display = 'block';
                }

                // Show stats
                statsLoading.style.display = 'none';
                statsContainer.style.display = 'block';
                historyLoading.style.display = 'none';

            } catch (error) {
                console.error('Failed to load backup data:', error);
                statsLoading.innerHTML = `<p style="color: #dc2626;">Error: ${escapeHtml(error.message)}</p>`;
                historyLoading.style.display = 'none';
            }
        }

        async function createBackup(confirmed = false) {
            const btn = document.getElementById('create-backup-btn');
            btn.disabled = true;
            btn.textContent = 'Creating...';

            try {
                const formData = new FormData();
                formData.append('key', secretPath);
                formData.append('action', 'create');
                if (confirmed) {
                    formData.append('confirmed', 'true');
                }

                const response = await fetch('backup.php', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                // Check if confirmation needed
                if (!data.success && data.needsConfirmation) {
                    // Show confirmation modal
                    document.getElementById('backup-confirm-message').textContent = data.message;
                    pendingBackupData = data.oldestBackup;
                    backupConfirmModal.style.display = 'flex';
                    btn.disabled = false;
                    btn.textContent = 'Create Backup';
                    return;
                }

                if (!data.success) {
                    throw new Error(data.message || 'Backup creation failed');
                }

                // Show progress modal with success
                const progressBody = document.getElementById('backup-progress-body');
                const progressText = document.getElementById('backup-progress-text');

                backupProgressModal.style.display = 'flex';

                let resultHtml = `
                    <div style="text-align: center;">
                        <div style="font-size: 3rem; color: #059669; margin-bottom: 1rem;">&#10004;</div>
                        <p style="font-weight: 600; color: #065f46; margin-bottom: 0.5rem;">Backup Created Successfully!</p>
                        <p style="font-size: 0.875rem; color: #6b7280; margin-bottom: 1rem;">
                            ${escapeHtml(data.backup.filename)} (${escapeHtml(data.backup.sizeFormatted)})
                        </p>
                `;

                if (data.email.success) {
                    resultHtml += `<p style="font-size: 0.875rem; color: #059669;">Email sent successfully!</p>`;
                } else {
                    resultHtml += `<p style="font-size: 0.875rem; color: #dc2626;">Email failed: ${escapeHtml(data.email.message)}</p>`;
                }

                if (data.deleted && data.deleted.length > 0) {
                    resultHtml += `<p style="font-size: 0.75rem; color: #6b7280; margin-top: 0.5rem;">Deleted old backup: ${escapeHtml(data.deleted.join(', '))}</p>`;
                }

                if (data.warning) {
                    resultHtml += `<p style="font-size: 0.75rem; color: #d97706; margin-top: 0.5rem;">${escapeHtml(data.warning)}</p>`;
                }

                resultHtml += `
                        <button type="button" class="btn btn-primary" style="margin-top: 1rem;" onclick="closeProgressModal()">Done</button>
                    </div>
                `;

                progressBody.innerHTML = resultHtml;

                // Reload backup data
                loadBackupData();

            } catch (error) {
                console.error('Backup creation failed:', error);
                alert('Backup failed: ' + error.message);
            } finally {
                btn.disabled = false;
                btn.textContent = 'Create Backup';
            }
        }

        function confirmBackup() {
            closeBackupModal();
            createBackup(true);
        }

        function closeBackupModal() {
            backupConfirmModal.style.display = 'none';
            pendingBackupData = null;
        }

        function closeProgressModal() {
            backupProgressModal.style.display = 'none';
            // Reset progress modal content
            document.getElementById('backup-progress-body').innerHTML = `
                <div class="spinner"></div>
                <p style="text-align: center; margin-top: 1rem;" id="backup-progress-text">Creating backup and sending email...</p>
            `;
        }

        async function deleteBackup(filename) {
            if (!confirm(`Are you sure you want to delete ${filename}?`)) {
                return;
            }

            try {
                const formData = new FormData();
                formData.append('key', secretPath);
                formData.append('action', 'delete');
                formData.append('filename', filename);

                const response = await fetch('backup.php', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                if (!data.success) {
                    throw new Error(data.message || 'Failed to delete backup');
                }

                // Reload backup data
                loadBackupData();

            } catch (error) {
                console.error('Delete failed:', error);
                alert('Delete failed: ' + error.message);
            }
        }

        // Close modals on escape key or click outside
        backupConfirmModal.addEventListener('click', (e) => {
            if (e.target === backupConfirmModal) closeBackupModal();
        });
        backupProgressModal.addEventListener('click', (e) => {
            if (e.target === backupProgressModal) closeProgressModal();
        });
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                if (addReviewModal?.style.display !== 'none') closeAddReviewModal();
                if (backupConfirmModal.style.display !== 'none') closeBackupModal();
                if (backupProgressModal.style.display !== 'none') closeProgressModal();
            }
        });

        // Load backup data if on backup tab
        if (document.getElementById('backup-tab')?.classList.contains('active')) {
            loadBackupData();
        }
    </script>
</body>
</html>
