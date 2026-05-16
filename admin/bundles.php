<?php
require_once __DIR__ . '/../includes/admin_check.php';
require_once __DIR__ . '/../includes/db.php';

$pdo = getDB();

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'delete_bundle') {
        $id = (int) ($_POST['bundle_id'] ?? 0);
        if ($id) $pdo->prepare('DELETE FROM bundle_codes WHERE id = ?')->execute([$id]);
        header('Location: ' . BASE_URL . '/admin/bundles.php?msg=deleted');
        exit;
    }

    if ($action === 'create_bundle') {
        $name     = trim($_POST['name'] ?? '');
        $code     = strtoupper(trim($_POST['code'] ?? ''));
        $bookIds  = array_map('intval', (array) ($_POST['book_ids'] ?? []));

        $errors = [];
        if (!$name)               $errors[] = 'Bundle name is required.';
        if (strlen($code) !== 12) $errors[] = 'Code must be exactly 12 characters.';
        if (!preg_match('/^[A-Z0-9]{12}$/', $code)) $errors[] = 'Code must be uppercase letters and digits only.';
        if (empty($bookIds))      $errors[] = 'Select at least one book.';

        if (empty($errors)) {
            try {
                $pdo->beginTransaction();
                $ins = $pdo->prepare('INSERT INTO bundle_codes (code, name) VALUES (?, ?)');
                $ins->execute([$code, $name]);
                $bundleId = (int) $pdo->lastInsertId();
                $insBook  = $pdo->prepare('INSERT INTO bundle_books (bundle_id, store_book_id) VALUES (?, ?)');
                foreach ($bookIds as $bid) $insBook->execute([$bundleId, $bid]);
                $pdo->commit();
                header('Location: ' . BASE_URL . '/admin/bundles.php?msg=created');
                exit;
            } catch (Throwable $e) {
                $pdo->rollBack();
                $errors[] = 'Code already exists or database error.';
            }
        }
        // fall through to show form with errors
        $formErrors = $errors;
    }
}

// Load bundles
$bundles = $pdo->query(
    'SELECT bc.id, bc.code, bc.name, bc.created_at, COUNT(bb.id) AS book_count
     FROM bundle_codes bc
     LEFT JOIN bundle_books bb ON bb.bundle_id = bc.id
     GROUP BY bc.id
     ORDER BY bc.created_at DESC'
)->fetchAll();

// Load store books for the create form
$storeBooks = $pdo->query('SELECT id, title, author FROM store_books ORDER BY title ASC')->fetchAll();

