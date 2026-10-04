<?php
/**
 * SmartReceipt - User Sign Out
 */

require_once __DIR__ . '/config/config.php';

// Unset all session keys
$_SESSION = [];

// Delete session cookie if configured
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

// Destroy session
session_destroy();

// Start fresh session just to flash sign-out confirmation
session_start();
flash('info', 'You have been signed out successfully.');

redirect(BASE_URL . '/login.php');