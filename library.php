<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/includes/db.php';

$userId = (int) $_SESSION['user_id'];
$pdo    = getDB();

$stmt = $pdo->prepare(
    'SELECT ub.id, ub.title, ub.author, ub.cover_url, ub.genre, ub.file_type, ub.source, ub.added_at,
            COALESCE(rp.percentage, 0) AS percentage
     FROM user_books ub
     LEFT JOIN reading_progress rp ON rp.user_book_id = ub.id AND rp.user_id = ub.user_id
     WHERE ub.user_id = ?
     ORDER BY ub.added_at DESC'
);
$stmt->execute([$userId]);
$books = $stmt->fetchAll();

// Admins also see all store books
$storeBooks = [];
if (($_SESSION['role'] ?? '') === 'admin') {
    $storeBooks = $pdo->query(
        'SELECT id, title, author, cover_url, genre, file_type FROM store_books ORDER BY created_at DESC'
    )->fetchAll();
}

$pageTitle = 'Library';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ThgBook — Library</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,500;0,9..40,600;1,9..40,400&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
    <style>
        #meta-status { font-size:12px; padding:6px 10px; border-radius:var(--radius2); margin-top:8px; display:none; }
        #meta-status.is-loading { background:var(--surface2); color:var(--text2); }
        #meta-status.is-warn    { background:rgba(224,115,112,0.12); color:#e07370; }
        .autofill-badge { display:inline-block; font-size:10px; background:rgba(180,140,60,0.15); color:var(--accent); border-radius:4px; padding:1px 6px; margin-left:6px; font-weight:400; text-transform:none; letter-spacing:0; vertical-align:middle; }
    </style>
    <link rel="manifest" href="/tools/ThgBook/manifest.json">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="ThgBook">
    <link rel="apple-touch-icon" href="/tools/ThgBook/assets/img/icon-192.png">
</head>
<body>
<?php include __DIR__ . '/includes/header.php'; ?>

<main class="page">
    <?php if (empty($books) && empty($storeBooks)): ?>
        <div class="empty-state">
            <svg xmlns="http://www.w3.org/2000/svg" width="56" height="56" viewBox="0 0 24 24" fill="none"
                 stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/>
                <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/>
            </svg>
            <h3>Your library is empty</h3>
            <p>Upload your own EPUB or PDF, or redeem a store code.</p>
            <a href="<?= BASE_URL ?>/store.php" class="btn btn-primary">Browse Store</a>
        </div>
    <?php else: ?>
        <?php if (!empty($books)): ?>
        <div class="books-grid">
            <?php foreach ($books as $book): ?>
                <div class="book-card-wrapper" data-book-id="<?= $book['id'] ?>">
                    <div class="book-card" onclick="openBook(<?= $book['id'] ?>)">
                        <?php if ($book['cover_url']): ?>
                            <img src="<?= htmlspecialchars($book['cover_url']) ?>"
                                 alt="<?= htmlspecialchars($book['title']) ?>"
                                 class="book-cover"
                                 onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
                            <div class="book-cover-placeholder" style="display:none">
                                <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
                                <span><?= strtoupper($book['file_type']) ?></span>
                            </div>
                        <?php else: ?>
                            <div class="book-cover-placeholder">
                                <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
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
                    </div>
                    <button class="book-delete-btn" onclick="confirmDelete(<?= $book['id'] ?>)" aria-label="Remove book">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none"
                             stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="18" y1="6" x2="6" y2="18"/>
                            <line x1="6" y1="6" x2="18" y2="18"/>
                        </svg>
                    </button>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if (!empty($storeBooks)): ?>
        <div class="store-library-section">
            <div class="section-header" style="padding: 0 16px; margin-bottom: 12px">
                <h2 class="section-title">Store Library <span class="badge badge-admin" style="font-size:10px;vertical-align:middle;margin-left:6px">Admin</span></h2>
                <span style="font-size:13px;color:var(--text2)"><?= count($storeBooks) ?> books</span>
            </div>
            <div class="books-grid" style="padding:0 16px 24px">
                <?php foreach ($storeBooks as $sb): ?>
                    <a href="<?= BASE_URL ?>/reader.php?store_id=<?= $sb['id'] ?>" class="book-card" style="text-decoration:none">
                        <?php if ($sb['cover_url']): ?>
                            <img src="<?= htmlspecialchars($sb['cover_url']) ?>"
                                 alt="<?= htmlspecialchars($sb['title']) ?>"
                                 class="book-cover"
                                 onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
                            <div class="book-cover-placeholder" style="display:none">
                                <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
                            </div>
                        <?php else: ?>
                            <div class="book-cover-placeholder">
                                <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
                                <span><?= strtoupper($sb['file_type']) ?></span>
                            </div>
                        <?php endif; ?>
                        <div class="book-info">
                            <div class="book-title"><?= htmlspecialchars($sb['title']) ?></div>
                            <div class="book-author"><?= htmlspecialchars($sb['author']) ?></div>
                            <?php if ($sb['genre']): ?>
                                <div class="book-genre"><?= htmlspecialchars($sb['genre']) ?></div>
                            <?php endif; ?>
                            <span class="badge badge-store" style="margin-top:auto">STORE</span>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    <?php endif; ?>
