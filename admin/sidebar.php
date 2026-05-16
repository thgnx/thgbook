<?php
$active   = $active ?? '';
$curFile  = basename($_SERVER['PHP_SELF']);
$navItems = [
    ['file' => 'index.php',  'label' => 'Dashboard', 'icon' => '<path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/>'],
    ['file' => 'books.php',  'label' => 'Books',     'icon' => '<path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/>'],
    ['file' => 'upload.php', 'label' => 'Upload',    'icon' => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/>'],
    ['file' => 'users.php',   'label' => 'Users',   'icon' => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>'],
    ['file' => 'bundles.php', 'label' => 'Bundles', 'icon' => '<rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2"/><line x1="12" y1="12" x2="12" y2="16"/><line x1="10" y1="14" x2="14" y2="14"/>'],
];
?>
<aside class="admin-sidebar">
    <div class="admin-logo">ThgBook</div>

    <nav>
        <?php foreach ($navItems as $item): ?>
            <a href="<?= BASE_URL ?>/admin/<?= $item['file'] ?>"
               class="admin-nav-item <?= $curFile === $item['file'] ? 'active' : '' ?>">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <?= $item['icon'] ?>
                </svg>
                <?= $item['label'] ?>
            </a>
        <?php endforeach; ?>
    </nav>

    <div style="position:absolute;bottom:24px;left:0;right:0;padding:0 12px">
        <a href="<?= BASE_URL ?>/home.php" class="admin-nav-item" style="font-size:13px">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none"
                 stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="m15 18-6-6 6-6"/>
            </svg>
            Back to App
        </a>
        <button onclick="logout()" class="admin-nav-item" style="width:100%;border:none;background:none;cursor:pointer;color:var(--text2);font-size:13px">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none"
                 stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
                <polyline points="16 17 21 12 16 7"/>
                <line x1="21" y1="12" x2="9" y2="12"/>
            </svg>
            Log Out
        </button>
    </div>
</aside>

<script>
if (typeof BASE_URL === 'undefined') var BASE_URL = '<?= BASE_URL ?>';
async function logout() {
    await fetch(BASE_URL + '/api/auth.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'logout' })
    });
    window.location.href = BASE_URL + '/login.php';
}
</script>
