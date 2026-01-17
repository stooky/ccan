<?php
/**
 * C-Can Sam Contact Form Handler
 *
 * Handles form submissions:
 * - Validates input
 * - Logs to JSON file
 * - Sends email notification
 * - Returns JSON response
 */

// Set headers for JSON response
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

// Load configuration
$configPath = dirname(__DIR__) . '/config.yaml';
$localConfigPath = dirname(__DIR__) . '/config.local.yaml';

if (!file_exists($configPath)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Configuration not found']);
    exit();
}

$config = yaml_parse_file($configPath);
if ($config === false) {
    // Fallback: parse YAML manually if yaml extension not available
    $config = parseSimpleYaml($configPath);
}

// Merge local overrides if they exist
if (file_exists($localConfigPath)) {
    $localConfig = yaml_parse_file($localConfigPath);
    if ($localConfig === false) {
        $localConfig = parseSimpleYaml($localConfigPath);
    }
    if ($localConfig) {
        $config = array_replace_recursive($config, $localConfig);
    }
}

/**
 * Simple YAML parser for our config format
 */
function parseSimpleYaml($path) {
    $content = file_get_contents($path);
    $config = [
        'contact_form' => [
            'recipient_email' => 'ccansam22@gmail.com',
            'subject_prefix' => '[C-Can Sam Contact]'
        ],
        'logging' => [
            'submissions_file' => 'data/submissions.json'
        ],
        'security' => [
            'honeypot_field' => 'website_url',
            'rate_limit' => 10,
            'rate_limit_window' => 3600,
            'min_submit_time' => 3,
            'max_urls' => 2,
            'spam_phrases' => [],
            'disposable_domains' => [],
            // Advanced bot detection
            'gibberish_detection' => true,
            'name_validation' => true,
            'phone_area_code_validation' => true,
            'valid_area_codes' => ['306', '639', '403', '587', '780', '204', '431', '250', '604', '778'],
            'message_word_validation' => true,
            'min_real_words' => 1,
            'gmail_dot_limit' => 3
        ]
    ];

    // Extract recipient email
    if (preg_match('/recipient_email:\s*["\']?([^"\'\n]+)["\']?/', $content, $matches)) {
        $config['contact_form']['recipient_email'] = trim($matches[1]);
    }

    // Extract subject prefix
    if (preg_match('/subject_prefix:\s*["\']?([^"\'\n]+)["\']?/', $content, $matches)) {
        $config['contact_form']['subject_prefix'] = trim($matches[1]);
    }

    // Extract submissions file
    if (preg_match('/submissions_file:\s*["\']?([^"\'\n]+)["\']?/', $content, $matches)) {
        $config['logging']['submissions_file'] = trim($matches[1]);
    }

    // Extract security settings
    if (preg_match('/honeypot_field:\s*["\']?([^"\'\n]+)["\']?/', $content, $matches)) {
        $config['security']['honeypot_field'] = trim($matches[1]);
    }
    if (preg_match('/min_submit_time:\s*(\d+)/', $content, $matches)) {
        $config['security']['min_submit_time'] = intval($matches[1]);
    }
    if (preg_match('/rate_limit:\s*(\d+)/', $content, $matches)) {
        $config['security']['rate_limit'] = intval($matches[1]);
    }
    if (preg_match('/rate_limit_window:\s*(\d+)/', $content, $matches)) {
        $config['security']['rate_limit_window'] = intval($matches[1]);
    }
    if (preg_match('/max_urls:\s*(\d+)/', $content, $matches)) {
        $config['security']['max_urls'] = intval($matches[1]);
    }

    // Extract spam_phrases array
    if (preg_match('/spam_phrases:\s*\n((?:\s+-\s*["\']?[^"\'\n]+["\']?\n)+)/m', $content, $matches)) {
        preg_match_all('/-\s*["\']?([^"\'\n]+)["\']?/', $matches[1], $phrases);
        $config['security']['spam_phrases'] = array_map('trim', $phrases[1]);
    }

    // Extract disposable_domains array
    if (preg_match('/disposable_domains:\s*\n((?:\s+-\s*["\']?[^"\'\n]+["\']?\n)+)/m', $content, $matches)) {
        preg_match_all('/-\s*["\']?([^"\'\n]+)["\']?/', $matches[1], $domains);
        $config['security']['disposable_domains'] = array_map('trim', $domains[1]);
    }

    // Extract advanced bot detection settings
    if (preg_match('/gibberish_detection:\s*(true|false)/i', $content, $matches)) {
        $config['security']['gibberish_detection'] = strtolower($matches[1]) === 'true';
    }
    if (preg_match('/name_validation:\s*(true|false)/i', $content, $matches)) {
        $config['security']['name_validation'] = strtolower($matches[1]) === 'true';
    }
    if (preg_match('/phone_area_code_validation:\s*(true|false)/i', $content, $matches)) {
        $config['security']['phone_area_code_validation'] = strtolower($matches[1]) === 'true';
    }
    if (preg_match('/message_word_validation:\s*(true|false)/i', $content, $matches)) {
        $config['security']['message_word_validation'] = strtolower($matches[1]) === 'true';
    }
    if (preg_match('/min_real_words:\s*(\d+)/', $content, $matches)) {
        $config['security']['min_real_words'] = intval($matches[1]);
    }
    if (preg_match('/gmail_dot_limit:\s*(\d+)/', $content, $matches)) {
        $config['security']['gmail_dot_limit'] = intval($matches[1]);
    }

    // Extract valid_area_codes array
    if (preg_match('/valid_area_codes:\s*\n((?:\s+-\s*["\']?[^"\'\n]+["\']?\n)+)/m', $content, $matches)) {
        preg_match_all('/-\s*["\']?([^"\'\n]+)["\']?/', $matches[1], $codes);
        $config['security']['valid_area_codes'] = array_map('trim', $codes[1]);
    }

    return $config;
}

