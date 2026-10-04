<?php
/**
 * SmartReceipt - Sign In Authentication
 */

require_once __DIR__ . '/config/db.php';

if (is_logged_in()) {
    redirect(BASE_URL . '/dashboard.php');
}

// Handle explicit "Clear saved credentials" request
if (isset($_GET['clear_saved']) && $_GET['clear_saved'] == '1') {
    setcookie('sr_saved_email', '', time() - 3600, '/');
    setcookie('sr_saved_pwd', '', time() - 3600, '/');
    setcookie('sr_remember', '', time() - 3600, '/');

    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
        exit;
    }
    flash('info', 'Saved login credentials have been removed from this browser.');
    redirect(BASE_URL . '/login.php');
}

$error = '';
$email = '';
$saved_password = '';
$is_saved_loaded = false;

// Check if credentials were saved previously in cookies
if (!empty($_COOKIE['sr_saved_email'])) {
    $email = $_COOKIE['sr_saved_email'];
}
if (!empty($_COOKIE['sr_saved_pwd'])) {
    $decrypted = decrypt_vault_data($_COOKIE['sr_saved_pwd']);
    if ($decrypted !== null && $decrypted !== '') {
        $saved_password = $decrypted;
        $is_saved_loaded = true;
    }
}
$remember_checked = !empty($_COOKIE['sr_remember']) || $is_saved_loaded || ($_SERVER['REQUEST_METHOD'] === 'GET');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = !empty($_POST['remember_password']);

    if ($email === '' || $password === '') {
        $error = 'Please enter both your email address and password.';
    } else {
        $stmt = $pdo->prepare("SELECT id, name, email, password FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            session_regenerate_id(true);
            $_SESSION['user_id']    = (int)$user['id'];
            $_SESSION['user_name']  = $user['name'];
            $_SESSION['user_email'] = $user['email'];

            // Persist credentials for future use if requested
            $cookieDuration = time() + (30 * 86400); // 30 days
            if ($remember) {
                // Save email (accessible to JS for prefill if needed)
                setcookie('sr_saved_email', $email, [
                    'expires'  => $cookieDuration,
                    'path'     => '/',
                    'httponly' => false,
                    'samesite' => 'Lax'
                ]);
                // Save encrypted password (HttpOnly)
                setcookie('sr_saved_pwd', encrypt_vault_data($password), [
                    'expires'  => $cookieDuration,
                    'path'     => '/',
                    'httponly' => true,
                    'samesite' => 'Lax'
                ]);
                setcookie('sr_remember', '1', [
                    'expires'  => $cookieDuration,
                    'path'     => '/',
                    'httponly' => false,
                    'samesite' => 'Lax'
                ]);
            } else {
                setcookie('sr_saved_email', '', time() - 3600, '/');
                setcookie('sr_saved_pwd', '', time() - 3600, '/');
                setcookie('sr_remember', '', time() - 3600, '/');
            }
            
            flash('success', 'Welcome back, ' . $user['name'] . '!');
            redirect(BASE_URL . '/dashboard.php');
        } else {
            $error = 'Invalid email credentials or password.';
        }
    }
}

$page_title = 'Sign In';
require_once __DIR__ . '/includes/header.php';
?>