</main>

<!-- Upload FAB -->
<button class="fab" onclick="document.getElementById('upload-modal').style.display='flex'" aria-label="Upload book">
    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
         stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
    </svg>
</button>

<!-- Upload Modal -->
<div id="upload-modal" class="modal-overlay" style="display:none" onclick="closeModal(event)">
    <div class="modal" onclick="event.stopPropagation()">
        <h2 class="modal-title">Upload a Book</h2>
        <div id="upload-alert"></div>
        <form id="upload-form" enctype="multipart/form-data">
            <div style="display:flex;flex-direction:column;gap:14px">
                <div class="upload-area" id="upload-area">
                    <input type="file" name="book_file" id="book-file" accept=".epub,.pdf" required>
                    <div class="upload-area-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                    </div>
                    <div class="upload-area-text" id="upload-area-text">
                        <strong>Choose file</strong> or drag & drop<br>
                        <small style="color:var(--text2)">EPUB or PDF · max 50 MB</small>
                    </div>
                </div>
                <div id="meta-status"></div>
                <div class="form-group">
                    <label for="upload-title">Title</label>
                    <input type="text" id="upload-title" name="title" placeholder="Book title" required>
                </div>
                <div class="form-group">
                    <label for="upload-author">Author</label>
                    <input type="text" id="upload-author" name="author" placeholder="Author name" required>
                </div>
                <div class="form-group">
                    <label for="upload-genre">Genre <span style="color:var(--text2);text-transform:none;font-size:11px">(optional)</span></label>
                    <input type="text" id="upload-genre" name="genre" placeholder="Fiction, Non-fiction…">
                </div>
                <div class="form-group">
                    <label for="upload-cover">Cover URL <span style="color:var(--text2);text-transform:none;font-size:11px">(optional)</span></label>
                    <input type="url" id="upload-cover" name="cover_url" placeholder="https://…">
                </div>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" onclick="document.getElementById('upload-modal').style.display='none'">Cancel</button>
                <button type="submit" class="btn btn-primary" id="upload-submit" style="flex:1">Upload</button>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/includes/tab_bar.php'; ?>
<script src="<?= BASE_URL ?>/assets/js/main.js"></script>
<script>
function openBook(id) { window.location.href = '<?= BASE_URL ?>/reader.php?id=' + id; }

function closeModal(e) {
    if (e.target.classList.contains('modal-overlay')) {
        e.target.style.display = 'none';
    }
}

// Drag-and-drop visual feedback
const uploadArea = document.getElementById('upload-area');
const fileInput  = document.getElementById('book-file');
const areaText   = document.getElementById('upload-area-text');

if (window.pdfjsLib) {
    pdfjsLib.GlobalWorkerOptions.workerSrc =
        'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
}

