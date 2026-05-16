<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/includes/db.php';

$userId = (int) $_SESSION['user_id'];
$pdo    = getDB();

// Only show books the current user has redeemed
$stmt = $pdo->prepare(
    'SELECT sb.id, sb.title, sb.author, sb.cover_url, sb.genre, sb.file_type, sb.description,
            rc.redeemed_at,
            COALESCE(rp.percentage, 0) AS percentage,
            ub.id AS user_book_id
     FROM redeemed_codes rc
     INNER JOIN store_books sb ON sb.id = rc.store_book_id
     LEFT JOIN user_books ub ON ub.store_book_id = sb.id AND ub.user_id = rc.user_id
     LEFT JOIN reading_progress rp ON rp.user_book_id = ub.id AND rp.user_id = rc.user_id
     WHERE rc.user_id = ?
     ORDER BY rc.redeemed_at DESC'
);
$stmt->execute([$userId]);
$redeemedBooks = $stmt->fetchAll();

$pageTitle = 'Store';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ThgBook — Store</title>
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

        <!-- Redeem section -->
        <div class="redeem-section">
            <h2 class="redeem-title">Redeem a Code</h2>
            <p style="font-size:13px;color:var(--text2);margin-bottom:14px">Enter your 8-character code to add a book to your library.</p>
            <div id="redeem-alert"></div>
            <div class="redeem-row">
                <input type="text" id="redeem-code" placeholder="8 or 12-char code" maxlength="12" autocomplete="off" spellcheck="false">
                <button class="btn btn-primary" id="redeem-btn" onclick="redeemCode()">Redeem</button>
            </div>
        </div>

        <!-- Redeemed books -->
        <div class="section">
            <div class="section-header">
                <h2 class="section-title">Your Redeemed Books</h2>
                <span style="font-size:13px;color:var(--text2)"><?= count($redeemedBooks) ?> books</span>
            </div>

            <?php if (empty($redeemedBooks)): ?>
                <div class="empty-state" style="padding:32px 0">
                    <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none"
                         stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/>
                        <line x1="3" y1="6" x2="21" y2="6"/>
                        <path d="M16 10a4 4 0 0 1-8 0"/>
                    </svg>
                    <h3>No books yet</h3>
                    <p>Enter a redeem code above to unlock your first book.</p>
                </div>
            <?php else: ?>
                <div class="books-grid" style="padding:0">
                    <?php foreach ($redeemedBooks as $book):
                        $previewData = [
                            'title'        => $book['title'],
                            'author'       => $book['author'],
                            'cover_url'    => $book['cover_url'] ?? '',
                            'genre'        => $book['genre'] ?? '',
                            'description'  => $book['description'] ?? '',
                            'user_book_id' => (int)$book['user_book_id'],
                        ];
                    ?>
                        <a href="#"
                           class="book-card" style="text-decoration:none"
                           data-book="<?= htmlspecialchars(json_encode($previewData), ENT_QUOTES) ?>"
                           onclick="openBookPreview(JSON.parse(this.dataset.book)); return false;">
                            <?php if ($book['cover_url']): ?>
                                <img src="<?= htmlspecialchars($book['cover_url']) ?>"
                                     alt="<?= htmlspecialchars($book['title']) ?>"
                                     class="book-cover"
                                     onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
                                <div class="book-cover-placeholder" style="display:none">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
                                </div>
                            <?php else: ?>
                                <div class="book-cover-placeholder">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
                                    <span><?= strtoupper($book['file_type']) ?></span>
                                </div>
                            <?php endif; ?>
                            <div class="book-info">
                                <div class="book-title"><?= htmlspecialchars($book['title']) ?></div>
                                <div class="book-author"><?= htmlspecialchars($book['author']) ?></div>
                                <?php if ($book['genre']): ?>
                                    <div class="book-genre"><?= htmlspecialchars($book['genre']) ?></div>
                                <?php endif; ?>
                                <div class="progress-bar">
                                    <div class="progress-fill" style="width:<?= (float)$book['percentage'] ?>%"></div>
                                </div>
                                <div class="progress-label"><?= number_format((float)$book['percentage'], 0) ?>%</div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

    </div>
</main>

<?php include __DIR__ . '/includes/tab_bar.php'; ?>

<!-- Book Preview Modal -->
<div id="book-preview-modal" class="book-preview-modal" onclick="if(event.target===this)closeBookPreview()">
    <div class="book-preview-inner">
        <div class="book-preview-cover-col">
            <img id="preview-cover" class="book-preview-cover" src="" alt=""
                 onerror="this.style.display='none';document.getElementById('preview-cover-ph').style.display='flex'">
            <div id="preview-cover-ph" class="book-preview-cover-placeholder" style="display:none">
                <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/>
                    <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/>
                </svg>
            </div>
        </div>
        <div class="book-preview-info">
            <div id="preview-title" class="book-preview-title"></div>
            <div id="preview-author" class="book-preview-author"></div>
            <span id="preview-genre" class="book-preview-genre"></span>
            <p id="preview-desc" class="book-preview-desc"></p>
            <div class="book-preview-actions">
                <a id="preview-read-btn" href="#" class="btn btn-primary"
                   style="flex:1;text-align:center;display:flex;align-items:center;justify-content:center">
                    Read Now
                </a>
                <button onclick="closeBookPreview()" class="btn btn-secondary">Close</button>
            </div>
        </div>
    </div>
</div>

<script src="<?= BASE_URL ?>/assets/js/main.js"></script>
<script src="<?= BASE_URL ?>/assets/js/store.js"></script>
</body>
</html>
