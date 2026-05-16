<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/includes/db.php';

$userId      = (int) $_SESSION['user_id'];
$userBookId  = (int) ($_GET['id']       ?? 0);
$storeBookId = (int) ($_GET['store_id'] ?? 0);
$pdo         = getDB();

if ($storeBookId && ($_SESSION['role'] ?? '') === 'admin') {
    // Admin reading a store book directly (no progress tracking)
    $stmt = $pdo->prepare('SELECT * FROM store_books WHERE id = ?');
    $stmt->execute([$storeBookId]);
    $book = $stmt->fetch();
    if (!$book) {
        header('Location: ' . BASE_URL . '/library.php');
        exit;
    }
    $fileUrl    = BASE_URL . '/' . ltrim($book['file_path'], '/');
    $fileType   = $book['file_type'];
    $savedCfi   = '';
    $savedPage  = 0;
    $percentage = 0.0;
    $userBookId = 0;
} elseif ($userBookId) {
    $stmt = $pdo->prepare(
        "SELECT ub.*, COALESCE(rp.cfi_position, '') AS cfi_position,
                COALESCE(rp.page_number, 0) AS saved_page,
                COALESCE(rp.percentage, 0)  AS percentage
         FROM user_books ub
         LEFT JOIN reading_progress rp ON rp.user_book_id = ub.id AND rp.user_id = ub.user_id
         WHERE ub.id = ? AND ub.user_id = ?"
    );
    $stmt->execute([$userBookId, $userId]);
    $book = $stmt->fetch();
    if (!$book) {
        header('Location: ' . BASE_URL . '/library.php');
        exit;
    }
    $fileUrl    = BASE_URL . '/' . ltrim($book['file_path'], '/');
    $fileType   = $book['file_type'];
    $savedCfi   = $book['cfi_position'];
    $savedPage  = (int) $book['saved_page'];
    $percentage = (float) $book['percentage'];
} else {
    header('Location: ' . BASE_URL . '/library.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($book['title']) ?> — ThgBook</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,500;0,9..40,600;1,9..40,400&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
    <link rel="manifest" href="/tools/ThgBook/manifest.json">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="ThgBook">
    <link rel="apple-touch-icon" href="/tools/ThgBook/assets/img/icon-192.png">
    <?php if ($fileType === 'epub'): ?>
        <script src="https://cdn.jsdelivr.net/npm/jszip/dist/jszip.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/epubjs/dist/epub.min.js"></script>
    <?php else: ?>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
    <?php endif; ?>
</head>
<body class="reader-body">

<!-- Loading overlay -->
<div id="reader-loading" class="reader-loading">
    <div class="reader-loading-spinner"></div>
    <div class="reader-loading-title"><?= htmlspecialchars($book['title']) ?></div>
</div>

<!-- TOC Panel (EPUB only) -->
<?php if ($fileType === 'epub'): ?>
<div class="reader-panel-backdrop" id="toc-backdrop"></div>
<div class="reader-panel reader-toc-panel" id="toc-panel">
    <div class="reader-panel-header">
        <h3>Contents</h3>
        <button class="icon-btn" id="toc-close" aria-label="Close">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none"
                 stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
            </svg>
        </button>
    </div>
    <div class="reader-panel-body" id="toc-list">
        <p style="color:var(--text2);font-size:13px">Loading…</p>
    </div>
</div>
<?php endif; ?>

<!-- Settings Panel -->
<div class="reader-panel-backdrop" id="settings-backdrop"></div>
<div class="reader-panel reader-settings-panel" id="settings-panel">
    <div class="reader-panel-header">
        <h3>Settings</h3>
        <button class="icon-btn" id="settings-close" aria-label="Close">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none"
                 stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
            </svg>
        </button>
    </div>
    <div class="reader-panel-body">
        <div class="settings-group">
            <span class="settings-label">Theme</span>
            <div class="theme-options">
                <button class="theme-dot" data-theme="light" style="background:#f8f5ef" title="Light"></button>
                <button class="theme-dot" data-theme="sepia" style="background:#f4ecd8" title="Sepia"></button>
                <button class="theme-dot" data-theme="gray"  style="background:#2d2d2d" title="Gray"></button>
                <button class="theme-dot" data-theme="dark"  style="background:#111111" title="Dark"></button>
            </div>
        </div>
        <?php if ($fileType === 'epub'): ?>
        <div class="settings-group">
            <span class="settings-label">Font</span>
            <select id="font-family-select" class="settings-select">
                <option value="Georgia, serif">Georgia</option>
                <option value="'DM Sans', sans-serif">DM Sans</option>
                <option value="Palatino, serif">Palatino</option>
                <option value="'Courier New', monospace">Courier New</option>
            </select>
        </div>
        <div class="settings-group">
            <span class="settings-label">Font Size</span>
            <div class="stepper">
                <button class="stepper-btn" id="font-size-dec">−</button>
                <span class="stepper-val" id="font-size-val">18px</span>
                <button class="stepper-btn" id="font-size-inc">+</button>
            </div>
        </div>
        <div class="settings-group">
            <span class="settings-label">Line Height</span>
            <div class="stepper">
                <button class="stepper-btn" id="line-height-dec">−</button>
                <span class="stepper-val" id="line-height-val">1.7</span>
                <button class="stepper-btn" id="line-height-inc">+</button>
            </div>
        </div>
        <div class="settings-group">
            <span class="settings-label">Margin</span>
            <div class="stepper">
                <button class="stepper-btn" id="margin-dec">−</button>
                <span class="stepper-val" id="margin-val">4%</span>
                <button class="stepper-btn" id="margin-inc">+</button>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<div class="reader-page">
    <!-- Top bar -->
    <div class="reader-top-bar">
        <div style="display:flex;gap:2px;flex-shrink:0">
            <button class="icon-btn" onclick="history.back()" aria-label="Back">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="m15 18-6-6 6-6"/>
                </svg>
            </button>
            <?php if ($fileType === 'epub'): ?>
            <button class="icon-btn" id="toc-btn" aria-label="Table of contents">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="3" y1="6" x2="21" y2="6"/>
                    <line x1="3" y1="12" x2="15" y2="12"/>
                    <line x1="3" y1="18" x2="18" y2="18"/>
                </svg>
            </button>
            <?php endif; ?>
        </div>

        <div class="reader-title-block">
            <div class="reader-title"><?= htmlspecialchars($book['title']) ?></div>
            <?php if ($fileType === 'epub'): ?>
            <div class="reader-chapter" id="reader-chapter"></div>
            <?php endif; ?>
        </div>

        <button class="icon-btn" id="settings-btn" aria-label="Settings">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none"
                 stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="3"/>
                <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/>
            </svg>
        </button>
    </div>

    <!-- Book content -->
    <div class="reader-content">
        <?php if ($fileType === 'epub'): ?>
            <div id="epub-container"></div>
            <button class="epub-nav-btn epub-prev" id="epub-prev" aria-label="Previous page">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="m15 18-6-6 6-6"/>
                </svg>
            </button>
            <button class="epub-nav-btn epub-next" id="epub-next" aria-label="Next page">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="m9 18 6-6-6-6"/>
                </svg>
            </button>
            <div id="swipe-overlay"></div>
        <?php else: ?>
            <div id="pdf-container"></div>
        <?php endif; ?>
    </div>

    <!-- Bottom bar -->
    <div class="reader-bottom-bar">
        <div class="reader-progress-bar" id="reader-progress-bar">
            <div class="reader-progress-fill" id="progress-fill" style="width:<?= $percentage ?>%"></div>
        </div>
        <div class="reader-nav">
            <?php if ($fileType === 'pdf'): ?>
                <button class="btn btn-secondary" id="pdf-prev" style="padding:8px 16px">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none"
                         stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="m15 18-6-6 6-6"/>
                    </svg>
                </button>
            <?php else: ?>
                <button class="btn-nav-mobile" id="mobile-prev">&#x2039;</button>
            <?php endif; ?>

            <div class="reader-page-info" id="page-info">
                <?php echo $percentage > 0 ? number_format($percentage, 0) . '%' : 'Loading…'; ?>
            </div>

            <?php if ($fileType === 'pdf'): ?>
                <button class="btn btn-secondary" id="pdf-next" style="padding:8px 16px">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none"
                         stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="m9 18 6-6-6-6"/>
                    </svg>
                </button>
            <?php else: ?>
                <button class="btn-nav-mobile" id="mobile-next">&#x203a;</button>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
window.cfg = {
    type:        '<?= $fileType ?>',
    fileUrl:     '<?= addslashes($fileUrl) ?>',
    userBookId:  <?= $userBookId ?>,
    savedCfi:    '<?= addslashes($savedCfi) ?>',
    savedPage:   <?= $savedPage ?>,
    percentage:  <?= $percentage ?>
};
</script>
<script src="<?= BASE_URL ?>/assets/js/reader.js"></script>
</body>
</html>