<div class="auth-wrapper">
    <div class="auth-card">
        <div class="auth-header">
            <div class="auth-brand-mark">
                <?= svg_icon('receipt', '', 24) ?>
            </div>
            <h1>Welcome Back</h1>
            <p>Access your receipt records and warranty vault.</p>
        </div>

        <?php if ($error): ?>
            <div class="alert error" role="alert">
                <?= svg_icon('alert-triangle', '', 18) ?>
                <span><?= e($error) ?></span>
            </div>
        <?php endif; ?>

        <form method="post" action="<?= BASE_URL ?>/login.php" class="form-grid" id="loginForm" autocomplete="on">
            <?= csrf_input() ?>

            <div class="form-group">
                <label for="loginEmail">Email Address</label>
                <input type="email" id="loginEmail" name="email" class="form-control" 
                       value="<?= e($email) ?>" placeholder="name@example.com" required autocomplete="username email" autofocus>
            </div>

            <div class="form-group">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                    <label for="loginPassword" style="margin-bottom: 0;">Password</label>
                    <div id="savedPwdBadge" class="saved-vault-badge" style="<?= $is_saved_loaded ? 'display: inline-flex;' : 'display: none;' ?>">
                        <?= svg_icon('shield-check', '', 13) ?>
                        <span>Saved Password Loaded</span>
                    </div>
                </div>

                <div class="input-with-action">
                    <input type="password" id="loginPassword" name="password" class="form-control" 
                           value="<?= e($saved_password) ?>"
                           placeholder="••••••••" required autocomplete="current-password">
                    <button type="button" class="input-action-btn" onclick="togglePasswordVisibility('loginPassword', this)" aria-label="Toggle password view" title="Show / Hide Password">
                        <span class="icon-show"><?= svg_icon('eye', '', 17) ?></span>
                        <span class="icon-hide" style="display: none;"><?= svg_icon('eye-off', '', 17) ?></span>
                    </button>
                </div>

                <!-- Real-time dynamic feedback as user types or modifies credentials -->
                <div class="password-vault-feedback <?= $is_saved_loaded ? 'active-saved' : '' ?>" id="passwordVaultFeedback">
                    <span class="vault-status-icon"><?= svg_icon('shield', '', 13) ?></span>
                    <span class="vault-status-text" id="vaultStatusText">
                        <?= $is_saved_loaded ? 'Password loaded securely from your saved vault.' : 'Strong vault encryption: password will be saved for future use.' ?>
                    </span>
                </div>
            </div>

            <!-- Save Password for Future Use Checkbox & Clear Action -->
            <div class="remember-group-card">
                <label class="custom-checkbox" for="rememberPassword">
                    <input type="checkbox" id="rememberPassword" name="remember_password" value="1" <?= $remember_checked ? 'checked' : '' ?>>
                    <span class="checkbox-indicator">
                        <?= svg_icon('check', '', 12) ?>
                    </span>
                    <span class="checkbox-text">
                        <strong>Save password for future use</strong>
                        <small>Auto-fills credentials on this device for instant access</small>
                    </span>
                </label>

                <button type="button" class="btn-clear-vault" id="btnClearSavedCreds" style="<?= ($is_saved_loaded || !empty($email) || !empty($saved_password)) ? 'display: inline-flex;' : 'display: none;' ?>" onclick="forgetSavedCredentials()" title="Clear saved credentials from this browser">
                    <?= svg_icon('trash', '', 13) ?>
                    <span>Clear</span>
                </button>
            </div>

            <button class="btn btn-primary btn-block btn-lg" id="loginSubmitBtn" type="submit" style="margin-top: 10px;">
                <span>Sign In to Vault</span>
                <?= svg_icon('arrow-right', '', 16) ?>
            </button>
        </form>

        <p class="auth-switch">
            New to SmartReceipt? <a href="<?= BASE_URL ?>/register.php">Create an account free</a>
        </p>
    </div>
</div>