// Get form data
$data = [];
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';

if (strpos($contentType, 'application/json') !== false) {
    $data = json_decode(file_get_contents('php://input'), true) ?? [];
} else {
    $data = $_POST;
}

// Honeypot check (spam prevention)
$honeypotField = $config['security']['honeypot_field'] ?? 'website_url';
if (!empty($data[$honeypotField])) {
    // Bot detected - pretend success but do nothing
    logSpamAttempt('honeypot', $data, $config);
    echo json_encode(['success' => true, 'message' => 'Thank you for your message!']);
    exit();
}

// Time-based validation (reject if form submitted too quickly)
$minSubmitTime = $config['security']['min_submit_time'] ?? 3; // seconds
if (!empty($data['_formLoadTime'])) {
    $loadTime = intval($data['_formLoadTime']);
    $submitTime = round(microtime(true) * 1000); // current time in ms
    $elapsedSeconds = ($submitTime - $loadTime) / 1000;

    if ($elapsedSeconds < $minSubmitTime) {
        // Form submitted too quickly - likely a bot
        logSpamAttempt('time_check', $data, $config);
        echo json_encode(['success' => true, 'message' => 'Thank you for your message!']);
        exit();
    }
}
// Remove internal field from data
unset($data['_formLoadTime']);

// Rate limiting check
$rateLimit = $config['security']['rate_limit'] ?? 10; // max per hour
$rateLimitWindow = $config['security']['rate_limit_window'] ?? 3600; // 1 hour in seconds
$clientIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

if (!checkRateLimit($clientIp, $rateLimit, $rateLimitWindow, $config)) {
    http_response_code(429);
    echo json_encode(['success' => false, 'message' => 'Too many submissions. Please try again later.']);
    exit();
}

// Content filtering
$contentFilterResult = checkContentFilters($data, $config);
if ($contentFilterResult !== true) {
    logSpamAttempt('content_filter:' . $contentFilterResult, $data, $config);
    // Pretend success to not give spammers feedback
    echo json_encode(['success' => true, 'message' => 'Thank you for your message!']);
    exit();
}

// Validate required fields based on form type
$formType = $data['formType'] ?? 'message';
$errors = [];

if ($formType === 'quote') {
    // Quote form requires: name, email, phone
    $required = ['name', 'email', 'phone'];
    foreach ($required as $field) {
        if (empty($data[$field])) {
            $errors[] = ucfirst($field) . ' is required';
        }
    }
} else {
    // Message form requires: firstName, lastName, email, message
    $required = ['firstName', 'lastName', 'email', 'message'];
    foreach ($required as $field) {
        if (empty($data[$field])) {
            $errors[] = ucfirst($field) . ' is required';
        }
    }
}

