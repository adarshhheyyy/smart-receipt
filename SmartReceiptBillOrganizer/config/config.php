<?php
/**
 * SmartReceipt - Bill & Warranty Organizer
 * Application Configuration & Core Helper Functions
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Application Constants
define('APP_NAME', 'SmartReceipt');
define('APP_TAGLINE', 'Bill & Warranty Organizer');
define('BASE_URL', '/SmartReceiptBillOrganizer');
define('CURRENCY_SYMBOL', '₹');

// Upload constraints
define('UPLOAD_DIR', __DIR__ . '/../uploads/');
define('MAX_UPLOAD_BYTES', 3 * 1024 * 1024); // 3 MB
define('ALLOWED_IMAGE_TYPES', [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/webp' => 'webp',
]);

// Centralized Categories with Design Tokens & Icons
define('RECEIPT_CATEGORIES', [
    'Shopping'    => ['icon' => 'shopping-bag', 'color' => '#6366f1', 'bg' => '#eef2ff'],
    'Electronics' => ['icon' => 'cpu',          'color' => '#0284c7', 'bg' => '#e0f2fe'],
    'Food'        => ['icon' => 'coffee',       'color' => '#d97706', 'bg' => '#fef3c7'],
    'Travel'      => ['icon' => 'compass',      'color' => '#7c3aed', 'bg' => '#f5f3ff'],
    'Home'        => ['icon' => 'home',         'color' => '#059669', 'bg' => '#ecfdf5'],
    'Healthcare'  => ['icon' => 'activity',     'color' => '#e11d48', 'bg' => '#ffe4e6'],
    'Education'   => ['icon' => 'book-open',    'color' => '#2563eb', 'bg' => '#eff6ff'],
    'Fashion'     => ['icon' => 'tag',          'color' => '#db2777', 'bg' => '#fdf2f8'],
    'Bills'       => ['icon' => 'file-text',    'color' => '#0d9488', 'bg' => '#f0fdfa'],
    'Other'       => ['icon' => 'box',          'color' => '#475569', 'bg' => '#f1f5f9'],
]);

// Set default timezone
date_default_timezone_set('Asia/Kolkata');

// CSRF Token Management
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/**
 * Escape HTML output safely
 */
