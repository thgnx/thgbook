<?php
require_once __DIR__ . '/../includes/admin_check.php';
require_once __DIR__ . '/../includes/db.php';

$pdo = getDB();

$users = $pdo->query(
    "SELECT u.id, u.username, u.email, u.role, u.created_at,
            COUNT(DISTINCT ub.id) AS book_count
     FROM users u
     LEFT JOIN user_books ub ON ub.user_id = u.id
     GROUP BY u.id
     ORDER BY u.created_at DESC"
)->fetchAll();

// Stats
$totalUsers  = count($users);
$totalAdmins = count(array_filter($users, fn($u) => $u['role'] === 'admin'));
$weekAgo     = date('Y-m-d H:i:s', strtotime('-7 days'));
$newThisWeek = $pdo->prepare('SELECT COUNT(*) FROM users WHERE created_at >= ?');
$newThisWeek->execute([$weekAgo]);
$newCount = (int) $newThisWeek->fetchColumn();

$currentAdminId = (int) $_SESSION['user_id'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ThgBook Admin — Users</title>
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
            <h1 class="admin-title">Users</h1>
            <p class="admin-subtitle"><?= $totalUsers ?> registered accounts</p>
        </div>

        <!-- Stats -->
        <div class="admin-stats" style="margin-bottom:24px">
            <div class="admin-stat-card">
                <div class="admin-stat-label">Total Users</div>
                <div class="admin-stat-number"><?= $totalUsers ?></div>
            </div>
            <div class="admin-stat-card">
                <div class="admin-stat-label">Admins</div>
                <div class="admin-stat-number"><?= $totalAdmins ?></div>
            </div>
            <div class="admin-stat-card">
                <div class="admin-stat-label">New This Week</div>
                <div class="admin-stat-number"><?= $newCount ?></div>
            </div>
        </div>

        <!-- Search -->
        <div style="margin-bottom:16px">
            <input type="text" id="user-search" placeholder="Filter by username or email…"
                   oninput="filterUsers(this.value)"
                   style="width:280px;background:var(--surface2);border:0.5px solid var(--border2);color:var(--text);border-radius:var(--radius2);padding:8px 12px;font-family:'DM Sans',sans-serif;font-size:14px;outline:none"
                   onfocus="this.style.borderColor='var(--accent)'" onblur="this.style.borderColor='var(--border2)'">
        </div>

        <!-- Users table -->
        <div class="table-container">
            <table id="users-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Books</th>
                        <th>Registered</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $u): ?>
                        <tr data-username="<?= strtolower(htmlspecialchars($u['username'])) ?>"
                            data-email="<?= strtolower(htmlspecialchars($u['email'])) ?>">
                            <td style="color:var(--text2);font-size:12px"><?= $u['id'] ?></td>
                            <td style="font-weight:500"><?= htmlspecialchars($u['username']) ?></td>
                            <td style="color:var(--text2);font-size:13px"><?= htmlspecialchars($u['email']) ?></td>
                            <td>
                                <?php if ($u['role'] === 'admin'): ?>
                                    <span class="badge badge-admin">Admin</span>
                                <?php else: ?>
                                    <span style="font-size:12px;color:var(--text2)">User</span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align:center"><?= (int)$u['book_count'] ?></td>
                            <td style="color:var(--text2);font-size:12px"><?= date('M j, Y', strtotime($u['created_at'])) ?></td>
                            <td>
                                <div style="display:flex;gap:6px;flex-wrap:wrap">
                                    <button class="btn btn-secondary btn-sm"
                                            onclick="viewBooks(<?= $u['id'] ?>, '<?= htmlspecialchars(addslashes($u['username'])) ?>')">
                                        Books
                                    </button>
                                    <?php if ($u['id'] !== $currentAdminId): ?>
                                        <button class="btn btn-secondary btn-sm"
                                                onclick="toggleRole(<?= $u['id'] ?>, '<?= htmlspecialchars(addslashes($u['username'])) ?>', '<?= $u['role'] ?>')">
                                            <?= $u['role'] === 'admin' ? 'Demote' : 'Promote' ?>
                                        </button>
                                        <button class="btn btn-danger btn-sm"
                                                onclick="deleteUser(<?= $u['id'] ?>, '<?= htmlspecialchars(addslashes($u['username'])) ?>')">
                                            Delete
                                        </button>
                                    <?php else: ?>
                                        <span style="font-size:12px;color:var(--text2)">You</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($users)): ?>
                        <tr><td colspan="7" style="text-align:center;color:var(--text2);padding:32px">No users yet</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>
</div>

