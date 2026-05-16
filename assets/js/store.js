/**
 * ThgBook — Store: redeem code logic + book preview modal
 */
(function () {
    const codeInput = document.getElementById('redeem-code');
    const btn       = document.getElementById('redeem-btn');
    const alertEl   = document.getElementById('redeem-alert');

    if (!codeInput || !btn) return;

    // Auto-uppercase & strip non-alphanum
    codeInput.addEventListener('input', () => {
        const raw = codeInput.value.toUpperCase().replace(/[^A-Z0-9]/g, '');
        if (codeInput.value !== raw) codeInput.value = raw;
    });

    codeInput.addEventListener('keydown', e => {
        if (e.key === 'Enter') redeemCode();
    });

    // Close modal on Escape
    document.addEventListener('keydown', e => {
        if (e.key === 'Escape') closeBookPreview();
    });

    window.redeemCode = async function () {
        const code = codeInput.value.trim().toUpperCase();
        alertEl.innerHTML = '';

        if (code.length !== 8 && code.length !== 12) {
            alertEl.innerHTML = '<div class="alert alert-error">Please enter a valid 8 or 12-character code.</div>';
            codeInput.focus();
            return;
        }

        btn.disabled  = true;
        btn.innerHTML = '<span class="spinner"></span>';

        try {
            const r    = await fetch(BASE_URL + '/api/redeem.php', {
                method:  'POST',
                headers: { 'Content-Type': 'application/json' },
                body:    JSON.stringify({ code })
            });
            const data = await r.json();

            if (data.success && data.bundle) {
                const n = data.added;
                alertEl.innerHTML = '<div class=”alert alert-success”>' + n + ' book' + (n !== 1 ? 's' : '') + ' from “' + escHtml(data.title) + '” added to your library!</div>';
                codeInput.value = '';
            } else if (data.success) {
                alertEl.innerHTML = '<div class=”alert alert-success”>”' + escHtml(data.title) + '” added to your library!</div>';
                codeInput.value   = '';
                openBookPreview({
                    title:        data.title,
                    author:       data.author       || '',
                    cover_url:    data.cover_url    || '',
                    genre:        data.genre        || '',
                    description:  data.description  || '',
                    user_book_id: data.user_book_id || 0,
                });
            } else {
                alertEl.innerHTML = '<div class="alert alert-error">' + escHtml(data.error || 'Invalid code') + '</div>';
            }
        } catch (_) {
            alertEl.innerHTML = '<div class="alert alert-error">Network error. Please try again.</div>';
        } finally {
            btn.disabled    = false;
            btn.textContent = 'Redeem';
        }
    };

    window.openBookPreview = function (data) {
        const modal      = document.getElementById('book-preview-modal');
        const coverImg   = document.getElementById('preview-cover');
        const coverPh    = document.getElementById('preview-cover-ph');
        const readBtn    = document.getElementById('preview-read-btn');

        document.getElementById('preview-title').textContent  = data.title  || '';
        document.getElementById('preview-author').textContent = data.author || '';

        const genreEl = document.getElementById('preview-genre');
        if (data.genre) {
            genreEl.textContent    = data.genre;
            genreEl.style.display  = 'inline-block';
        } else {
            genreEl.style.display  = 'none';
        }

        const descEl = document.getElementById('preview-desc');
        descEl.textContent    = data.description || '';
        descEl.style.display  = data.description ? '' : 'none';

        if (data.cover_url) {
            coverImg.src           = data.cover_url;
            coverImg.style.display = 'block';
            if (coverPh) coverPh.style.display = 'none';
        } else {
            coverImg.style.display = 'none';
            if (coverPh) coverPh.style.display = 'flex';
        }

        if (data.user_book_id) {
            readBtn.href             = BASE_URL + '/reader.php?id=' + data.user_book_id;
            readBtn.style.display    = '';
        } else {
            readBtn.style.display    = 'none';
        }

        if (modal) modal.classList.add('open');
    };

    window.closeBookPreview = function () {
        document.getElementById('book-preview-modal')?.classList.remove('open');
    };

    function escHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }
}());