// Validate email format
if (!empty($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Please enter a valid email address';
}

if (!empty($errors)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => implode(', ', $errors), 'errors' => $errors]);
    exit();
}

// Sanitize input based on form type
$submission = [
    'id' => uniqid('sub_'),
    'timestamp' => date('c'),
    'date' => date('Y-m-d'),
    'time' => date('H:i:s'),
    'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
    'formType' => $formType,
    'email' => filter_var(trim($data['email']), FILTER_SANITIZE_EMAIL),
    'phone' => htmlspecialchars(trim($data['phone'] ?? ''), ENT_QUOTES, 'UTF-8'),
    'userAgent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
    'referer' => $_SERVER['HTTP_REFERER'] ?? 'direct'
];

if ($formType === 'quote') {
    // Quote form fields
    $submission['name'] = htmlspecialchars(trim($data['name']), ENT_QUOTES, 'UTF-8');
    $submission['containerSize'] = htmlspecialchars(trim($data['containerSize'] ?? ''), ENT_QUOTES, 'UTF-8');
    $submission['condition'] = htmlspecialchars(trim($data['condition'] ?? ''), ENT_QUOTES, 'UTF-8');
    $submission['intention'] = htmlspecialchars(trim($data['intention'] ?? ''), ENT_QUOTES, 'UTF-8');
    $submission['delivery'] = htmlspecialchars(trim($data['delivery'] ?? ''), ENT_QUOTES, 'UTF-8');
    $submission['locationType'] = htmlspecialchars(trim($data['locationType'] ?? ''), ENT_QUOTES, 'UTF-8');
    // Urban address fields
    $submission['streetAddress'] = htmlspecialchars(trim($data['streetAddress'] ?? ''), ENT_QUOTES, 'UTF-8');
    $submission['city'] = htmlspecialchars(trim($data['city'] ?? ''), ENT_QUOTES, 'UTF-8');
    $submission['postalCode'] = htmlspecialchars(trim($data['postalCode'] ?? ''), ENT_QUOTES, 'UTF-8');
    // Rural address fields
    $submission['landLocation'] = htmlspecialchars(trim($data['landLocation'] ?? ''), ENT_QUOTES, 'UTF-8');
    $submission['additionalDirections'] = htmlspecialchars(trim($data['additionalDirections'] ?? ''), ENT_QUOTES, 'UTF-8');
    $submission['message'] = htmlspecialchars(trim($data['message'] ?? ''), ENT_QUOTES, 'UTF-8');
    $submission['subject'] = 'Quote Request';
} else {
    // Message form fields
    $submission['firstName'] = htmlspecialchars(trim($data['firstName']), ENT_QUOTES, 'UTF-8');
    $submission['lastName'] = htmlspecialchars(trim($data['lastName']), ENT_QUOTES, 'UTF-8');
    $submission['subject'] = htmlspecialchars(trim($data['subject'] ?? 'General Inquiry'), ENT_QUOTES, 'UTF-8');
    $submission['message'] = htmlspecialchars(trim($data['message']), ENT_QUOTES, 'UTF-8');
}

// Log submission to file
$logFile = dirname(__DIR__) . '/' . ($config['logging']['submissions_file'] ?? 'data/submissions.json');
$logDir = dirname($logFile);

if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}

$submissions = [];
if (file_exists($logFile)) {
    $content = file_get_contents($logFile);
    $submissions = json_decode($content, true) ?? [];
}

// Add new submission at the beginning
array_unshift($submissions, $submission);

// Save log
if (file_put_contents($logFile, json_encode($submissions, JSON_PRETTY_PRINT)) === false) {
    error_log('Failed to write submission log: ' . $logFile);
}

// Send email notification
$recipientEmail = $config['contact_form']['recipient_email'] ?? 'ccansam22@gmail.com';
$subjectPrefix = $config['contact_form']['subject_prefix'] ?? '[Contact Form]';
$resendApiKey = $config['email']['resend_api_key'] ?? '';
$fromEmail = $config['email']['from_email'] ?? 'noreply@ccansam.com';

