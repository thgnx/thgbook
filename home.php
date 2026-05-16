<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/includes/db.php';

$userId = (int) $_SESSION['user_id'];
$pdo    = getDB();

// Continue reading — last 2 books with progress
$continueStmt = $pdo->prepare(
    'SELECT ub.id, ub.title, ub.author, ub.cover_url, ub.file_type,
            rp.percentage, rp.last_read
     FROM user_books ub
     INNER JOIN reading_progress rp ON rp.user_book_id = ub.id AND rp.user_id = ub.user_id
     WHERE ub.user_id = ? AND rp.percentage < 100
     ORDER BY rp.last_read DESC
     LIMIT 2'
);
$continueStmt->execute([$userId]);
$continueBooks = $continueStmt->fetchAll();

// Recently added — last 3 books (any source)
$recentStmt = $pdo->prepare(
    'SELECT ub.id, ub.title, ub.author, ub.cover_url, ub.file_type, ub.added_at,
            COALESCE(rp.percentage, 0) AS percentage
     FROM user_books ub
     LEFT JOIN reading_progress rp ON rp.user_book_id = ub.id AND rp.user_id = ub.user_id
     WHERE ub.user_id = ?
     ORDER BY ub.added_at DESC
     LIMIT 3'
);
$recentStmt->execute([$userId]);
$recentBooks = $recentStmt->fetchAll();

// Stats
$totalStmt = $pdo->prepare('SELECT COUNT(*) FROM user_books WHERE user_id = ?');
$totalStmt->execute([$userId]);
$totalBooks = (int) $totalStmt->fetchColumn();

$finishedStmt = $pdo->prepare(
    'SELECT COUNT(*) FROM reading_progress WHERE user_id = ? AND percentage >= 100'
);
$finishedStmt->execute([$userId]);
$finishedBooks = (int) $finishedStmt->fetchColumn();

$readingStmt = $pdo->prepare(
    'SELECT COUNT(*) FROM reading_progress WHERE user_id = ? AND percentage > 0 AND percentage < 100'
);
$readingStmt->execute([$userId]);
$readingBooks = (int) $readingStmt->fetchColumn();

$pageTitle = 'Home';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ThgBook — Home</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,500;0,9..40,600;1,9..40,400&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
    <link rel="manifest" href="/tools/ThgBook/manifest.json">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="ThgBook">
    <link rel="apple-touch-icon" href="/tools/ThgBook/assets/img/icon-192.png">
</head>
<body>
<?php include __DIR__ . '/includes/header.php'; ?>

<main class="page">
    <div class="content">

        <!-- Stats -->
        <div class="section">
            <div class="stats-row">
                <div class="stat-card">
                    <div class="stat-number"><?= $totalBooks ?></div>
                    <div class="stat-label">Total</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= $readingBooks ?></div>
                    <div class="stat-label">Reading</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= $finishedBooks ?></div>
                    <div class="stat-label">Finished</div>
                </div>
            </div>
        </div>

        <!-- Continue Reading -->
        <div class="section">
            <div class="section-header">
                <h2 class="section-title">Continue Reading</h2>
                <a href="<?= BASE_URL ?>/library.php" class="section-link">All books</a>
            </div>

            <?php if (empty($continueBooks)): ?>
                <div class="empty-state" style="padding:24px 0">
                    <p style="font-size:14px;color:var(--text2)">No books in progress — start reading from your <a href="<?= BASE_URL ?>/library.php" style="color:var(--accent)">Library</a>.</p>
                </div>
            <?php else: ?>
                <?php foreach ($continueBooks as $book): ?>
                    <a href="<?= BASE_URL ?>/reader.php?id=<?= $book['id'] ?>" class="continue-card" style="text-decoration:none">
                        <?php if ($book['cover_url']): ?>
                            <img src="<?= htmlspecialchars($book['cover_url']) ?>"
                                 alt="<?= htmlspecialchars($book['title']) ?>"
                                 class="continue-card-cover"
                                 onerror="this.style.display='none'">
                        <?php else: ?>
                            <div class="continue-card-cover" style="display:flex;align-items:center;justify-content:center;background:var(--surface2);border-radius:6px">
                                <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="var(--text2)" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
                            </div>
                        <?php endif; ?>
                        <div class="continue-card-info">
                            <div class="continue-card-title"><?= htmlspecialchars($book['title']) ?></div>
                            <div class="continue-card-author"><?= htmlspecialchars($book['author']) ?></div>
                            <div style="margin-top:auto;padding-top:8px">
                                <div class="progress-bar">
                                    <div class="progress-fill" style="width:<?= (float)$book['percentage'] ?>%"></div>
                                </div>
                                <div class="progress-label"><?= number_format((float)$book['percentage'], 0) ?>% complete</div>
                            </div>
                        </div>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Recently Added -->
        <div class="section">
            <div class="section-header">
                <h2 class="section-title">Recently Added</h2>
                <a href="<?= BASE_URL ?>/library.php" class="section-link">See all</a>
            </div>

            <?php if (empty($recentBooks)): ?>
                <div class="empty-state" style="padding:24px 0">
                    <p style="font-size:14px;color:var(--text2)">Your library is empty. <a href="<?= BASE_URL ?>/library.php" style="color:var(--accent)">Upload a book</a> or <a href="<?= BASE_URL ?>/store.php" style="color:var(--accent)">redeem one</a>.</p>
                </div>
            <?php else: ?>
                <div class="books-grid" style="padding:0">
                    <?php foreach ($recentBooks as $book): ?>
                        <a href="<?= BASE_URL ?>/reader.php?id=<?= $book['id'] ?>" class="book-card" style="text-decoration:none">
                            <?php if ($book['cover_url']): ?>
                                <img src="<?= htmlspecialchars($book['cover_url']) ?>"
                                     alt="<?= htmlspecialchars($book['title']) ?>"
                                     class="book-cover"
                                     onerror="this.outerHTML='<div class=\'book-cover-placeholder\'><svg xmlns=\'http://www.w3.org/2000/svg\' width=\'32\' height=\'32\' viewBox=\'0 0 24 24\' fill=\'none\' stroke=\'currentColor\' stroke-width=\'1.5\'><path d=\'M4 19.5A2.5 2.5 0 0 1 6.5 17H20\'/><path d=\'M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z\'/></svg></div>'">
                            <?php else: ?>
                                <div class="book-cover-placeholder">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
                                </div>
                            <?php endif; ?>
                            <div class="book-info">
                                <div class="book-title"><?= htmlspecialchars($book['title']) ?></div>
                                <div class="book-author"><?= htmlspecialchars($book['author']) ?></div>
                                <div class="progress-bar">
                                    <div class="progress-fill" style="width:<?= (float)$book['percentage'] ?>%"></div>
                                </div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

    </div>
</main>

<?php include __DIR__ . '/includes/tab_bar.php'; ?>
<script src="<?= BASE_URL ?>/assets/js/main.js"></script>
</body>
</html>