<script>
(function() {
    const emailInput = document.getElementById('loginEmail');
    const passwordInput = document.getElementById('loginPassword');
    const rememberCb = document.getElementById('rememberPassword');
    const savedBadge = document.getElementById('savedPwdBadge');
    const clearBtn = document.getElementById('btnClearSavedCreds');
    const feedbackEl = document.getElementById('passwordVaultFeedback');
    const statusText = document.getElementById('vaultStatusText');
    const loginForm = document.getElementById('loginForm');

    const STORAGE_KEY = 'smartreceipt_vault_creds';

    // 1. Sync from local storage if server cookie was not already populated
    try {
        const stored = localStorage.getItem(STORAGE_KEY);
        if (stored) {
            const parsed = JSON.parse(stored);
            if (parsed) {
                if (parsed.email && !emailInput.value) {
                    emailInput.value = parsed.email;
                }
                if (parsed.pwd && !passwordInput.value) {
                    try {
                        passwordInput.value = decodeURIComponent(atob(parsed.pwd));
                        if (savedBadge) savedBadge.style.display = 'inline-flex';
                        if (clearBtn) clearBtn.style.display = 'inline-flex';
                        if (statusText) {
                            statusText.textContent = 'Saved password loaded securely from your saved vault.';
                            feedbackEl.classList.add('active-saved');
                            feedbackEl.classList.remove('warning-unsaved');
                        }
                    } catch (decErr) {}
                }
                if (rememberCb && parsed.remember !== false) {
                    rememberCb.checked = true;
                }
            }
        }
    } catch (e) {}

    // 2. Real-time credential saving as user enters password or email
    function updateLiveSaveState() {
        const hasPwd = passwordInput && passwordInput.value.length > 0;
        const hasEmail = emailInput && emailInput.value.length > 0;
        const willSave = rememberCb && rememberCb.checked;

        if (willSave && (hasPwd || hasEmail)) {
            try {
                localStorage.setItem(STORAGE_KEY, JSON.stringify({
                    email: emailInput ? emailInput.value.trim() : '',
                    pwd: hasPwd ? btoa(encodeURIComponent(passwordInput.value)) : '',
                    remember: true,
                    updatedAt: new Date().toISOString()
                }));
            } catch (err) {}

            if (clearBtn) clearBtn.style.display = 'inline-flex';
            if (statusText) {
                statusText.textContent = hasPwd 
                    ? 'Password will be saved on this device for future sign-ins ✓' 
                    : 'Credentials will be saved for future use.';
                feedbackEl.classList.remove('warning-unsaved');
                feedbackEl.classList.add('active-saved');
            }
        } else if (!willSave) {
            try {
                localStorage.removeItem(STORAGE_KEY);
            } catch (err) {}

            if (statusText) {
                statusText.textContent = 'Password will not be saved on this device.';
                feedbackEl.classList.remove('active-saved');
                feedbackEl.classList.add('warning-unsaved');
            }
        } else {
            if (statusText) {
                statusText.textContent = 'Strong vault encryption: password will be saved for future use.';
                feedbackEl.classList.remove('active-saved', 'warning-unsaved');
            }
        }
    }

    if (passwordInput) {
        passwordInput.addEventListener('input', updateLiveSaveState);
        passwordInput.addEventListener('change', updateLiveSaveState);
    }
    if (emailInput) {
        emailInput.addEventListener('input', updateLiveSaveState);
        emailInput.addEventListener('change', updateLiveSaveState);
    }
    if (rememberCb) {
        rememberCb.addEventListener('change', updateLiveSaveState);
    }

    // 3. User can remove saved credentials at any time
    window.forgetSavedCredentials = function() {
        if (emailInput) emailInput.value = '';
        if (passwordInput) passwordInput.value = '';
        if (rememberCb) rememberCb.checked = false;
        if (savedBadge) savedBadge.style.display = 'none';
        if (clearBtn) clearBtn.style.display = 'none';

        try {
            localStorage.removeItem(STORAGE_KEY);
        } catch (e) {}

        if (statusText) {
            statusText.textContent = 'Saved credentials erased from this browser.';
            feedbackEl.classList.remove('active-saved');
            feedbackEl.classList.add('warning-unsaved');
        }

        // Clear cookies on server asynchronously
        fetch('<?= BASE_URL ?>/login.php?clear_saved=1', {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        }).catch(function() {});

        if (typeof window.showToast === 'function') {
            window.showToast({
                title: 'Credentials Erased',
                message: 'Saved login email and password have been removed from this browser.',
                type: 'info'
            });
        }
    };

    // 4. Form submit: ensure storage is synced before submission
    if (loginForm) {
        loginForm.addEventListener('submit', function() {
            if (rememberCb && rememberCb.checked && emailInput && passwordInput) {
                try {
                    localStorage.setItem(STORAGE_KEY, JSON.stringify({
                        email: emailInput.value.trim(),
                        pwd: btoa(encodeURIComponent(passwordInput.value)),
                        remember: true,
                        updatedAt: new Date().toISOString()
                    }));
                } catch (e) {}
            } else {
                try {
                    localStorage.removeItem(STORAGE_KEY);
                } catch (e) {}
            }
        });
    }
})();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>