if ($formType === 'quote') {
    $emailSubject = $subjectPrefix . ' Quote Request from ' . $submission['name'];

    $emailBody = "New Quote Request:\n\n";
    $emailBody .= "Name: {$submission['name']}\n";
    $emailBody .= "Email: {$submission['email']}\n";
    $emailBody .= "Phone: {$submission['phone']}\n";
    $emailBody .= "Date: {$submission['date']} at {$submission['time']}\n";
    $emailBody .= "\n--- Container Details ---\n\n";
    $emailBody .= "Container Size: {$submission['containerSize']}\n";
    $emailBody .= "Condition: {$submission['condition']}\n";
    $emailBody .= "Intention: {$submission['intention']}\n";
    $emailBody .= "Delivery: {$submission['delivery']}\n";

    if (!empty($submission['locationType'])) {
        $emailBody .= "\n--- Delivery Location ---\n\n";
        $emailBody .= "Location Type: {$submission['locationType']}\n";
        if ($submission['locationType'] === 'Urban') {
            $emailBody .= "Street Address: {$submission['streetAddress']}\n";
            $emailBody .= "City: {$submission['city']}\n";
            $emailBody .= "Postal Code: {$submission['postalCode']}\n";
        } else {
            $emailBody .= "Land Location: {$submission['landLocation']}\n";
            if (!empty($submission['additionalDirections'])) {
                $emailBody .= "Additional Directions: {$submission['additionalDirections']}\n";
            }
        }
    }

    if (!empty($submission['message'])) {
        $emailBody .= "\n--- Additional Message ---\n\n";
        $emailBody .= $submission['message'];
    }

    $emailBody .= "\n\n--- End of Request ---\n";
} else {
    $emailSubject = $subjectPrefix . ' ' . $submission['subject'] . ' from ' . $submission['firstName'];

    $emailBody = "New contact form submission:\n\n";
    $emailBody .= "Name: {$submission['firstName']} {$submission['lastName']}\n";
    $emailBody .= "Email: {$submission['email']}\n";
    $emailBody .= "Phone: " . ($submission['phone'] ?: 'Not provided') . "\n";
    $emailBody .= "Subject: {$submission['subject']}\n";
    $emailBody .= "Date: {$submission['date']} at {$submission['time']}\n";
    $emailBody .= "\n--- Message ---\n\n";
    $emailBody .= $submission['message'];
    $emailBody .= "\n\n--- End of Message ---\n";
}

$emailBody .= "\nIP: {$submission['ip']}\n";
$emailBody .= "Submission ID: {$submission['id']}\n";

$emailSent = false;

// Try Resend API first (recommended)
if (!empty($resendApiKey)) {
    $emailSent = sendViaResend($resendApiKey, $fromEmail, $recipientEmail, $submission['email'], $emailSubject, $emailBody);
} else {
    // Fallback to PHP mail()
    $emailHeaders = [
        'From: ' . $fromEmail,
        'Reply-To: ' . $submission['email'],
        'X-Mailer: PHP/' . phpversion(),
        'Content-Type: text/plain; charset=UTF-8'
    ];
    $emailSent = @mail($recipientEmail, $emailSubject, $emailBody, implode("\r\n", $emailHeaders));
}

if (!$emailSent) {
    error_log('Failed to send contact form email to: ' . $recipientEmail);
}

/**
 * Send email via Resend API
 * https://resend.com - Free tier: 3,000 emails/month
 */
function sendViaResend($apiKey, $from, $to, $replyTo, $subject, $body) {
    $data = [
        'from' => $from,
        'to' => [$to],
        'reply_to' => $replyTo,
        'subject' => $subject,
        'text' => $body
    ];

    $ch = curl_init('https://api.resend.com/emails');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json'
        ],
        CURLOPT_TIMEOUT => 10
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        error_log('Resend cURL error: ' . $error);
        return false;
    }

    if ($httpCode !== 200) {
        error_log('Resend API error (HTTP ' . $httpCode . '): ' . $response);
        return false;
    }

    return true;
}

// Return success response
echo json_encode([
    'success' => true,
    'message' => 'Thank you for your message! We\'ll get back to you soon.',
    'id' => $submission['id']
]);

/**
 * Log spam attempt for analysis
 */
