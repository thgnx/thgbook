/**
 * ThgBook — shared utilities
 */
const BASE_URL = '/tools/ThgBook';

function showToast(message, type = 'info') {
    document.querySelectorAll('.toast').forEach(t => t.remove());

    const toast = document.createElement('div');
    toast.className = 'toast' + (type !== 'info' ? ' ' + type : '');
    toast.textContent = message;
    document.body.appendChild(toast);

    setTimeout(() => {
        toast.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
        toast.style.opacity    = '0';
        toast.style.transform  = 'translateX(-50%) translateY(8px)';
        setTimeout(() => toast.remove(), 320);
    }, 2800);
}

function confirmDelete(bookId) {
    if (!confirm('Remove this book from your library?')) return;

    fetch(BASE_URL + '/api/delete_book.php', {
        method:  'POST',
        headers: { 'Content-Type': 'application/json' },
        body:    JSON.stringify({ book_id: bookId })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            const wrapper = document.querySelector('[data-book-id="' + bookId + '"]');
            if (wrapper) {
                wrapper.style.transition = 'opacity 0.3s, transform 0.3s';
                wrapper.style.opacity    = '0';
                wrapper.style.transform  = 'scale(0.95)';
                setTimeout(() => wrapper.remove(), 320);
            }
            showToast('Book removed', 'success');
        } else {
            showToast(data.error || 'Delete failed', 'error');
        }
    })
    .catch(() => showToast('Network error', 'error'));
}

// Logout helper (used by admin sidebar)
async function logout() {
    try {
        await fetch(BASE_URL + '/api/auth.php', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify({ action: 'logout' })
        });
    } catch (_) {}
    window.location.href = BASE_URL + '/login.php';
}
