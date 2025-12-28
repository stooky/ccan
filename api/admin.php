<?php
/**
 * C-Can Sam Admin Panel
 *
 * View contact form submissions
 * Access via: /api/admin.php?key=YOUR_SECRET_PATH
 */

// Load configuration
$configPath = dirname(__DIR__) . '/config.yaml';
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

$secretPath = $config['admin']['secret_path'] ?? 'ccan-admin-2024';
$perPage = $config['admin']['per_page'] ?? 50;
$logFile = dirname(__DIR__) . '/' . ($config['logging']['submissions_file'] ?? 'data/submissions.json');

// Check authentication
$providedKey = $_GET['key'] ?? '';
if ($providedKey !== $secretPath) {
    http_response_code(404);
    echo '<!DOCTYPE html><html><head><title>404 Not Found</title></head><body><h1>Not Found</h1></body></html>';
    exit();
}

// Load submissions
$submissions = [];
if (file_exists($logFile)) {
    $content = file_get_contents($logFile);
    $submissions = json_decode($content, true) ?? [];
}

// Pagination
$page = max(1, (int)($_GET['page'] ?? 1));
$totalSubmissions = count($submissions);
$totalPages = max(1, ceil($totalSubmissions / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;
$pagedSubmissions = array_slice($submissions, $offset, $perPage);

// Handle delete action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $deleteId = $_POST['delete_id'];
    $submissions = array_filter($submissions, fn($s) => $s['id'] !== $deleteId);
    $submissions = array_values($submissions);
    file_put_contents($logFile, json_encode($submissions, JSON_PRETTY_PRINT));
    header('Location: ?key=' . urlencode($secretPath) . '&page=' . $page . '&deleted=1');
    exit();
}

// Handle export action
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="submissions-' . date('Y-m-d') . '.csv"');

    $output = fopen('php://output', 'w');
    fputcsv($output, ['ID', 'Date', 'Time', 'Name', 'Email', 'Phone', 'Subject', 'Message', 'IP']);

    foreach ($submissions as $sub) {
        fputcsv($output, [
            $sub['id'],
            $sub['date'],
            $sub['time'],
            $sub['firstName'] . ' ' . $sub['lastName'],
            $sub['email'],
            $sub['phone'] ?? '',
            $sub['subject'] ?? '',
            $sub['message'],
            $sub['ip']
        ]);
    }

    fclose($output);
    exit();
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Submissions - Admin</title>
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
            display: flex; gap: 1rem; margin-bottom: 2rem; flex-wrap: wrap;
        }
        .stat {
            background: white; padding: 1rem 1.5rem; border-radius: 0.5rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .stat-value { font-size: 1.5rem; font-weight: 700; color: #d97706; }
        .stat-label { font-size: 0.875rem; color: #6b7280; }
        .actions { margin-bottom: 1.5rem; }
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
        .submissions { display: flex; flex-direction: column; gap: 1rem; }
        .submission {
            background: white; border-radius: 0.5rem; padding: 1.5rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .submission-header {
            display: flex; justify-content: space-between; align-items: flex-start;
            margin-bottom: 1rem; flex-wrap: wrap; gap: 0.5rem;
        }
        .submission-name { font-weight: 600; font-size: 1.125rem; }
        .submission-email { color: #d97706; }
        .submission-meta { font-size: 0.875rem; color: #6b7280; text-align: right; }
        .submission-subject {
            display: inline-block; background: #fef3c7; color: #92400e;
            padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.75rem;
            font-weight: 500; margin-bottom: 0.75rem;
        }
        .submission-message {
            background: #f9fafb; padding: 1rem; border-radius: 0.375rem;
            white-space: pre-wrap; font-size: 0.875rem;
        }
        .submission-footer {
            display: flex; justify-content: space-between; align-items: center;
            margin-top: 1rem; padding-top: 1rem; border-top: 1px solid #e5e7eb;
            font-size: 0.75rem; color: #9ca3af;
        }
        .pagination {
            display: flex; justify-content: center; gap: 0.5rem; margin-top: 2rem;
        }
        .pagination a, .pagination span {
            padding: 0.5rem 1rem; border-radius: 0.375rem; text-decoration: none;
        }
        .pagination a { background: white; color: #374151; box-shadow: 0 1px 2px rgba(0,0,0,0.1); }
        .pagination a:hover { background: #f3f4f6; }
        .pagination .current { background: #d97706; color: white; }
        .empty {
            text-align: center; padding: 3rem; background: white;
            border-radius: 0.5rem; color: #6b7280;
        }
        @media (max-width: 640px) {
            body { padding: 1rem; }
            .submission-header { flex-direction: column; }
            .submission-meta { text-align: left; }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Contact Form Submissions</h1>
        <p class="subtitle">C-Can Sam Admin Panel</p>

        <?php if (isset($_GET['deleted'])): ?>
            <div class="alert">Submission deleted successfully.</div>
        <?php endif; ?>

        <div class="stats">
            <div class="stat">
                <div class="stat-value"><?= $totalSubmissions ?></div>
                <div class="stat-label">Total Submissions</div>
            </div>
            <div class="stat">
                <div class="stat-value"><?= count(array_filter($submissions, fn($s) => $s['date'] === date('Y-m-d'))) ?></div>
                <div class="stat-label">Today</div>
            </div>
            <div class="stat">
                <div class="stat-value"><?= count(array_filter($submissions, fn($s) => strtotime($s['date']) >= strtotime('-7 days'))) ?></div>
                <div class="stat-label">Last 7 Days</div>
            </div>
        </div>

        <div class="actions">
            <a href="?key=<?= urlencode($secretPath) ?>&export=csv" class="btn btn-secondary">Export CSV</a>
            <a href="?key=<?= urlencode($secretPath) ?>" class="btn btn-secondary">Refresh</a>
        </div>

        <?php if (empty($pagedSubmissions)): ?>
            <div class="empty">
                <p>No submissions yet.</p>
            </div>
        <?php else: ?>
            <div class="submissions">
                <?php foreach ($pagedSubmissions as $sub): ?>
                    <div class="submission">
                        <div class="submission-header">
                            <div>
                                <div class="submission-name">
                                    <?= htmlspecialchars($sub['firstName'] . ' ' . $sub['lastName']) ?>
                                </div>
                                <a href="mailto:<?= htmlspecialchars($sub['email']) ?>" class="submission-email">
                                    <?= htmlspecialchars($sub['email']) ?>
                                </a>
                                <?php if (!empty($sub['phone'])): ?>
                                    <span style="color: #6b7280;"> · <?= htmlspecialchars($sub['phone']) ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="submission-meta">
                                <div><?= htmlspecialchars($sub['date']) ?></div>
                                <div><?= htmlspecialchars($sub['time']) ?></div>
                            </div>
                        </div>
                        <?php if (!empty($sub['subject'])): ?>
                            <span class="submission-subject"><?= htmlspecialchars($sub['subject']) ?></span>
                        <?php endif; ?>
                        <div class="submission-message"><?= htmlspecialchars($sub['message']) ?></div>
                        <div class="submission-footer">
                            <span>ID: <?= htmlspecialchars($sub['id']) ?> · IP: <?= htmlspecialchars($sub['ip']) ?></span>
                            <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this submission?');">
                                <input type="hidden" name="delete_id" value="<?= htmlspecialchars($sub['id']) ?>">
                                <button type="submit" class="btn btn-danger">Delete</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php if ($totalPages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?key=<?= urlencode($secretPath) ?>&page=<?= $page - 1 ?>">← Previous</a>
                    <?php endif; ?>

                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <?php if ($i === $page): ?>
                            <span class="current"><?= $i ?></span>
                        <?php else: ?>
                            <a href="?key=<?= urlencode($secretPath) ?>&page=<?= $i ?>"><?= $i ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>

                    <?php if ($page < $totalPages): ?>
                        <a href="?key=<?= urlencode($secretPath) ?>&page=<?= $page + 1 ?>">Next →</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</body>
</html>