function logSpamAttempt($reason, $data, $config) {
    $logFile = dirname(__DIR__) . '/data/spam-log.json';
    $logDir = dirname($logFile);

    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }

    $entries = [];
    if (file_exists($logFile)) {
        $content = file_get_contents($logFile);
        $entries = json_decode($content, true) ?? [];
    }

    // Keep only last 1000 entries
    if (count($entries) > 1000) {
        $entries = array_slice($entries, -500);
    }

    $entries[] = [
        'timestamp' => date('c'),
        'reason' => $reason,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'email' => $data['email'] ?? '',
        'userAgent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 200),
    ];

    file_put_contents($logFile, json_encode($entries, JSON_PRETTY_PRINT));
}

/**
 * Check rate limit for IP address
 */
function checkRateLimit($ip, $maxRequests, $windowSeconds, $config) {
    $rateFile = dirname(__DIR__) . '/data/rate-limits.json';
    $rateDir = dirname($rateFile);

    if (!is_dir($rateDir)) {
        mkdir($rateDir, 0755, true);
    }

    $now = time();
    $rates = [];

    if (file_exists($rateFile)) {
        $content = file_get_contents($rateFile);
        $rates = json_decode($content, true) ?? [];
    }

    // Clean up expired entries
    foreach ($rates as $rateIp => $timestamps) {
        $rates[$rateIp] = array_filter($timestamps, function($ts) use ($now, $windowSeconds) {
            return ($now - $ts) < $windowSeconds;
        });
        if (empty($rates[$rateIp])) {
            unset($rates[$rateIp]);
        }
    }

    // Check current IP
    $ipRequests = $rates[$ip] ?? [];
    if (count($ipRequests) >= $maxRequests) {
        file_put_contents($rateFile, json_encode($rates, JSON_PRETTY_PRINT));
        return false;
    }

    // Add new request timestamp
    $rates[$ip][] = $now;
    file_put_contents($rateFile, json_encode($rates, JSON_PRETTY_PRINT));

    return true;
}

/**
 * Check content for spam indicators
 * Returns true if clean, or string describing the filter that triggered
 */
