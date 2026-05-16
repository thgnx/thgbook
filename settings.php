<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/includes/db.php';

$userId = (int) $_SESSION['user_id'];
$pdo    = getDB();

$stmt = $pdo->prepare('SELECT username, email FROM users WHERE id = ?');
$stmt->execute([$userId]);
$user = $stmt->fetch();

$pageTitle = 'Settings';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ThgBook — Settings</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,500;0,9..40,600;1,9..40,400&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
    <link rel="manifest" href="/tools/ThgBook/manifest.json">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="ThgBook">
    <link rel="apple-touch-icon" href="/tools/ThgBook/assets/img/icon-192.png">
</head>
<body>
<?php include __DIR__ . '/includes/header.php'; ?>

<main class="page">
    <div class="content">

        <!-- Profile section -->
        <div class="settings-section">
            <h2 class="settings-section-title">Profile</h2>
            <div id="profile-alert"></div>
            <form id="profile-form">
                <div class="form-group">
                    <label for="s-username">Username</label>
                    <input type="text" id="s-username" name="username"
                           value="<?= htmlspecialchars($user['username']) ?>"
                           minlength="3" maxlength="50" required>
                </div>
                <div class="form-group">
                    <label for="s-email">Email</label>
                    <input type="email" id="s-email" name="email"
                           value="<?= htmlspecialchars($user['email']) ?>"
                           required>
                </div>
                <button type="submit" class="btn btn-primary" id="profile-btn">Save Changes</button>
            </form>
        </div>

        <!-- Password section -->
        <div class="settings-section">
            <h2 class="settings-section-title">Change Password</h2>
            <div id="password-alert"></div>
            <form id="password-form">
                <div class="form-group">
                    <label for="s-current">Current Password</label>
                    <input type="password" id="s-current" name="current_password"
                           placeholder="••••••••" required autocomplete="current-password">
                </div>
                <div class="form-group">
                    <label for="s-new">New Password <span style="color:var(--text2);font-size:11px;text-transform:none">(min 8 chars)</span></label>
                    <input type="password" id="s-new" name="new_password"
                           placeholder="••••••••" minlength="8" required autocomplete="new-password">
                </div>
                <div class="form-group">
                    <label for="s-confirm">Confirm New Password</label>
                    <input type="password" id="s-confirm" name="confirm_password"
                           placeholder="••••••••" required autocomplete="new-password">
                </div>
                <button type="submit" class="btn btn-primary" id="password-btn">Update Password</button>
            </form>
        </div>

        <!-- Danger zone -->
        <div class="settings-section settings-danger">
            <h2 class="settings-section-title" style="color:#e07370">Danger Zone</h2>
            <p style="font-size:13px;color:var(--text2);margin-bottom:16px">
                Permanently delete your account and all associated books and reading progress. This cannot be undone.
            </p>
            <div id="delete-alert"></div>
            <button class="btn btn-danger" onclick="document.getElementById('delete-confirm').style.display='block';this.style.display='none'">
                Delete My Account
            </button>
            <div id="delete-confirm" style="display:none">
                <form id="delete-form">
                    <div class="form-group" style="margin-top:12px">
                        <label for="s-del-pw">Enter your password to confirm</label>
                        <input type="password" id="s-del-pw" name="password" placeholder="••••••••" required>
                    </div>
                    <div style="display:flex;gap:10px">
                        <button type="submit" class="btn btn-danger" id="delete-btn">Yes, Delete Everything</button>
                        <button type="button" class="btn btn-secondary"
                                onclick="document.getElementById('delete-confirm').style.display='none';document.querySelector('.btn-danger').style.display=''">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>

    </div>
</main>

<?php include __DIR__ . '/includes/tab_bar.php'; ?>
<script src="<?= BASE_URL ?>/assets/js/main.js"></script>
<script>
(function () {
    async function post(action, data) {
        const r = await fetch(BASE_URL + '/api/update_settings.php', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify({ action, ...data })
        });
        return r.json();
    }

    function setAlert(id, msg, type) {
        document.getElementById(id).innerHTML =
            msg ? '<div class="alert alert-' + type + '">' + msg + '</div>' : '';
    }

    function setBusy(btn, busy, label) {
        btn.disabled = busy;
        btn.innerHTML = busy ? '<span class="spinner"></span>' : label;
    }

    // Profile form
    document.getElementById('profile-form').addEventListener('submit', async e => {
        e.preventDefault();
        const btn = document.getElementById('profile-btn');
        setAlert('profile-alert', '', '');
        setBusy(btn, true, '');
        const data = await post('update_profile', {
            username: document.getElementById('s-username').value,
            email:    document.getElementById('s-email').value
        });
        setBusy(btn, false, 'Save Changes');
        if (data.success) {
            setAlert('profile-alert', 'Profile updated.', 'success');
        } else {
            setAlert('profile-alert', data.error || 'Failed', 'error');
        }
    });

    // Password form
    document.getElementById('password-form').addEventListener('submit', async e => {
        e.preventDefault();
        const btn = document.getElementById('password-btn');
        setAlert('password-alert', '', '');
        setBusy(btn, true, '');
        const data = await post('update_password', {
            current_password: document.getElementById('s-current').value,
            new_password:     document.getElementById('s-new').value,
            confirm_password: document.getElementById('s-confirm').value
        });
        setBusy(btn, false, 'Update Password');
        if (data.success) {
            setAlert('password-alert', 'Password updated.', 'success');
            e.target.reset();
        } else {
            setAlert('password-alert', data.error || 'Failed', 'error');
        }
    });

    // Delete account form
    document.getElementById('delete-form').addEventListener('submit', async e => {
        e.preventDefault();
        if (!confirm('Are you absolutely sure? This cannot be undone.')) return;
        const btn = document.getElementById('delete-btn');
        setAlert('delete-alert', '', '');
        setBusy(btn, true, '');
        const data = await post('delete_account', {
            password: document.getElementById('s-del-pw').value
        });
        if (data.success) {
            window.location.href = BASE_URL + '/login.php';
        } else {
            setBusy(btn, false, 'Yes, Delete Everything');
            setAlert('delete-alert', data.error || 'Failed', 'error');
        }
    });
}());
</script>
</body>
</html>
