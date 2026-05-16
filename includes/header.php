<?php
$pageTitle   = $pageTitle   ?? 'ThgBook';
$showBack    = $showBack    ?? false;
$topBarRight = $topBarRight ?? '';
$_username   = htmlspecialchars($_SESSION['username'] ?? '');
?>
<header class="top-bar">
    <div class="top-bar-inner">
        <?php if ($showBack): ?>
            <button class="icon-btn back-btn" onclick="history.back()" aria-label="Go back">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="m15 18-6-6 6-6"/>
                </svg>
            </button>
        <?php else: ?>
            <span class="logo">ThgBook</span>
        <?php endif; ?>

        <h1 class="page-title"><?= htmlspecialchars($pageTitle) ?></h1>

        <div class="top-bar-actions">
            <?php if ($topBarRight): ?>
                <?= $topBarRight ?>
            <?php else: ?>
                <?php if ($_username): ?>
                    <a href="<?= BASE_URL ?>/settings.php" class="icon-btn" aria-label="Settings" title="Account settings">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none"
                             stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="8" r="4"/>
                            <path d="M20 21a8 8 0 1 0-16 0"/>
                        </svg>
                    </a>
                    <button class="icon-btn" onclick="logout()" aria-label="Logout" title="Logout">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none"
                             stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
                            <polyline points="16 17 21 12 16 7"/>
                            <line x1="21" y1="12" x2="9" y2="12"/>
                        </svg>
                    </button>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</header>
