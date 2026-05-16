<?php
require_once __DIR__ . '/../includes/admin_check.php';
require_once __DIR__ . '/../includes/db.php';

$success = null;
$error   = null;
$newCode = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title       = trim($_POST['title']       ?? '');
    $author      = trim($_POST['author']      ?? '');
    $genre       = trim($_POST['genre']       ?? '');
    $description = trim($_POST['description'] ?? '');
    $coverUrl    = trim($_POST['cover_url']   ?? '');

    if (!$title || !$author) {
        $error = 'Title and author are required.';
    } elseif (empty($_FILES['book_file']) || $_FILES['book_file']['error'] !== UPLOAD_ERR_OK) {
        $errCode = $_FILES['book_file']['error'] ?? -1;
        $error   = ($errCode === UPLOAD_ERR_INI_SIZE || $errCode === UPLOAD_ERR_FORM_SIZE)
            ? 'File exceeds the 50 MB limit.'
            : 'File upload failed (code ' . $errCode . ').';
    } else {
        $maxBytes     = 50 * 1024 * 1024;
        $allowedMimes = ['application/epub+zip' => 'epub', 'application/pdf' => 'pdf'];

        if ($_FILES['book_file']['size'] > $maxBytes) {
            $error = 'File exceeds the 50 MB limit.';
        } else {
            $finfo    = new finfo(FILEINFO_MIME_TYPE);
            $mimeType = $finfo->file($_FILES['book_file']['tmp_name']);

            if (!isset($allowedMimes[$mimeType])) {
                $error = 'Only EPUB and PDF files are accepted.';
            } else {
                $fileType = $allowedMimes[$mimeType];
                $ext      = '.' . $fileType;
                $filename = bin2hex(random_bytes(12)) . $ext;
                $destDir  = __DIR__ . '/../uploads/store/';
                $dest     = $destDir . $filename;

                if (!is_dir($destDir)) {
                    mkdir($destDir, 0755, true);
                }

                if (!move_uploaded_file($_FILES['book_file']['tmp_name'], $dest)) {
                    $error = 'Failed to save file.';
                } else {
                    $filePath = 'uploads/store/' . $filename;
                    $pdo      = getDB();

                    // Generate unique 8-char redeem code
                    do {
                        $code     = strtoupper(bin2hex(random_bytes(4)));
                        $existing = $pdo->prepare('SELECT id FROM store_books WHERE redeem_code = ?');
                        $existing->execute([$code]);
                    } while ($existing->fetch());

                    if ($coverUrl && !filter_var($coverUrl, FILTER_VALIDATE_URL)) {
                        $coverUrl = '';
                    }

                    $stmt = $pdo->prepare(
                        'INSERT INTO store_books (title, author, cover_url, description, genre, file_path, file_type, redeem_code)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
                    );
                    $stmt->execute([$title, $author, $coverUrl, $description, $genre, $filePath, $fileType, $code]);

                    $success = true;
                    $newCode = $code;
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ThgBook Admin — Upload Book</title>
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
</head>
<body>
<div class="admin-layout">
    <?php include __DIR__ . '/sidebar.php'; ?>

    <main class="admin-main">
        <div class="admin-header">
            <h1 class="admin-title">Upload Book</h1>
            <p class="admin-subtitle">Add a new book to the store and generate a redeem code</p>
        </div>

        <?php if ($success): ?>
            <div class="alert alert-success" style="margin-bottom:24px;font-size:16px">
                Book uploaded successfully!<br>
                Redeem code: <strong style="font-family:monospace;font-size:20px;letter-spacing:0.15em;color:var(--accent)"><?= htmlspecialchars($newCode) ?></strong>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-error" style="margin-bottom:24px"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form class="upload-form" method="POST" enctype="multipart/form-data">

            <div class="upload-area" id="upload-area">
                <input type="file" name="book_file" id="book-file" accept=".epub,.pdf" required>
                <div class="upload-area-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24" fill="none"
                         stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                        <polyline points="17 8 12 3 7 8"/>
                        <line x1="12" y1="3" x2="12" y2="15"/>
                    </svg>
                </div>
                <div class="upload-area-text" id="upload-label">
                    <strong>Choose EPUB or PDF</strong> or drag & drop<br>
                    <small style="color:var(--text2)">Max 50 MB</small>
                </div>
            </div>
            <div id="meta-status"></div>

            <div class="form-group">
                <label for="title">Title</label>
                <input type="text" id="title" name="title" placeholder="Book title" required
                       value="<?= htmlspecialchars($_POST['title'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label for="author">Author</label>
                <input type="text" id="author" name="author" placeholder="Author name" required
                       value="<?= htmlspecialchars($_POST['author'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label for="genre">Genre <span style="color:var(--text2);text-transform:none;font-size:11px">(optional)</span></label>
                <input type="text" id="genre" name="genre" placeholder="Fiction, Science, History…"
                       value="<?= htmlspecialchars($_POST['genre'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label for="description">Description <span style="color:var(--text2);text-transform:none;font-size:11px">(optional)</span></label>
                <textarea id="description" name="description" placeholder="Brief description of the book…" rows="4"><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
            </div>

            <div class="form-group">
                <label for="cover_url">Cover Image URL <span style="color:var(--text2);text-transform:none;font-size:11px">(optional)</span></label>
                <input type="url" id="cover_url" name="cover_url" placeholder="https://example.com/cover.jpg"
                       value="<?= htmlspecialchars($_POST['cover_url'] ?? '') ?>">
            </div>

            <button type="submit" class="btn btn-primary" style="align-self:flex-start;padding:14px 32px">
                Upload & Generate Code
            </button>
        </form>
    </main>
</div>

<script>
// BASE_URL declared by sidebar.php
if (window.pdfjsLib) {
    pdfjsLib.GlobalWorkerOptions.workerSrc =
        'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
}

var area  = document.getElementById('upload-area');
var input = document.getElementById('book-file');
var label = document.getElementById('upload-label');

var FIELDS = { title: 'title', author: 'author', genre: 'genre', description: 'description' };

input.addEventListener('change', function() {
    var file = input.files[0];
    if (!file) return;
    label.innerHTML = '<strong>' + file.name + '</strong>';
    extractAndFill(file);
});
area.addEventListener('dragover', function(e) { e.preventDefault(); area.classList.add('drag-over'); });
area.addEventListener('dragleave', function() { area.classList.remove('drag-over'); });
area.addEventListener('drop', function(e) {
    e.preventDefault();
    area.classList.remove('drag-over');
    var file = e.dataTransfer.files[0];
    if (file) {
        input.files = e.dataTransfer.files;
        label.innerHTML = '<strong>' + file.name + '</strong>';
        extractAndFill(file);
    }
});

async function extractAndFill(file) {
    var ext = file.name.split('.').pop().toLowerCase();
    setStatus('loading', 'Extracting metadata...');
    removeBadges();

    var meta = null;
    if (ext === 'epub') {
        meta = await extractEpubMeta(file);
    } else if (ext === 'pdf') {
        meta = await extractPdfMeta(file);
    } else {
        setStatus('hide', '');
        return;
    }

    if (!meta || (!meta.title && !meta.author && !meta.genre && !meta.description)) {
        setStatus('warn', 'No metadata found — please fill in manually');
        return;
    }
    setStatus('hide', '');
    fillFields(meta);
}

async function extractEpubMeta(file) {
    var fd = new FormData();
    fd.append('book_file', file);
    try {
        var r = await fetch(BASE_URL + '/api/extract_meta.php', { method: 'POST', body: fd });
        return await r.json();
    } catch (e) { return null; }
}

async function extractPdfMeta(file) {
    if (!window.pdfjsLib) return null;
    try {
        var buf = await file.arrayBuffer();
        var pdf = await pdfjsLib.getDocument({ data: buf }).promise;
        var m   = await pdf.getMetadata();
        var info = (m && m.info) ? m.info : {};
        return { title: info.Title || '', author: info.Author || '', genre: info.Subject || '', description: '' };
    } catch (e) { return null; }
}

function fillFields(meta) {
    var filled = false;
    for (var key in FIELDS) {
        var val = (meta[key] || '').trim();
        var el  = document.getElementById(FIELDS[key]);
        if (val && el) {
            el.value = val;
            addBadge(el);
            filled = true;
        }
    }
    if (!filled) {
        setStatus('warn', 'No metadata found — please fill in manually');
    }
}

function setStatus(type, msg) {
    var el = document.getElementById('meta-status');
    if (!el) return;
    if (type === 'hide') { el.style.display = 'none'; return; }
    el.className = type === 'loading' ? 'is-loading' : 'is-warn';
    el.textContent = msg;
    el.style.display = 'block';
}

function addBadge(el) {
    var lbl = el.closest('.form-group').querySelector('label');
    if (lbl && !lbl.querySelector('.autofill-badge')) {
        var b = document.createElement('span');
        b.className = 'autofill-badge';
        b.textContent = 'Auto-filled';
        lbl.appendChild(b);
    }
}

function removeBadges() {
    document.querySelectorAll('.autofill-badge').forEach(function(b) { b.remove(); });
}
</script>
</body>
</html>