function e($value): string {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

/**
 * Redirect to a specific URL and exit execution
 */
function redirect(string $url): void {
    header("Location: " . $url);
    exit;
}

/**
 * Check if current user is logged in
 */
function is_logged_in(): bool {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Enforce authentication on protected pages
 */
function require_login(): void {
    if (!is_logged_in()) {
        flash('error', 'Please sign in to access that page.');
        redirect(BASE_URL . '/login.php');
    }
}

// Vault Credentials Encryption Secret Key for Saved Passwords
define('VAULT_SECRET_KEY', hash('sha256', 'smartreceipt_credentials_vault_key_2026'));

/**
 * Encrypt credential data safely for local cookie storage
 */
function encrypt_vault_data(string $data): string {
    $ivLength = openssl_cipher_iv_length('AES-256-CBC');
    $iv = openssl_random_pseudo_bytes($ivLength);
    $encrypted = openssl_encrypt($data, 'AES-256-CBC', VAULT_SECRET_KEY, 0, $iv);
    return base64_encode($iv . '::' . $encrypted);
}

/**
 * Decrypt credential data safely from local cookie storage
 */
function decrypt_vault_data(?string $encryptedData): ?string {
    if (!$encryptedData) {
        return null;
    }
    try {
        $raw = base64_decode($encryptedData, true);
        if (!$raw || strpos($raw, '::') === false) {
            return null;
        }
        list($iv, $encrypted) = explode('::', $raw, 2);
        $decrypted = openssl_decrypt($encrypted, 'AES-256-CBC', VAULT_SECRET_KEY, 0, $iv);
        return $decrypted !== false ? $decrypted : null;
    } catch (\Throwable $e) {
        return null;
    }
}

/**
 * Get current user name safely
 */
function current_user_name(): string {
    return $_SESSION['user_name'] ?? 'User';
}

/**
 * Get current user email safely
 */
function current_user_email(): string {
    return $_SESSION['user_email'] ?? '';
}

/**
 * Get active CSRF token
 */
function csrf_token(): string {
    return $_SESSION['csrf_token'] ?? '';
}

/**
 * Render hidden CSRF form input field
 */
function csrf_input(): string {
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

/**
 * Verify CSRF token for state-mutating requests
 */
function verify_csrf(): void {
    $submittedToken = $_POST['csrf_token'] ?? $_GET['csrf_token'] ?? '';
    if (
        empty($submittedToken) ||
        !hash_equals($_SESSION['csrf_token'] ?? '', $submittedToken)
    ) {
        http_response_code(403);
        exit('Invalid security token. Please refresh the page and try again.');
    }
}

/**
 * Store a flash message for the next request
 */
function flash(string $type, string $message): void {
    $_SESSION['flash'] = [
        'type'    => in_array($type, ['success', 'error', 'info', 'warning']) ? $type : 'info',
        'message' => $message,
    ];
}

/**
 * Retrieve and clear the active flash message
 */
function get_flash(): ?array {
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $flash;
}

/**
 * Format currency with symbol and 2 decimals
 */
function format_currency(float|int|string $amount): string {
    return CURRENCY_SYMBOL . ' ' . number_format((float)$amount, 2);
}

/**
 * Format date string safely
 */
function format_date(?string $date, string $format = 'd M Y'): string {
    if (!$date || $date === '0000-00-00') {
        return '—';
    }
    $timestamp = strtotime($date);
    return $timestamp ? date($format, $timestamp) : '—';
}

/**
 * Compute warranty status details
 */
function get_warranty_status(string $expiryDate, ?string $startDate = null): array {
    $today = new DateTime('today');
    $expiry = new DateTime($expiryDate);
    $diff = (int)$today->diff($expiry)->format('%r%a');

    if ($diff < 0) {
        $state = 'expired';
        $label = 'Expired (' . abs($diff) . 'd ago)';
        $badgeClass = 'status-badge-expired';
    } elseif ($diff === 0) {
        $state = 'soon';
        $label = 'Expires Today';
        $badgeClass = 'status-badge-soon';
    } elseif ($diff <= 30) {
        $state = 'soon';
        $label = $diff . ' days left';
        $badgeClass = 'status-badge-soon';
    } else {
        $state = 'active';
        $label = $diff . ' days left';
        $badgeClass = 'status-badge-active';
    }

    // Calculate percentage progress if start date is available
    $percentElapsed = 100;
    if ($startDate && $startDate !== '0000-00-00') {
        $start = new DateTime($startDate);
        $totalDays = max(1, (int)$start->diff($expiry)->format('%r%a'));
        $daysPassed = (int)$start->diff($today)->format('%r%a');
        $percentElapsed = min(100, max(0, round(($daysPassed / $totalDays) * 100)));
    }

    return [
        'state'          => $state,
        'days'           => $diff,
        'label'          => $label,
        'badge_class'    => $badgeClass,
        'percent_elapsed'=> $percentElapsed,
    ];
}

/**
 * Render category badge markup
 */
function render_category_badge(string $category): string {
    $info = RECEIPT_CATEGORIES[$category] ?? ['icon' => 'box', 'color' => '#64748b', 'bg' => '#f1f5f9'];
    $iconSvg = svg_icon($info['icon'], 'cat-icon', 14);
    
    return sprintf(
        '<span class="category-pill" style="--cat-color: %s; --cat-bg: %s">%s <span>%s</span></span>',
        e($info['color']),
        e($info['bg']),
        $iconSvg,
        e($category)
    );
}

/**
 * Crisp SVG icon system
 */
function svg_icon(string $name, string $class = '', int $size = 20): string {
    $attrs = sprintf(
        'class="svg-icon %s" width="%d" height="%d" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"',
        e($class),
        $size,
        $size
    );

    $icons = [
        'dashboard' => '<rect x="3" y="3" width="7" height="9" rx="1"/><rect x="14" y="3" width="7" height="5" rx="1"/><rect x="14" y="12" width="7" height="9" rx="1"/><rect x="3" y="16" width="7" height="5" rx="1"/>',
        'receipt' => '<path d="M4 2v20l2-1 2 1 2-1 2 1 2-1 2 1 2-1 2 1V2l-2 1-2-1-2 1-2-1-2 1-2-1-2 1Z"/><path d="M16 8h-8"/><path d="M16 12h-8"/><path d="M12 16h-4"/>',
        'shield' => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>',
        'shield-check' => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="m9 12 2 2 4-4"/>',
        'plus' => '<line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>',
        'plus-circle' => '<circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/>',
        'user' => '<path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
        'logout' => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/>',
        'search' => '<circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>',
        'filter' => '<polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/>',
        'edit' => '<path d="M17 3a2.828 2.828 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5L17 3z"/>',
        'trash' => '<polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/>',
        'calendar' => '<rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>',
        'clock' => '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>',
        'alert-triangle' => '<path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>',
        'check' => '<polyline points="20 6 9 17 4 12"/>',
        'check-circle' => '<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>',
        'x' => '<line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>',
        'arrow-right' => '<line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/>',
        'upload-cloud' => '<polyline points="16 16 12 12 8 16"/><line x1="12" y1="12" x2="12" y2="21"/><path d="M20.39 18.39A5 5 0 0 0 18 9h-1.26A8 8 0 1 0 3 16.3"/><polyline points="16 16 12 12 8 16"/>',
        'image' => '<rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/>',
        'sparkles' => '<path d="m12 3-1.9 5.8a2 2 0 0 1-1.3 1.3L3 12l5.8 1.9a2 2 0 0 1 1.3 1.3L12 21l1.9-5.8a2 2 0 0 1 1.3-1.3L21 12l-5.8-1.9a2 2 0 0 1-1.3-1.3Z"/>',
        'trending-up' => '<polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/>',
        'menu' => '<line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/>',
        'eye' => '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>',
        'eye-off' => '<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/>',
        'bell' => '<path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/>',
        'bell-off' => '<path d="M13.73 21a2 2 0 0 1-3.46 0"/><path d="M18.63 13A17.89 17.89 0 0 1 18 8"/><path d="M6.26 6.26A5.86 5.86 0 0 0 6 8c0 7-3 9-3 9h14"/><path d="M18 8a6 6 0 0 0-9.33-5"/><line x1="1" y1="1" x2="23" y2="23"/>',
        'sun' => '<circle cx="12" cy="12" r="4"/><path d="M12 2v2"/><path d="M12 20v2"/><path d="m4.93 4.93 1.41 1.41"/><path d="m17.66 17.66 1.41 1.41"/><path d="M2 12h2"/><path d="M20 12h2"/><path d="m6.34 17.66-1.41 1.41"/><path d="m19.07 4.93-1.41 1.41"/>',
        'moon' => '<path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/>',
        'info' => '<circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/>',
        'settings' => '<path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"/><circle cx="12" cy="12" r="3"/>',
        'lock' => '<rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>',
        'key' => '<path d="m21 2-2 2m-1.5 1.5L16 7l-1.5-1.5-2 2 1.5 1.5L12 11l-2-2-4 4a5 5 0 1 0 7.07 7.07l4-4-2-2 2-2 1.5 1.5L20 10l-1.5-1.5L20 7l-2-2 3-3Z"/>',
        'mail' => '<rect width="20" height="16" x="2" y="4" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/>',
        // Categories
        'shopping-bag' => '<path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-2Z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/>',
        'cpu' => '<rect x="4" y="4" width="16" height="16" rx="2"/><rect x="9" y="9" width="6" height="6"/><line x1="9" y1="1" x2="9" y2="4"/><line x1="15" y1="1" x2="15" y2="4"/><line x1="9" y1="20" x2="9" y2="23"/><line x1="15" y1="20" x2="15" y2="23"/><line x1="20" y1="9" x2="23" y2="9"/><line x1="20" y1="14" x2="23" y2="14"/><line x1="1" y1="9" x2="4" y2="9"/><line x1="1" y1="14" x2="4" y2="14"/>',
        'coffee' => '<path d="M18 8h1a4 4 0 0 1 0 8h-1"/><path d="M2 8h16v9a4 4 0 0 1-4 4H6a4 4 0 0 1-4-4V8z"/><line x1="6" y1="1" x2="6" y2="4"/><line x1="10" y1="1" x2="10" y2="4"/><line x1="14" y1="1" x2="14" y2="4"/>',
        'compass' => '<circle cx="12" cy="12" r="10"/><polygon points="16.24 7.76 14.12 14.12 7.76 16.24 9.88 9.88 16.24 7.76"/>',
        'home' => '<path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/>',
        'activity' => '<polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>',
        'book-open' => '<path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/>',
        'tag' => '<path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/>',
        'file-text' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/>',
        'box' => '<path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/>',
    ];

    $path = $icons[$name] ?? '<circle cx="12" cy="12" r="10"/>';
    return sprintf('<svg %s>%s</svg>', $attrs, $path);
}