function checkContentFilters($data, $config) {
    $message = strtolower($data['message'] ?? '');
    $email = strtolower($data['email'] ?? '');
    $allText = $message . ' ' . ($data['name'] ?? '') . ' ' . ($data['firstName'] ?? '') . ' ' . ($data['lastName'] ?? '');
    $allText = strtolower($allText);

    // Check for excessive URLs (more than 2 links = spam)
    $urlCount = preg_match_all('/https?:\/\/|www\./i', $message);
    $maxUrls = $config['security']['max_urls'] ?? 2;
    if ($urlCount > $maxUrls) {
        return 'excessive_urls';
    }

    // Spam phrases to block
    $spamPhrases = $config['security']['spam_phrases'] ?? [
        'crypto', 'bitcoin', 'ethereum', 'nft',
        'seo services', 'seo expert', 'rank your website',
        'web traffic', 'buy followers', 'instagram followers',
        'casino', 'poker online', 'slot machine',
        'viagra', 'cialis', 'pharmacy',
        'make money fast', 'earn money online', 'work from home opportunity',
        'nigerian prince', 'lottery winner', 'you have won',
        'click here now', 'act now', 'limited time offer',
        'webcam', 'adult content', 'xxx',
    ];

    foreach ($spamPhrases as $phrase) {
        if (strpos($allText, strtolower($phrase)) !== false) {
            return 'spam_phrase:' . $phrase;
        }
    }

    // Disposable email domains to block
    $disposableDomains = $config['security']['disposable_domains'] ?? [
        'mailinator.com', 'guerrillamail.com', 'tempmail.com', 'throwaway.email',
        '10minutemail.com', 'trashmail.com', 'fakeinbox.com', 'temp-mail.org',
        'getnada.com', 'maildrop.cc', 'yopmail.com', 'sharklasers.com',
        'guerrillamail.info', 'grr.la', 'spam4.me', 'dispostable.com',
        'mailnesia.com', 'tempr.email', 'discard.email', 'tmpmail.org',
        'emailondeck.com', 'mohmal.com', 'tempail.com', 'burnermail.io',
    ];

    $emailDomain = '';
    if (strpos($email, '@') !== false) {
        $emailDomain = substr($email, strpos($email, '@') + 1);
    }

    foreach ($disposableDomains as $domain) {
        if ($emailDomain === $domain) {
            return 'disposable_email:' . $domain;
        }
    }

    // Check for all caps message (often spam)
    $upperCount = preg_match_all('/[A-Z]/', $data['message'] ?? '');
    $lowerCount = preg_match_all('/[a-z]/', $data['message'] ?? '');
    if ($upperCount > 20 && $lowerCount > 0 && ($upperCount / $lowerCount) > 3) {
        return 'excessive_caps';
    }

    // --- Advanced Bot Detection ---

    // Gmail dot pattern check (bots often use x.o.x.o.b pattern)
    $gmailDotLimit = $config['security']['gmail_dot_limit'] ?? 3;
    if (strpos($email, '@gmail.com') !== false) {
        $username = substr($email, 0, strpos($email, '@'));
        $dotCount = substr_count($username, '.');
        if ($dotCount >= $gmailDotLimit) {
            return 'gmail_dot_pattern:' . $dotCount . '_dots';
        }
    }

    // Name validation (check for gibberish names)
    if ($config['security']['name_validation'] ?? true) {
        $name = $data['name'] ?? (($data['firstName'] ?? '') . ' ' . ($data['lastName'] ?? ''));
        $name = trim($name);
        if (strlen($name) > 0) {
            $nameResult = isGibberishName($name);
            if ($nameResult !== true) {
                return 'invalid_name:' . $nameResult;
            }
        }
    }

    // Phone area code validation
    if ($config['security']['phone_area_code_validation'] ?? true) {
        $phone = preg_replace('/\D/', '', $data['phone'] ?? '');
        if (strlen($phone) >= 10) {
            $validCodes = $config['security']['valid_area_codes'] ?? ['306', '639'];
            $phoneResult = isValidAreaCode($phone, $validCodes);
            if ($phoneResult !== true) {
                return 'invalid_phone:' . $phoneResult;
            }
        }
    }

    // Message word validation (check for gibberish messages)
    if ($config['security']['message_word_validation'] ?? true) {
        $messageText = $data['message'] ?? '';
        if (strlen(trim($messageText)) > 0) {
            $minWords = $config['security']['min_real_words'] ?? 1;
            $messageResult = hasRealWords($messageText, $minWords);
            if ($messageResult !== true) {
                return 'gibberish_message:' . $messageResult;
            }
        }
    }

    // Gibberish detection on all text fields
    if ($config['security']['gibberish_detection'] ?? true) {
        $fieldsToCheck = ['name', 'firstName', 'lastName', 'message'];
        foreach ($fieldsToCheck as $field) {
            $value = $data[$field] ?? '';
            if (strlen($value) > 5 && isRandomString($value)) {
                return 'gibberish_' . $field;
            }
        }
    }

    return true;
}

/**
 * Check if a name looks like gibberish
 */
function isGibberishName($name) {
    // Names should have at least some vowels
    $vowelCount = preg_match_all('/[aeiouAEIOU]/', $name);
    $consonantCount = preg_match_all('/[bcdfghjklmnpqrstvwxyzBCDFGHJKLMNPQRSTVWXYZ]/', $name);
    $letterCount = $vowelCount + $consonantCount;

    // If mostly letters and very few vowels, likely gibberish
    if ($letterCount > 5 && $vowelCount == 0) {
        return 'no_vowels';
    }

    // Check vowel ratio (normal names have ~35-45% vowels)
    if ($letterCount > 8) {
        $vowelRatio = $vowelCount / $letterCount;
        if ($vowelRatio < 0.15) {
            return 'low_vowel_ratio';
        }
    }

    // Check for too many consecutive consonants (more than 4 is unusual)
    if (preg_match('/[bcdfghjklmnpqrstvwxyz]{5,}/i', $name)) {
        return 'consonant_cluster';
    }

    // Check for random case patterns mid-word (like "RLuWJgVL")
    // Normal names: "John", "McDonald" - capitals only at start or after space/apostrophe
    $words = preg_split('/[\s\'-]+/', $name);
    foreach ($words as $word) {
        if (strlen($word) > 3) {
            // Count mid-word capitals (not first letter)
            $midCaps = preg_match_all('/(?!^)[A-Z]/', $word);
            if ($midCaps > 2) {
                return 'random_caps';
            }
        }
    }

    return true;
}

