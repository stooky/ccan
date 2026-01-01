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

        .reviews-filter {
            display: flex; gap: 0.5rem; align-items: center;
        }
        .reviews-filter input {
            padding: 0.5rem 0.75rem; border: 1px solid #d1d5db; border-radius: 0.375rem;
            font-size: 0.875rem; width: 250px;
        }
        .reviews-filter input:focus {
            outline: none; border-color: #d97706; box-shadow: 0 0 0 2px rgba(217, 119, 6, 0.2);
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
            <a href="?key=<?= urlencode($secretPath) ?>&tab=reviews" class="tab <?= $currentTab === 'reviews' ? 'active' : '' ?>">
                Reviews (<?= $reviewsTotalCount ?>)
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
                <div class="reviews-filter">
                    <input type="text" id="reviews-search" placeholder="Search reviews..." />
                </div>
            </div>

            <div class="reviews-list" id="reviews-container">
                <!-- Reviews will be rendered by JavaScript -->
            </div>

            <div class="reviews-pagination" id="reviews-pagination">
                <!-- Pagination will be rendered by JavaScript -->
            </div>
        </div><!-- End Reviews Tab -->

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

        const REVIEWS_PER_PAGE = 10;
        let currentReviewsPage = 1;
        let filteredReviews = [...allReviews];

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

            filteredReviews = allReviews.filter(review => {
                if (!search) return true;
                const searchText = [
                    review.author || '',
                    review.text || '',
                    review.ownerResponse?.text || ''
                ].join(' ').toLowerCase();
                return searchText.includes(search);
            });

            currentReviewsPage = 1;
            renderReviews();
            renderReviewsPagination();
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

                return `
                    <div class="review-card">
                        <div class="review-header">
                            <div class="review-author">
                                <div class="review-avatar">${getInitials(review.author)}</div>
                                <div class="review-author-info">
                                    <div class="review-author-name">${escapeHtml(review.author)}</div>
                                    <div class="review-author-meta">
                                        <div class="review-rating">${renderStars(review.rating)}</div>
                                    </div>
                                </div>
                            </div>
                            <div class="review-date">
                                ${getRelativeTime(review.date)}
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

        // Initial reviews render (only if on reviews tab)
        if (document.getElementById('reviews-tab').classList.contains('active')) {
            renderReviews();
            renderReviewsPagination();
        }

        // Render reviews when tab is clicked (for tabs without page reload)
        document.querySelectorAll('.tab').forEach(tab => {
            tab.addEventListener('click', () => {
                setTimeout(() => {
                    if (document.getElementById('reviews-tab').classList.contains('active')) {
                        renderReviews();
                        renderReviewsPagination();
                    }
                }, 0);
            });
        });
    </script>
</body>
</html>
