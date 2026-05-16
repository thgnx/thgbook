<?php
require_once __DIR__ . '/../includes/admin_check.php';
require_once __DIR__ . '/../includes/db.php';

$pdo = getDB();

$userCount = (int) $pdo->query('SELECT COUNT(*) FROM users WHERE role = \'user\'')->fetchColumn();
$bookCount = (int) $pdo->query('SELECT COUNT(*) FROM store_books')->fetchColumn();
$redeemCount = (int) $pdo->query('SELECT COUNT(*) FROM redeemed_codes')->fetchColumn();

$recentUsers = $pdo->query(
    'SELECT id, username, email, created_at FROM users ORDER BY created_at DESC LIMIT 5'
)->fetchAll();

$recentBooks = $pdo->query(
    'SELECT id, title, author, file_type, redeem_code, created_at FROM store_books ORDER BY created_at DESC LIMIT 5'
)->fetchAll();

$active = 'dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ThgBook Admin — Dashboard</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,500;0,9..40,600;1,9..40,400&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body>
<div class="admin-layout">
    <?php include __DIR__ . '/sidebar.php'; ?>

    <main class="admin-main">
        <div class="admin-header">
            <h1 class="admin-title">Dashboard</h1>
            <p class="admin-subtitle">Welcome back, <?= htmlspecialchars($_SESSION['username']) ?></p>
        </div>

        <div class="admin-stats">
            <div class="admin-stat-card">
                <div class="admin-stat-label">Registered Users</div>
                <div class="admin-stat-number"><?= $userCount ?></div>
            </div>
            <div class="admin-stat-card">
                <div class="admin-stat-label">Store Books</div>
                <div class="admin-stat-number"><?= $bookCount ?></div>
            </div>
            <div class="admin-stat-card">
                <div class="admin-stat-label">Total Redeems</div>
                <div class="admin-stat-number"><?= $redeemCount ?></div>
            </div>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px">

            <div>
                <h2 style="font-family:'Playfair Display',serif;font-size:18px;margin-bottom:16px">Recent Users</h2>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr><th>Username</th><th>Joined</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentUsers as $u): ?>
                                <tr>
                                    <td><?= htmlspecialchars($u['username']) ?></td>
                                    <td style="color:var(--text2);font-size:12px"><?= date('M j, Y', strtotime($u['created_at'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($recentUsers)): ?>
                                <tr><td colspan="2" style="color:var(--text2);text-align:center">No users yet</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div>
                <h2 style="font-family:'Playfair Display',serif;font-size:18px;margin-bottom:16px">Recent Books</h2>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr><th>Title</th><th>Code</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentBooks as $b): ?>
                                <tr>
                                    <td style="max-width:140px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= htmlspecialchars($b['title']) ?></td>
                                    <td><span class="code-badge"><?= htmlspecialchars($b['redeem_code']) ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($recentBooks)): ?>
                                <tr><td colspan="2" style="color:var(--text2);text-align:center">No books yet</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </main>
</div>
</body>
</html>