<!-- User Books Modal -->
<div id="books-modal" class="modal-overlay" style="display:none" onclick="if(event.target===this)closeModal()">
    <div class="modal" onclick="event.stopPropagation()" style="max-width:560px;width:92vw">
        <h2 class="modal-title" id="books-modal-title">Library</h2>
        <div id="books-modal-body" style="max-height:60vh;overflow-y:auto"></div>
        <div class="modal-actions">
            <button class="btn btn-secondary" onclick="closeModal()">Close</button>
        </div>
    </div>
</div>

<script>
// BASE_URL and logout() are already declared in sidebar.php

function filterUsers(q) {
    q = q.toLowerCase();
    document.querySelectorAll('#users-table tbody tr[data-username]').forEach(function(tr) {
        var match = tr.dataset.username.includes(q) || tr.dataset.email.includes(q);
        tr.style.display = match ? '' : 'none';
    });
}

function escHtml(str) {
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

async function apiPost(action, payload) {
    var r = await fetch(BASE_URL + '/api/admin_user_action.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(Object.assign({ action: action }, payload))
    });
    return r.json();
}

async function viewBooks(userId, username) {
    document.getElementById('books-modal-title').textContent = username + "'s Library";
    document.getElementById('books-modal-body').innerHTML = '<p style="color:var(--text2);padding:16px 0">Loading…</p>';
    document.getElementById('books-modal').style.display = 'flex';

    var res  = await apiPost('get_user_books', { user_id: userId });
    var body = document.getElementById('books-modal-body');

    if (!res.success) {
        body.innerHTML = '<p style="color:#e07370">Failed to load books.</p>';
        return;
    }
    if (!res.books.length) {
        body.innerHTML = '<p style="color:var(--text2);padding:12px 0">No books in library.</p>';
        return;
    }

    body.innerHTML = res.books.map(function(b) {
        var cover = b.cover_url
            ? '<img src="' + escHtml(b.cover_url) + '" alt="" style="width:36px;height:50px;object-fit:cover;border-radius:3px;flex-shrink:0" onerror="this.style.display=\'none\'">'
            : '<div style="width:36px;height:50px;background:var(--surface2);border-radius:3px;flex-shrink:0;display:flex;align-items:center;justify-content:center;font-size:9px;color:var(--text2)">' + escHtml(b.file_type.toUpperCase()) + '</div>';
        return '<div style="display:flex;align-items:center;gap:12px;padding:10px 0;border-bottom:1px solid var(--border)">'
            + cover
            + '<div style="flex:1;min-width:0">'
            + '<div style="font-weight:500;font-size:14px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">' + escHtml(b.title) + '</div>'
            + '<div style="font-size:12px;color:var(--text2)">' + escHtml(b.author) + ' &middot; ' + escHtml(b.file_type.toUpperCase()) + '</div>'
            + '</div>'
            + '<div style="font-size:12px;color:var(--text2);flex-shrink:0">' + parseFloat(b.percentage).toFixed(0) + '%</div>'
            + '</div>';
    }).join('');
}

async function toggleRole(userId, username, currentRole) {
    var verb = currentRole === 'admin' ? 'Demote' : 'Promote';
    var dest = currentRole === 'admin' ? 'User' : 'Admin';
    if (!confirm(verb + ' ' + username + ' to ' + dest + '?')) return;
    var btn = event.currentTarget;
    btn.disabled = true;
    var res = await apiPost('toggle_role', { user_id: userId });
    if (res.success) {
        var row     = btn.closest('tr');
        var roleTd  = row.querySelector('td:nth-child(4)');
        var isAdmin = res.new_role === 'admin';
        roleTd.innerHTML = isAdmin
            ? '<span class="badge badge-admin">Admin</span>'
            : '<span style="font-size:12px;color:var(--text2)">User</span>';
        btn.textContent = isAdmin ? 'Demote' : 'Promote';
        btn.onclick = function() { toggleRole(userId, username, res.new_role); };
    } else {
        alert(res.error || 'Failed');
    }
    btn.disabled = false;
}

async function deleteUser(userId, username) {
    if (!confirm('Delete "' + username + '" and all their data? This cannot be undone.')) return;
    var btn = event.currentTarget;
    btn.disabled = true;
    var res = await apiPost('delete_user', { user_id: userId });
    if (res.success) {
        var row = btn.closest('tr');
        row.style.transition = 'opacity 0.3s';
        row.style.opacity = '0';
        setTimeout(function() { row.remove(); }, 320);
    } else {
        alert(res.error || 'Failed');
        btn.disabled = false;
    }
}

function closeModal() {
    document.getElementById('books-modal').style.display = 'none';
}
</script>
</body>
</html>
