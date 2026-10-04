<?php
/**
 * SmartReceipt - Database Connection Setup
 * Initializes PDO connection with prepared statement security
 */

require_once __DIR__ . '/config.php';

$host    = 'localhost';
$db      = 'smart_receipt_db';
$user    = 'root';
$pass    = '';
$charset = 'utf8mb4';

$dsn = "mysql:host={$host};dbname={$db};charset={$charset}";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    http_response_code(500);
    // User-friendly error message with troubleshooting guidance
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <title>Database Connection Error - <?= e(APP_NAME) ?></title>
        <style>
            body { font-family: system-ui, -apple-system, sans-serif; background: #0f172a; color: #f8fafc; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; padding: 20px; }
            .card { background: #1e293b; border: 1px solid #334155; border-radius: 16px; max-width: 520px; padding: 36px; box-shadow: 0 20px 40px rgba(0,0,0,0.4); }
            h2 { color: #f43f5e; margin-top: 0; font-size: 22px; display: flex; align-items: center; gap: 10px; }
            p { color: #94a3b8; line-height: 1.6; font-size: 14px; }
            code { background: #0f172a; color: #38bdf8; padding: 3px 8px; border-radius: 6px; font-size: 13px; }
            ol { color: #cbd5e1; font-size: 14px; line-height: 1.8; padding-left: 20px; }
        </style>
    </head>
    <body>
        <div class="card">
            <h2>⚠️ Database Connection Failed</h2>
            <p>Could not connect to MySQL using database <code>smart_receipt_db</code>.</p>
            <ol>
                <li>Make sure MySQL is running in your <strong>XAMPP Control Panel</strong>.</li>
                <li>Import <code>database.sql</code> into phpMyAdmin (<code>http://localhost/phpmyadmin</code>).</li>
                <li>Check connection credentials in <code>config/db.php</code>.</li>
            </ol>
        </div>
    </body>
    </html>
    <?php
    exit;
}