var metaStatus = document.getElementById('meta-status');
var UPLOAD_FIELDS = { title: 'upload-title', author: 'upload-author', genre: 'upload-genre' };

fileInput.addEventListener('change', function() {
    var file = fileInput.files[0];
    if (!file) return;
    areaText.innerHTML = '<strong>' + file.name + '</strong>';
    libExtractAndFill(file);
});

uploadArea.addEventListener('dragover', function(e) { e.preventDefault(); uploadArea.classList.add('drag-over'); });
uploadArea.addEventListener('dragleave', function() { uploadArea.classList.remove('drag-over'); });
uploadArea.addEventListener('drop', function(e) {
    e.preventDefault();
    uploadArea.classList.remove('drag-over');
    var file = e.dataTransfer.files[0];
    if (file) {
        fileInput.files = e.dataTransfer.files;
        areaText.innerHTML = '<strong>' + file.name + '</strong>';
        libExtractAndFill(file);
    }
});

async function libExtractAndFill(file) {
    var ext = file.name.split('.').pop().toLowerCase();
    libSetStatus('loading', 'Extracting metadata...');
    libRemoveBadges();

    var meta = null;
    if (ext === 'epub') {
        var fd = new FormData();
        fd.append('book_file', file);
        try {
            var r = await fetch('<?= BASE_URL ?>/api/extract_meta.php', { method: 'POST', body: fd });
            meta = await r.json();
        } catch (e) { meta = null; }
    } else if (ext === 'pdf') {
        if (window.pdfjsLib) {
            try {
                var buf = await file.arrayBuffer();
                var pdf = await pdfjsLib.getDocument({ data: buf }).promise;
                var m   = await pdf.getMetadata();
                var info = (m && m.info) ? m.info : {};
                meta = { title: info.Title || '', author: info.Author || '', genre: info.Subject || '' };
            } catch (e) { meta = null; }
        }
    }

    if (!meta || (!meta.title && !meta.author && !meta.genre)) {
        libSetStatus('warn', 'No metadata found — please fill in manually');
        return;
    }
    metaStatus.style.display = 'none';
    var filled = false;
    for (var key in UPLOAD_FIELDS) {
        var val = (meta[key] || '').trim();
        var el  = document.getElementById(UPLOAD_FIELDS[key]);
        if (val && el) {
            el.value = val;
            libAddBadge(el);
            filled = true;
        }
    }
    if (!filled) libSetStatus('warn', 'No metadata found — please fill in manually');
}

function libSetStatus(type, msg) {
    metaStatus.className = type === 'loading' ? 'is-loading' : 'is-warn';
    metaStatus.textContent = msg;
    metaStatus.style.display = 'block';
}

function libAddBadge(el) {
    var lbl = el.closest('.form-group').querySelector('label');
    if (lbl && !lbl.querySelector('.autofill-badge')) {
        var b = document.createElement('span');
        b.className = 'autofill-badge';
        b.textContent = 'Auto-filled';
        lbl.appendChild(b);
    }
}

function libRemoveBadges() {
    document.querySelectorAll('.autofill-badge').forEach(function(b) { b.remove(); });
}

document.getElementById('upload-form').addEventListener('submit', async e => {
    e.preventDefault();
    const alertEl = document.getElementById('upload-alert');
    alertEl.innerHTML = '';
    const btn = document.getElementById('upload-submit');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner"></span> Uploading…';

    const formData = new FormData(e.target);
    try {
        const r    = await fetch('<?= BASE_URL ?>/api/upload_book.php', { method: 'POST', body: formData });
        const data = await r.json();
        if (data.success) {
            showToast('Book added to library!', 'success');
            setTimeout(() => location.reload(), 800);
        } else {
            alertEl.innerHTML = '<div class="alert alert-error">' + (data.error || 'Upload failed') + '</div>';
            btn.disabled = false;
            btn.textContent = 'Upload';
        }
    } catch {
        alertEl.innerHTML = '<div class="alert alert-error">Network error</div>';
        btn.disabled = false;
        btn.textContent = 'Upload';
    }
});
</script>
</body>
</html>
