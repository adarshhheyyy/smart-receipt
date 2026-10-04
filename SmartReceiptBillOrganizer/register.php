<?php
/**
 * SmartReceipt - User Registration
 */

require_once __DIR__ . '/config/db.php';

if (is_logged_in()) {
    redirect(BASE_URL . '/dashboard.php');
}

$errors = [];
$name  = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $name     = trim($_POST['name'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';

    // Validation
    if ($name === '' || mb_strlen($name) < 2) {
        $errors[] = 'Please enter your full name (minimum 2 characters).';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please provide a valid email address.';
    }
    // Strong Password Validation: min 8 chars, 1 uppercase, 1 number, 1 symbol
    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters in length.';
    }
    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = 'Password must contain at least one capital (uppercase) letter (A-Z).';
    }
    if (!preg_match('/[0-9]/', $password)) {
        $errors[] = 'Password must contain at least one number (0-9).';
    }
    if (!preg_match('/[^A-Za-z0-9]/', $password)) {
        $errors[] = 'Password must contain at least one special symbol (e.g. !@#$%^&*).';
    }
    if ($password !== $confirm) {
        $errors[] = 'Password confirmation does not match.';
    }

    if (empty($errors)) {
        // Check for duplicate account
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);

        if ($stmt->fetch()) {
            $errors[] = 'An account with this email address already exists.';
        } else {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $insertStmt = $pdo->prepare("INSERT INTO users (name, email, password) VALUES (?, ?, ?)");
            $insertStmt->execute([$name, $email, $hashedPassword]);

            flash('success', 'Account created successfully! Please sign in to access your vault.');
            redirect(BASE_URL . '/login.php');
        }
    }
}

$page_title = 'Create Account';
require_once __DIR__ . '/includes/header.php';
?>

<div class="auth-wrapper">
    <div class="auth-card" style="max-width: 500px;">
        <div class="auth-header">
            <div class="auth-brand-mark">
                <?= svg_icon('receipt', '', 24) ?>
            </div>
            <h1>Create Your Vault</h1>
            <p>Organize receipts, track warranties, and receive timely expiry alerts.</p>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="alert error permanent" role="alert">
                <?= svg_icon('alert-triangle', '', 20) ?>
                <div>
                    <strong>Password or Account Issue:</strong>
                    <ul style="margin: 6px 0 0 18px;">
                        <?php foreach ($errors as $err): ?>
                            <li><?= e($err) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        <?php endif; ?>

        <form method="post" action="<?= BASE_URL ?>/register.php" class="form-grid" id="registerForm">
            <?= csrf_input() ?>

            <div class="form-group">
                <label for="regName">Full Name</label>
                <input type="text" id="regName" name="name" class="form-control" 
                       value="<?= e($name) ?>" placeholder="e.g. John Doe" required autocomplete="name" autofocus>
            </div>

            <div class="form-group">
                <label for="regEmail">Email Address</label>
                <input type="email" id="regEmail" name="email" class="form-control" 
                       value="<?= e($email) ?>" placeholder="name@example.com" required autocomplete="email">
            </div>

            <div class="form-group">
                <label for="regPassword">
                    <span>Password</span>
                    <small style="color: var(--primary); font-weight: 600;">(Strong required)</small>
                </label>
                <div class="input-with-action">
                    <input type="password" id="regPassword" name="password" class="form-control" 
                           placeholder="••••••••" required minlength="8" autocomplete="new-password" oninput="checkPasswordStrength(this.value)">
                    <button type="button" class="input-action-btn" onclick="togglePasswordVisibility('regPassword', this)" aria-label="Toggle password view" title="Show / Hide Password">
                        <span class="icon-show"><?= svg_icon('eye', '', 17) ?></span>
                        <span class="icon-hide" style="display: none;"><?= svg_icon('eye-off', '', 17) ?></span>
                    </button>
                </div>

                <!-- Password Strength Meter -->
                <div class="pwd-meter-box">
                    <div class="pwd-meter-track">
                        <div class="pwd-meter-bar" id="pwdMeterBar"></div>
                    </div>
                    <span class="pwd-meter-label" id="pwdMeterLabel">Enter password</span>
                </div>

                <!-- Dynamic Requirement Checklist -->
                <div class="pwd-requirements" id="pwdRequirements">
                    <div class="pwd-req-item" id="reqLength">
                        <span class="pwd-req-icon"><?= svg_icon('check', '', 12) ?></span>
                        <span>8+ characters</span>
                    </div>
                    <div class="pwd-req-item" id="reqUpper">
                        <span class="pwd-req-icon"><?= svg_icon('check', '', 12) ?></span>
                        <span>1 Capital letter (A-Z)</span>
                    </div>
                    <div class="pwd-req-item" id="reqNumber">
                        <span class="pwd-req-icon"><?= svg_icon('check', '', 12) ?></span>
                        <span>1 Number (0-9)</span>
                    </div>
                    <div class="pwd-req-item" id="reqSymbol">
                        <span class="pwd-req-icon"><?= svg_icon('check', '', 12) ?></span>
                        <span>1 Symbol (!@#$...)</span>
                    </div>
                </div>
            </div>

            <div class="form-group">
                <label for="regConfirm">Confirm Password</label>
                <div class="input-with-action">
                    <input type="password" id="regConfirm" name="confirm_password" class="form-control" 
                           placeholder="••••••••" required minlength="8" autocomplete="new-password">
                    <button type="button" class="input-action-btn" onclick="togglePasswordVisibility('regConfirm', this)" aria-label="Toggle confirm password view" title="Show / Hide Password">
                        <span class="icon-show"><?= svg_icon('eye', '', 17) ?></span>
                        <span class="icon-hide" style="display: none;"><?= svg_icon('eye-off', '', 17) ?></span>
                    </button>
                </div>
            </div>

            <button class="btn btn-primary btn-block btn-lg" type="submit" style="margin-top: 8px;">
                <span>Create Secure Account</span>
                <?= svg_icon('arrow-right', '', 16) ?>
            </button>
        </form>

        <p class="auth-switch">
            Already have an account? <a href="<?= BASE_URL ?>/login.php">Sign in instead</a>
        </p>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>