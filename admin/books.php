<?php
require_once __DIR__ . '/../includes/admin_check.php';
require_once __DIR__ . '/../includes/db.php';

$pdo = getDB();

$books = $pdo->query(
    'SELECT sb.*, COUNT(rc.id) AS redeem_count
     FROM store_books sb
     LEFT JOIN redeemed_codes rc ON rc.store_book_id = sb.id
     GROUP BY sb.id
     ORDER BY sb.created_at DESC'
)->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ThgBook Admin — Books</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,500;0,9..40,600;1,9..40,400&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body>
<div class="admin-layout">
    <?php include __DIR__ . '/sidebar.php'; ?>

    <main class="admin-main">
        <div class="admin-header" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px">
            <div>
                <h1 class="admin-title">Store Books</h1>
                <p class="admin-subtitle"><?= count($books) ?> books in the store</p>
            </div>
            <a href="<?= BASE_URL ?>/admin/upload.php" class="btn btn-primary">+ Upload New Book</a>
        </div>

        <?php if (empty($books)): ?>
            <div class="empty-state">
                <h3>No books yet</h3>
                <p>Upload your first book to generate a redeem code.</p>
                <a href="<?= BASE_URL ?>/admin/upload.php" class="btn btn-primary">Upload a Book</a>
            </div>
        <?php else: ?>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Cover</th>
                            <th>Title</th>
                            <th>Author</th>
                            <th>Genre</th>
                            <th>Type</th>
                            <th>Redeem Code</th>
                            <th>Redeems</th>
                            <th>Added</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($books as $book): ?>
                            <tr>
                                <td>
                                    <?php if ($book['cover_url']): ?>
                                        <img src="<?= htmlspecialchars($book['cover_url']) ?>"
                                             alt="" style="width:36px;height:54px;object-fit:cover;border-radius:4px"
                                             onerror="this.style.display='none'">
                                    <?php else: ?>
                                        <div style="width:36px;height:54px;background:var(--surface2);border-radius:4px;display:flex;align-items:center;justify-content:center">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--text2)" stroke-width="1.5"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td style="font-weight:500;max-width:180px">
                                    <div style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= htmlspecialchars($book['title']) ?></div>
                                </td>
                                <td style="color:var(--text2)"><?= htmlspecialchars($book['author']) ?></td>
                                <td style="color:var(--text2);font-size:12px"><?= htmlspecialchars($book['genre'] ?? '—') ?></td>
                                <td><span class="badge badge-<?= $book['file_type'] ?>"><?= strtoupper($book['file_type']) ?></span></td>
                                <td><span class="code-badge"><?= htmlspecialchars($book['redeem_code']) ?></span></td>
                                <td style="text-align:center"><?= (int)$book['redeem_count'] ?></td>
                                <td style="color:var(--text2);font-size:12px"><?= date('M j, Y', strtotime($book['created_at'])) ?></td>
                                <td>
                                    <button class="btn btn-danger" style="padding:6px 12px;font-size:12px"
                                            onclick="deleteBook(<?= $book['id'] ?>, '<?= addslashes($book['title']) ?>')">
                                        Delete
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </main>
</div>

<script>
async function deleteBook(id, title) {
    if (!confirm('Delete "' + title + '" from the store? Users who already redeemed it will keep access.')) return;

    const r    = await fetch(BASE_URL + '/api/admin_delete_book.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ book_id: id })
    });
    const data = await r.json();
    if (data.success) {
        location.reload();
    } else {
        alert(data.error || 'Delete failed');
    }
}
</script>
</body>
</html>
