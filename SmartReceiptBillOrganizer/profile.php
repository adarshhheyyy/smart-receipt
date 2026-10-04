<?php
/**
 * SmartReceipt - User Profile & Account Settings
 */

require_once __DIR__ . '/config/db.php';
require_login();

$userId = (int)$_SESSION['user_id'];
$page_title = 'Profile Settings';

// Fetch fresh user data from database
$stmtUser = $pdo->prepare("SELECT id, name, email, password, created_at FROM users WHERE id = ?");
$stmtUser->execute([$userId]);
$user = $stmtUser->fetch();

if (!$user) {
    flash('error', 'User account not found.');
    redirect(BASE_URL . '/logout.php');
}

// Fetch user account statistics
$stmtStats = $pdo->prepare("
    SELECT 
        COUNT(*) AS total_receipts,
        COALESCE(SUM(amount), 0) AS total_amount
    FROM receipts 
    WHERE user_id = ?
");
$stmtStats->execute([$userId]);
$receiptStats = $stmtStats->fetch();
$totalReceipts = (int)($receiptStats['total_receipts'] ?? 0);
$totalSpending = (float)($receiptStats['total_amount'] ?? 0.0);

$stmtW = $pdo->prepare("
    SELECT COUNT(*) 
    FROM warranties w 
    JOIN receipts r ON r.id = w.receipt_id 
    WHERE r.user_id = ? AND w.expiry_date >= CURDATE()
");
$stmtW->execute([$userId]);
$activeWarranties = (int)$stmtW->fetchColumn();

// Check if credentials vault is currently saved in browser cookies
$hasSavedCreds = !empty($_COOKIE['sr_saved_email']) || !empty($_COOKIE['sr_saved_pwd']);

// Error containers
$profile_errors = [];
$password_errors = [];

// Handle Form Submissions
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    // 1. UPDATE PROFILE INFORMATION
    if ($action === 'update_profile') {
        $name  = trim($_POST['name'] ?? '');
        $email = trim(strtolower($_POST['email'] ?? ''));

        if ($name === '') {
            $profile_errors[] = 'Full name is required.';
        } elseif (mb_strlen($name) < 2 || mb_strlen($name) > 100) {
            $profile_errors[] = 'Full name must be between 2 and 100 characters.';
        }

        if ($email === '') {
            $profile_errors[] = 'Email address is required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $profile_errors[] = 'Please enter a valid email address.';
        } elseif (mb_strlen($email) > 190) {
            $profile_errors[] = 'Email address cannot exceed 190 characters.';
        } else {
            // Check for uniqueness against other users
            $stmtCheck = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $stmtCheck->execute([$email, $userId]);
            if ($stmtCheck->fetch()) {
                $profile_errors[] = 'This email address is already registered to another account.';
            }
        }

        if (empty($profile_errors)) {
            $stmtUpdate = $pdo->prepare("UPDATE users SET name = ?, email = ? WHERE id = ?");
            $stmtUpdate->execute([$name, $email, $userId]);

            // Update session data
            $_SESSION['user_name']  = $name;
            $_SESSION['user_email'] = $email;

            // If credentials cookie was saved, update the saved email too
            if (!empty($_COOKIE['sr_saved_email'])) {
                setcookie('sr_saved_email', $email, [
                    'expires'  => time() + (30 * 86400),
                    'path'     => '/',
                    'httponly' => false,
                    'samesite' => 'Lax'
                ]);
            }

            flash('success', 'Your profile details have been updated successfully!');
            redirect(BASE_URL . '/profile.php');
        }
    }

    // 2. CHANGE PASSWORD
    elseif ($action === 'change_password') {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword     = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if ($currentPassword === '') {
            $password_errors[] = 'Current password is required.';
        } elseif (!password_verify($currentPassword, $user['password'])) {
            $password_errors[] = 'The current password you entered is incorrect.';
        }

        if ($newPassword === '') {
            $password_errors[] = 'Please enter a new password.';
        } elseif (mb_strlen($newPassword) < 8) {
            $password_errors[] = 'New password must be at least 8 characters in length.';
        } elseif (!preg_match('/[A-Z]/', $newPassword)) {
            $password_errors[] = 'New password must contain at least one capital (uppercase) letter (A-Z).';
        } elseif (!preg_match('/[0-9]/', $newPassword)) {
            $password_errors[] = 'New password must contain at least one number (0-9).';
        } elseif (!preg_match('/[^A-Za-z0-9]/', $newPassword)) {
            $password_errors[] = 'New password must contain at least one special symbol (e.g. !@#$%^&*).';
        } elseif (mb_strlen($newPassword) > 128) {
            $password_errors[] = 'New password is too long (maximum 128 characters).';
        } elseif ($newPassword === $currentPassword) {
            $password_errors[] = 'New password cannot be identical to your current password.';
        }

        if ($newPassword !== $confirmPassword) {
            $password_errors[] = 'New password and confirmation password do not match.';
        }

        if (empty($password_errors)) {
            $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
            $stmtPwd = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmtPwd->execute([$newHash, $userId]);

            // If user had saved password in cookies, update encrypted cookie
            if (!empty($_COOKIE['sr_saved_pwd'])) {
                setcookie('sr_saved_pwd', encrypt_vault_data($newPassword), [
                    'expires'  => time() + (30 * 86400),
                    'path'     => '/',
                    'httponly' => true,
                    'samesite' => 'Lax'
                ]);
            }

            flash('success', 'Your password has been changed successfully!');
            redirect(BASE_URL . '/profile.php');
        }
    }

    // 3. CLEAR SAVED VAULT CREDENTIALS FROM BROWSER COOKIES
    elseif ($action === 'clear_vault') {
        setcookie('sr_saved_email', '', time() - 3600, '/');
        setcookie('sr_saved_pwd', '', time() - 3600, '/');
        setcookie('sr_remember', '', time() - 3600, '/');

        flash('info', 'Saved login credentials have been removed from this browser.');
        redirect(BASE_URL . '/profile.php');
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<!-- Profile Hero Banner -->
<div class="profile-hero">
    <div class="profile-hero-left">
        <div class="profile-large-avatar" aria-hidden="true">
            <?= strtoupper(substr($user['name'], 0, 1)) ?>
        </div>
        <div class="profile-hero-meta">
            <h2><?= e($user['name']) ?></h2>
            <div class="profile-hero-email">
                <?= svg_icon('mail', '', 15) ?>
                <span><?= e($user['email']) ?></span>
            </div>
            <div class="profile-member-badge">
                <?= svg_icon('shield-check', '', 14) ?>
                <span>Member since <?= format_date($user['created_at'], 'M Y') ?></span>
            </div>
        </div>
    </div>

    <!-- Quick Stats Ribbon -->
    <div class="profile-stats-ribbon">
        <div class="profile-stat-chip">
            <span class="chip-label">Total Receipts</span>
            <span class="chip-val"><?= number_format($totalReceipts) ?></span>
        </div>
        <div class="profile-stat-chip">
            <span class="chip-label">Active Warranties</span>
            <span class="chip-val"><?= number_format($activeWarranties) ?></span>
        </div>
        <div class="profile-stat-chip">
            <span class="chip-label">Total Spending</span>
            <span class="chip-val"><?= format_currency($totalSpending) ?></span>
        </div>
    </div>
</div>

<!-- Two-Column Profile Settings Grid -->
<div class="profile-grid">

    <!-- Card 1: Personal Details Form -->
    <section class="profile-card" aria-labelledby="personalInfoTitle">
        <div class="profile-card-header">
            <div class="profile-card-icon">
                <?= svg_icon('user', '', 20) ?>
            </div>
            <div class="profile-card-header-text">
                <h3 id="personalInfoTitle">Personal Details</h3>
                <p>Manage your account name and email address</p>
            </div>
        </div>

        <form action="<?= BASE_URL ?>/profile.php" method="POST" novalidate>
            <?= csrf_input() ?>
            <input type="hidden" name="action" value="update_profile">

            <div class="profile-card-body">
                <?php if (!empty($profile_errors)): ?>
                    <div class="alert error" role="alert" style="margin-bottom: 0;">
                        <?= svg_icon('alert-triangle', '', 18) ?>
                        <div>
                            <?php foreach ($profile_errors as $err): ?>
                                <div><?= e($err) ?></div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="form-group">
                    <label for="profileName">
                        Full Name <span class="required">*</span>
                    </label>
                    <input type="text" id="profileName" name="name" class="form-control" 
                           value="<?= e($_POST['name'] ?? $user['name']) ?>" 
                           required minlength="2" maxlength="100" autocomplete="name">
                    <span class="profile-field-desc">This name is displayed across your dashboard and generated reports.</span>
                </div>

                <div class="form-group">
                    <label for="profileEmail">
                        Email Address <span class="required">*</span>
                    </label>
                    <input type="email" id="profileEmail" name="email" class="form-control" 
                           value="<?= e($_POST['email'] ?? $user['email']) ?>" 
                           required maxlength="190" autocomplete="email">
                    <span class="profile-field-desc">Used for logging in and receiving warranty alerts.</span>
                </div>

                <div class="security-notice-callout">
                    <?= svg_icon('shield', '', 17) ?>
                    <div>
                        <strong>Account Verified</strong>
                        <p style="margin: 2px 0 0 0; color: var(--text-muted); font-size: 11.5px;">
                            Your account is secured with password hashing and session tokens.
                        </p>
                    </div>
                </div>
            </div>

            <div class="profile-card-footer">
                <button type="submit" class="btn btn-primary" id="saveProfileBtn">
                    <?= svg_icon('check', '', 16) ?>
                    <span>Save Changes</span>
                </button>
            </div>
        </form>
    </section>

    <!-- Card 2: Password & Security Form -->
    <section class="profile-card" aria-labelledby="securityTitle">
        <div class="profile-card-header">
            <div class="profile-card-icon security-icon">
                <?= svg_icon('lock', '', 20) ?>
            </div>
            <div class="profile-card-header-text">
                <h3 id="securityTitle">Password & Security</h3>
                <p>Change your password to keep your vault secure</p>
            </div>
        </div>

        <form action="<?= BASE_URL ?>/profile.php" method="POST" id="passwordChangeForm" novalidate>
            <?= csrf_input() ?>
            <input type="hidden" name="action" value="change_password">

            <div class="profile-card-body">
                <?php if (!empty($password_errors)): ?>
                    <div class="alert error" role="alert" style="margin-bottom: 0;">
                        <?= svg_icon('alert-triangle', '', 18) ?>
                        <div>
                            <?php foreach ($password_errors as $err): ?>
                                <div><?= e($err) ?></div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="form-group">
                    <label for="currentPassword">
                        Current Password <span class="required">*</span>
                    </label>
                    <div class="input-with-action">
                        <input type="password" id="currentPassword" name="current_password" class="form-control" 
                               placeholder="••••••••" required autocomplete="current-password">
                        <button type="button" class="input-action-btn" onclick="togglePasswordVisibility('currentPassword', this)" aria-label="Toggle password view" title="Show / Hide Password">
                            <span class="icon-show"><?= svg_icon('eye', '', 17) ?></span>
                            <span class="icon-hide" style="display: none;"><?= svg_icon('eye-off', '', 17) ?></span>
                        </button>
                    </div>
                </div>

                <div class="form-group">
                    <label for="newPassword">
                        New Password <span class="required">*</span>
                    </label>
                    <div class="input-with-action">
                        <input type="password" id="newPassword" name="new_password" class="form-control" 
                               placeholder="Min. 8 chars, Aa, 123, !@#" required minlength="8" autocomplete="new-password" oninput="checkPasswordStrength(this.value)">
                        <button type="button" class="input-action-btn" onclick="togglePasswordVisibility('newPassword', this)" aria-label="Toggle password view" title="Show / Hide Password">
                            <span class="icon-show"><?= svg_icon('eye', '', 17) ?></span>
                            <span class="icon-hide" style="display: none;"><?= svg_icon('eye-off', '', 17) ?></span>
                        </button>
                    </div>
                    <!-- Password strength meter -->
                    <div class="pwd-strength-bar">
                        <div class="pwd-strength-fill" id="pwdStrengthFill"></div>
                    </div>
                    <div class="pwd-strength-text">
                        <span id="pwdStrengthLabel">Enter a new password</span>
                        <span>Min. 8 chars, 1 uppercase, 1 number, 1 symbol</span>
                    </div>
                </div>

                <div class="form-group">
                    <label for="confirmPassword">
                        Confirm New Password <span class="required">*</span>
                    </label>
                    <div class="input-with-action">
                        <input type="password" id="confirmPassword" name="confirm_password" class="form-control" 
                               placeholder="Re-enter new password" required autocomplete="new-password" oninput="checkPasswordMatch()">
                        <button type="button" class="input-action-btn" onclick="togglePasswordVisibility('confirmPassword', this)" aria-label="Toggle password view" title="Show / Hide Password">
                            <span class="icon-show"><?= svg_icon('eye', '', 17) ?></span>
                            <span class="icon-hide" style="display: none;"><?= svg_icon('eye-off', '', 17) ?></span>
                        </button>
                    </div>
                    <div id="pwdMatchPillWrap" style="display: none;">
                        <span class="pwd-match-pill nomatch" id="pwdMatchPill">
                            <?= svg_icon('alert-triangle', '', 12) ?>
                            <span id="pwdMatchText">Passwords do not match</span>
                        </span>
                    </div>
                </div>
            </div>

            <div class="profile-card-footer">
                <button type="submit" class="btn btn-primary" id="savePasswordBtn">
                    <?= svg_icon('key', '', 16) ?>
                    <span>Update Password</span>
                </button>
            </div>
        </form>
    </section>

    <!-- Card 3: Credential Vault & Device Preferences -->
    <section class="profile-card" aria-labelledby="vaultTitle">
        <div class="profile-card-header">
            <div class="profile-card-icon vault-icon">
                <?= svg_icon('shield-check', '', 20) ?>
            </div>
            <div class="profile-card-header-text">
                <h3 id="vaultTitle">Saved Credentials & Device Vault</h3>
                <p>Manage browser remember-me credentials and theme</p>
            </div>
        </div>

        <div class="profile-card-body">
            <!-- Vault Status Indicator -->
            <div class="vault-status-box <?= $hasSavedCreds ? 'active-vault' : '' ?>">
                <div class="vault-status-info">
                    <div class="vault-status-icon-wrap">
                        <?= svg_icon($hasSavedCreds ? 'shield-check' : 'shield', '', 20) ?>
                    </div>
                    <div class="vault-status-text">
                        <strong><?= $hasSavedCreds ? 'Saved Credentials: Active' : 'No Saved Credentials on This Device' ?></strong>
                        <small>
                            <?= $hasSavedCreds 
                                ? 'Encrypted AES-256 credentials stored in local cookie for 30 days.' 
                                : 'Credentials are not stored for auto-fill on this browser.' ?>
                        </small>
                    </div>
                </div>

                <?php if ($hasSavedCreds): ?>
                    <form action="<?= BASE_URL ?>/profile.php" method="POST" style="margin: 0;">
                        <?= csrf_input() ?>
                        <input type="hidden" name="action" value="clear_vault">
                        <button type="submit" class="btn btn-outline btn-sm btn-clear-vault" title="Clear saved login credentials from this browser">
                            <?= svg_icon('trash', '', 14) ?>
                            <span>Clear Saved</span>
                        </button>
                    </form>
                <?php endif; ?>
            </div>

            <!-- Appearance Theme Preference -->
            <div style="display: flex; align-items: center; justify-content: space-between; padding: 14px 18px; border-radius: var(--radius-md); background: var(--surface-muted); border: 1px solid var(--border-color);">
                <div style="display: flex; align-items: center; gap: 12px;">
                    <div style="width: 38px; height: 38px; border-radius: var(--radius-full); background: var(--surface-card); border: 1px solid var(--border-color); display: grid; place-items: center; color: var(--primary);">
                        <?= svg_icon('sun', '', 18) ?>
                    </div>
                    <div>
                        <strong style="display: block; font-size: 13.5px; color: var(--text-primary);">Interface Theme</strong>
                        <small style="display: block; font-size: 12px; color: var(--text-muted);">Switch between elegant light mode and sleek dark mode</small>
                    </div>
                </div>
                <button type="button" class="btn btn-secondary btn-sm" onclick="toggleTheme()" style="gap: 8px;">
                    <span class="theme-icon-dark"><?= svg_icon('sun', '', 15) ?> Light Mode</span>
                    <span class="theme-icon-light"><?= svg_icon('moon', '', 15) ?> Dark Mode</span>
                </button>
            </div>

            <div class="security-notice-callout">
                <?= svg_icon('info', '', 17) ?>
                <div>
                    <strong>Data Privacy Notice</strong>
                    <p style="margin: 2px 0 0 0; color: var(--text-muted); font-size: 11.5px;">
                        All uploaded bills, receipts, and warranties are strictly isolated to your user ID.
                    </p>
                </div>
            </div>
        </div>

        <div class="profile-card-footer" style="justify-content: space-between;">
            <span style="font-size: 12px; color: var(--text-muted);">User ID: #<?= $userId ?></span>
            <a href="<?= BASE_URL ?>/logout.php" class="btn btn-ghost btn-sm" style="color: var(--danger-text);">
                <?= svg_icon('logout', '', 14) ?>
                <span>Sign Out</span>
            </a>
        </div>
    </section>

    <!-- Card 4: Quick Navigation & Vault Summary -->
    <section class="profile-card" aria-labelledby="quickNavTitle">
        <div class="profile-card-header">
            <div class="profile-card-icon overview-icon">
                <?= svg_icon('dashboard', '', 20) ?>
            </div>
            <div class="profile-card-header-text">
                <h3 id="quickNavTitle">Vault Quick Actions</h3>
                <p>Direct shortcuts to organize your bills and warranties</p>
            </div>
        </div>

        <div class="profile-card-body" style="gap: 14px;">
            <a href="<?= BASE_URL ?>/add_receipt.php" class="btn btn-secondary" style="justify-content: flex-start; padding: 14px 18px; text-align: left;">
                <?= svg_icon('plus-circle', '', 20) ?>
                <div style="margin-left: 6px;">
                    <strong style="display: block; font-size: 13.5px; color: var(--text-primary);">Add New Receipt or Bill</strong>
                    <small style="color: var(--text-muted); font-size: 11.5px;">Upload a store bill image and attach warranty details</small>
                </div>
            </a>

            <a href="<?= BASE_URL ?>/receipts.php" class="btn btn-secondary" style="justify-content: flex-start; padding: 14px 18px; text-align: left;">
                <?= svg_icon('receipt', '', 20) ?>
                <div style="margin-left: 6px;">
                    <strong style="display: block; font-size: 13.5px; color: var(--text-primary);">View All Saved Receipts</strong>
                    <small style="color: var(--text-muted); font-size: 11.5px;">Browse <?= $totalReceipts ?> receipts with instant category filters</small>
                </div>
            </a>

            <a href="<?= BASE_URL ?>/warranties.php" class="btn btn-secondary" style="justify-content: flex-start; padding: 14px 18px; text-align: left;">
                <?= svg_icon('shield-check', '', 20) ?>
                <div style="margin-left: 6px;">
                    <strong style="display: block; font-size: 13.5px; color: var(--text-primary);">Warranty Tracker</strong>
                    <small style="color: var(--text-muted); font-size: 11.5px;">Track expiring warranties and avoid missed claims</small>
                </div>
            </a>
        </div>

        <div class="profile-card-footer">
            <a href="<?= BASE_URL ?>/dashboard.php" class="btn btn-primary btn-sm">
                <span>Go to Dashboard</span>
                <?= svg_icon('arrow-right', '', 14) ?>
            </a>
        </div>
    </section>

</div>

<script>
/**
 * Toggle password input between text and password
 */
function togglePasswordVisibility(fieldId, btnElement) {
    const input = document.getElementById(fieldId);
    if (!input) return;

    const isPassword = input.type === 'password';
    input.type = isPassword ? 'text' : 'password';

    const iconShow = btnElement.querySelector('.icon-show');
    const iconHide = btnElement.querySelector('.icon-hide');

    if (iconShow && iconHide) {
        iconShow.style.display = isPassword ? 'none' : 'inline-block';
        iconHide.style.display = isPassword ? 'inline-block' : 'none';
    }
}

/**
 * Check password strength dynamically
 */
function checkPasswordStrength(password) {
    const fill = document.getElementById('pwdStrengthFill');
    const label = document.getElementById('pwdStrengthLabel');
    if (!fill || !label) return;

    if (!password || password.length === 0) {
        fill.className = 'pwd-strength-fill';
        label.textContent = 'Enter a new password';
        label.style.color = 'var(--text-muted)';
        return;
    }

    let score = 0;
    if (password.length >= 6) score++;
    if (password.length >= 10) score++;
    if (/[A-Z]/.test(password)) score++;
    if (/[0-9]/.test(password)) score++;
    if (/[^A-Za-z0-9]/.test(password)) score++;

    if (score <= 2) {
        fill.className = 'pwd-strength-fill weak';
        label.textContent = 'Weak password';
        label.style.color = '#ef4444';
    } else if (score <= 3) {
        fill.className = 'pwd-strength-fill medium';
        label.textContent = 'Medium strength';
        label.style.color = '#f59e0b';
    } else {
        fill.className = 'pwd-strength-fill strong';
        label.textContent = 'Strong password';
        label.style.color = '#10b981';
    }

    checkPasswordMatch();
}

/**
 * Check password matching in real-time
 */
function checkPasswordMatch() {
    const newPwd = document.getElementById('newPassword');
    const confirmPwd = document.getElementById('confirmPassword');
    const wrap = document.getElementById('pwdMatchPillWrap');
    const pill = document.getElementById('pwdMatchPill');
    const text = document.getElementById('pwdMatchText');

    if (!newPwd || !confirmPwd || !wrap || !pill || !text) return;

    if (!confirmPwd.value) {
        wrap.style.display = 'none';
        return;
    }

    wrap.style.display = 'block';

    if (newPwd.value === confirmPwd.value) {
        pill.className = 'pwd-match-pill match';
        text.textContent = 'Passwords match';
    } else {
        pill.className = 'pwd-match-pill nomatch';
        text.textContent = 'Passwords do not match';
    }
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