/**
 * Check if phone has valid North American area code
 */
function isValidAreaCode($phone, $validCodes) {
    // Remove country code if present (1 for North America)
    if (strlen($phone) == 11 && $phone[0] == '1') {
        $phone = substr($phone, 1);
    }

    if (strlen($phone) < 10) {
        return 'too_short';
    }

    $areaCode = substr($phone, 0, 3);

    // Check against valid area codes
    if (!in_array($areaCode, $validCodes)) {
        return 'invalid_area_code:' . $areaCode;
    }

    return true;
}

/**
 * Check if message contains real English words
 */
function hasRealWords($message, $minWords = 1) {
    // Common English words (expanded list for better detection)
    $commonWords = [
        'the', 'be', 'to', 'of', 'and', 'a', 'in', 'that', 'have', 'i',
        'it', 'for', 'not', 'on', 'with', 'he', 'as', 'you', 'do', 'at',
        'this', 'but', 'his', 'by', 'from', 'they', 'we', 'say', 'her', 'she',
        'or', 'an', 'will', 'my', 'one', 'all', 'would', 'there', 'their', 'what',
        'so', 'up', 'out', 'if', 'about', 'who', 'get', 'which', 'go', 'me',
        'when', 'make', 'can', 'like', 'time', 'no', 'just', 'him', 'know', 'take',
        'people', 'into', 'year', 'your', 'good', 'some', 'could', 'them', 'see', 'other',
        'than', 'then', 'now', 'look', 'only', 'come', 'its', 'over', 'think', 'also',
        'back', 'after', 'use', 'two', 'how', 'our', 'work', 'first', 'well', 'way',
        'even', 'new', 'want', 'because', 'any', 'these', 'give', 'day', 'most', 'us',
        // Container/shipping related words
        'container', 'shipping', 'storage', 'delivery', 'pickup', 'quote', 'price',
        'buy', 'sell', 'rent', 'lease', 'need', 'looking', 'interested', 'please',
        'thanks', 'thank', 'hello', 'hi', 'hey', 'call', 'contact', 'email', 'phone',
        'question', 'questions', 'help', 'information', 'info', 'size', 'condition',
        'new', 'used', 'foot', 'feet', 'ft', 'standard', 'high', 'cube',
        'deliver', 'delivered', 'location', 'address', 'available', 'cost', 'much',
        'weekend', 'weekends', 'week', 'today', 'tomorrow', 'soon', 'asap',
        'yes', 'no', 'maybe', 'ok', 'okay',
    ];

    // Extract words from message
    $words = preg_split('/[\s\p{P}]+/u', strtolower($message));
    $words = array_filter($words, fn($w) => strlen($w) >= 2);

    $realWordCount = 0;
    foreach ($words as $word) {
        if (in_array($word, $commonWords)) {
            $realWordCount++;
        }
    }

    if ($realWordCount < $minWords) {
        return 'no_real_words';
    }

    return true;
}

/**
 * Check if a string appears to be random characters
 */
function isRandomString($str) {
    // Skip if too short
    if (strlen($str) < 6) return false;

    // Check entropy (randomness)
    $str = strtolower($str);
    $chars = count_chars($str, 1);
    $len = strlen($str);

    // Calculate entropy
    $entropy = 0;
    foreach ($chars as $count) {
        $p = $count / $len;
        $entropy -= $p * log($p, 2);
    }

    // High entropy combined with other indicators suggests random string
    // Normal English text has entropy around 4.0-4.5
    // Random strings have entropy around 4.5-5.0+
    if ($entropy > 4.3) {
        // Also check for lack of common letter patterns
        $hasCommonPatterns = preg_match('/(th|he|in|er|an|re|on|at|en|es|or|te|of|ed|is|it|al|ar|st|to|nt|ng|se|ha|as|ou|io|le|ve|co|me|de|hi|ri|ro|ic|ne|ea|ra|ce)/i', $str);
        if (!$hasCommonPatterns) {
            return true;
        }
    }

    return false;
}
