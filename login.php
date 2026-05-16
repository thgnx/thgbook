<?php
session_start();
require_once __DIR__ . '/includes/db.php';
if (!empty($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . '/home.php');
    exit;
}
$tab = $_GET['tab'] ?? 'login';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ThgBook — Sign In</title>
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
<div class="auth-page">
    <div class="auth-card">
        <div class="auth-logo">ThgBook</div>
        <p class="auth-subtitle">Your personal reading sanctuary</p>

        <div class="auth-tabs">
            <button class="auth-tab <?= $tab === 'login' ? 'active' : '' ?>" data-tab="login">Sign In</button>
            <button class="auth-tab <?= $tab === 'register' ? 'active' : '' ?>" data-tab="register">Create Account</button>
        </div>

        <div id="alert-box"></div>

        <!-- Login form -->
        <form class="auth-form" id="form-login" style="<?= $tab !== 'login' ? 'display:none' : '' ?>">
            <div class="form-group">
                <label for="login-email">Email</label>
                <input type="email" id="login-email" name="email" placeholder="you@example.com" required autocomplete="email">
            </div>
            <div class="form-group">
                <label for="login-password">Password</label>
                <input type="password" id="login-password" name="password" placeholder="••••••••" required autocomplete="current-password">
            </div>
            <button type="submit" class="btn btn-primary btn-full">Sign In</button>
        </form>

        <!-- Register form -->
        <form class="auth-form" id="form-register" style="<?= $tab !== 'register' ? 'display:none' : '' ?>">
            <div class="form-group">
                <label for="reg-username">Username</label>
                <input type="text" id="reg-username" name="username" placeholder="readerlord" required minlength="3" maxlength="50" autocomplete="username">
            </div>
            <div class="form-group">
                <label for="reg-email">Email</label>
                <input type="email" id="reg-email" name="email" placeholder="you@example.com" required autocomplete="email">
            </div>
            <div class="form-group">
                <label for="reg-password">Password <span style="color:var(--text2);font-size:11px;text-transform:none">(min 8 chars)</span></label>
                <input type="password" id="reg-password" name="password" placeholder="••••••••" required minlength="8" autocomplete="new-password">
            </div>
            <button type="submit" class="btn btn-primary btn-full">Create Account</button>
        </form>
    </div>
</div>

<script>
(function () {
    const tabs      = document.querySelectorAll('.auth-tab');
    const formLogin = document.getElementById('form-login');
    const formReg   = document.getElementById('form-register');
    const alertBox  = document.getElementById('alert-box');

    function showAlert(msg, type) {
        alertBox.innerHTML = `<div class="alert alert-${type}">${msg}</div>`;
    }
    function clearAlert() { alertBox.innerHTML = ''; }

    tabs.forEach(tab => {
        tab.addEventListener('click', () => {
            tabs.forEach(t => t.classList.remove('active'));
            tab.classList.add('active');
            clearAlert();
            if (tab.dataset.tab === 'login') {
                formLogin.style.display = '';
                formReg.style.display   = 'none';
            } else {
                formLogin.style.display = 'none';
                formReg.style.display   = '';
            }
        });
    });

    async function post(url, data) {
        const r = await fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
        return r.json();
    }

    formLogin.addEventListener('submit', async e => {
        e.preventDefault();
        clearAlert();
        const btn = formLogin.querySelector('button[type=submit]');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner"></span>';
        const data = await post('<?= BASE_URL ?>/api/auth.php', {
            action:   'login',
            email:    document.getElementById('login-email').value,
            password: document.getElementById('login-password').value
        });
        if (data.success) {
            window.location.href = '<?= BASE_URL ?>/home.php';
        } else {
            showAlert(data.error || 'Login failed', 'error');
            btn.disabled = false;
            btn.textContent = 'Sign In';
        }
    });

    formReg.addEventListener('submit', async e => {
        e.preventDefault();
        clearAlert();
        const btn = formReg.querySelector('button[type=submit]');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner"></span>';
        const data = await post('<?= BASE_URL ?>/api/auth.php', {
            action:   'register',
            username: document.getElementById('reg-username').value,
            email:    document.getElementById('reg-email').value,
            password: document.getElementById('reg-password').value
        });
        if (data.success) {
            window.location.href = '<?= BASE_URL ?>/home.php';
        } else {
            showAlert(data.error || 'Registration failed', 'error');
            btn.disabled = false;
            btn.textContent = 'Create Account';
        }
    });
}());
</script>
</body>
</html>