// Generate a fresh random 12-char code
function genCode(): string {
    return strtoupper(bin2hex(random_bytes(6)));
}
$suggestedCode = genCode();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ThgBook Admin — Bundles</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,500;0,9..40,600;1,9..40,400&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
    <style>
        .dual-panel-wrap { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
        .dual-panel-col-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:8px; gap:8px; }
        .dual-panel-col-label { font-size:13px; font-weight:500; white-space:nowrap; }
        .dual-panel-search { font-size:12px; padding:4px 10px; height:30px; flex:1; max-width:180px; }
        .dual-panel {
            border: 1px solid var(--border);
            background: var(--surface2);
            border-radius: var(--radius);
            max-height: 280px;
            overflow-y: auto;
        }
        .dual-panel-empty { color:var(--text2); font-size:13px; padding:14px; }
        .dual-panel-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 14px;
            border-bottom: 1px solid var(--border);
            transition: background 0.1s;
        }
        .dual-panel-item:last-child { border-bottom: none; }
        .dual-panel-item:hover { background: var(--surface); }
        .dual-panel-item-selected { border-left: 3px solid var(--accent); padding-left: 11px; }
        .dual-panel-item-text { flex: 1; min-width: 0; }
        .dual-panel-item-title { font-size: 13px; font-weight: 500; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .dual-panel-item-author { font-size: 11px; color: var(--text2); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .dual-panel-btn { font-size: 11px; padding: 3px 8px; border-radius: 4px; border: 1px solid var(--border); background: var(--surface); color: var(--text); cursor: pointer; white-space: nowrap; flex-shrink: 0; transition: background 0.1s; }
        .dual-panel-btn:hover { background: var(--border); }
        .dual-panel-btn-add:hover { border-color: var(--accent); color: var(--accent); }
        .dual-panel-btn-remove:hover { border-color: #e07370; color: #e07370; }
    </style>
</head>
<body>
<div class="admin-layout">
    <?php include __DIR__ . '/sidebar.php'; ?>

    <main class="admin-main">
        <div class="admin-header">
            <h1 class="admin-title">Bundles</h1>
            <p class="admin-subtitle">Bundle redeem codes — one code unlocks multiple books</p>
        </div>

        <?php if (isset($_GET['msg'])): ?>
            <div class="alert <?= $_GET['msg'] === 'created' ? 'alert-success' : 'alert-error' ?>" style="margin-bottom:20px">
                <?= $_GET['msg'] === 'created' ? 'Bundle created successfully.' : 'Bundle deleted.' ?>
            </div>
        <?php endif; ?>

        <!-- Create bundle form -->
        <div class="admin-card" style="margin-bottom:28px">
            <h2 style="font-family:'Playfair Display',serif;font-size:18px;margin-bottom:16px">Create New Bundle</h2>

            <?php if (!empty($formErrors)): ?>
                <div class="alert alert-error" style="margin-bottom:14px">
                    <?= implode('<br>', array_map('htmlspecialchars', $formErrors)) ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <input type="hidden" name="action" value="create_bundle">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:14px">
                    <div class="form-group" style="margin:0">
                        <label>Bundle Name</label>
                        <input type="text" name="name" placeholder="e.g. Starter Pack"
                               value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" required>
                    </div>
                    <div class="form-group" style="margin:0">
                        <label>12-Character Code</label>
                        <div style="display:flex;gap:8px">
                            <input type="text" name="code" id="bundle-code"
                                   value="<?= htmlspecialchars($_POST['code'] ?? $suggestedCode) ?>"
                                   maxlength="12" style="text-transform:uppercase;letter-spacing:2px;font-family:monospace;flex:1"
                                   oninput="this.value=this.value.toUpperCase().replace(/[^A-Z0-9]/g,'')" required>
                            <button type="button" class="btn btn-secondary" onclick="regenerateCode()" title="Generate new code">↻</button>
                        </div>
                    </div>
                </div>
                <div class="form-group">
                    <label>Books in this bundle <span style="color:var(--text2);font-size:11px;text-transform:none">(select one or more)</span></label>
                    <?php if (empty($storeBooks)): ?>
                        <p style="color:var(--text2);font-size:13px">No store books available — upload some first.</p>
                    <?php else: ?>
                        <?php $preselectedIds = array_map('intval', (array)($_POST['book_ids'] ?? [])); ?>
                        <div class="dual-panel-wrap">
                            <!-- Left: Available -->
                            <div>
                                <div class="dual-panel-col-header">
                                    <span class="dual-panel-col-label">Available (<span id="avail-count">0</span>)</span>
                                    <input type="text" id="book-search" class="dual-panel-search" placeholder="Search…" autocomplete="off">
                                </div>
                                <div class="dual-panel" id="available-panel"></div>
                            </div>
                            <!-- Right: Selected -->
                            <div>
                                <div class="dual-panel-col-header">
                                    <span class="dual-panel-col-label">Selected (<span id="selected-count">0</span>)</span>
                                </div>
                                <div class="dual-panel" id="selected-panel"></div>
                            </div>
                        </div>
                        <div id="selected-inputs"></div>
                        <script>
                        const allBooks = <?= json_encode(array_values($storeBooks)) ?>;
                        const preselectedIds = new Set(<?= json_encode($preselectedIds) ?>);
                        let selected  = allBooks.filter(b => preselectedIds.has(b.id));
                        let available = allBooks.filter(b => !preselectedIds.has(b.id));

                        function escH(s) {
                            return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
                        }
                        function renderPanels() {
                            const q = document.getElementById('book-search').value.toLowerCase();
                            const vis = available.filter(b =>
                                b.title.toLowerCase().includes(q) || b.author.toLowerCase().includes(q)
                            );
                            const countLabel = vis.length !== available.length
                                ? vis.length + '/' + available.length : available.length;
                            document.getElementById('avail-count').textContent = countLabel;
                            document.getElementById('selected-count').textContent = selected.length;

                            document.getElementById('available-panel').innerHTML = vis.length === 0
                                ? '<p class="dual-panel-empty">' + (q ? 'No results' : 'All books selected') + '</p>'
                                : vis.map(b => `<div class="dual-panel-item">
                                    <div class="dual-panel-item-text">
                                        <div class="dual-panel-item-title">${escH(b.title)}</div>
                                        <div class="dual-panel-item-author">${escH(b.author)}</div>
                                    </div>
                                    <button type="button" class="dual-panel-btn dual-panel-btn-add" onclick="addBook(${b.id})">Add →</button>
                                </div>`).join('');

                            document.getElementById('selected-panel').innerHTML = selected.length === 0
                                ? '<p class="dual-panel-empty">No books selected</p>'
                                : selected.map(b => `<div class="dual-panel-item dual-panel-item-selected">
                                    <div class="dual-panel-item-text">
                                        <div class="dual-panel-item-title">${escH(b.title)}</div>
                                        <div class="dual-panel-item-author">${escH(b.author)}</div>
                                    </div>
                                    <button type="button" class="dual-panel-btn dual-panel-btn-remove" onclick="removeBook(${b.id})">← Remove</button>
                                </div>`).join('');

                            document.getElementById('selected-inputs').innerHTML =
                                selected.map(b => `<input type="hidden" name="book_ids[]" value="${b.id}">`).join('');
                        }
                        function addBook(id) {
                            const i = available.findIndex(b => b.id === id);
                            if (i !== -1) { selected.push(available.splice(i, 1)[0]); renderPanels(); }
                        }
                        function removeBook(id) {
                            const i = selected.findIndex(b => b.id === id);
                            if (i !== -1) {
                                available.push(selected.splice(i, 1)[0]);
                                available.sort((a, b) => a.title.localeCompare(b.title));
                                renderPanels();
                            }
                        }
                        document.getElementById('book-search').addEventListener('input', renderPanels);
                        renderPanels();
                        </script>
                    <?php endif; ?>
                </div>
                <button type="submit" class="btn btn-primary">Create Bundle</button>
            </form>
        </div>

        <!-- Bundles table -->
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Code</th>
                        <th>Name</th>
                        <th>Books</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($bundles as $b): ?>
                        <tr>
                            <td style="color:var(--text2);font-size:12px"><?= $b['id'] ?></td>
                            <td><span class="code-badge" style="letter-spacing:1px"><?= htmlspecialchars($b['code']) ?></span></td>
                            <td style="font-weight:500"><?= htmlspecialchars($b['name']) ?></td>
                            <td style="text-align:center"><?= (int)$b['book_count'] ?></td>
                            <td style="color:var(--text2);font-size:12px"><?= date('M j, Y', strtotime($b['created_at'])) ?></td>
                            <td>
                                <form method="POST" style="display:inline"
                                      onsubmit="return confirm('Delete bundle \'<?= htmlspecialchars(addslashes($b['name'])) ?>\'? Users keep their books.')">
                                    <input type="hidden" name="action" value="delete_bundle">
                                    <input type="hidden" name="bundle_id" value="<?= $b['id'] ?>">
                                    <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($bundles)): ?>
                        <tr><td colspan="6" style="text-align:center;color:var(--text2);padding:32px">No bundles yet</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>
</div>

<script>
function regenerateCode() {
    const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    const arr = new Uint8Array(12);
    crypto.getRandomValues(arr);
    document.getElementById('bundle-code').value = Array.from(arr, v => chars[v % chars.length]).join('');
}
</script>
</body>
</html>
