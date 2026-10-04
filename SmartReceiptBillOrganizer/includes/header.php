<?php
/**
 * SmartReceipt - Global Header Template
 */

require_once __DIR__ . '/../config/db.php';

$flash = get_flash();
$page_title = $page_title ?? APP_NAME;
$current_page = basename($_SERVER['PHP_SELF']);

// Count warranties expiring soon for the sidebar badge and build notifications
$warranty_alert_count = 0;
$notifications_list = [];

if (is_logged_in()) {
    try {
        $curUserId = (int)$_SESSION['user_id'];

        $stmtAlert = $pdo->prepare("
            SELECT COUNT(*) 
            FROM warranties w 
            JOIN receipts r ON r.id = w.receipt_id 
            WHERE r.user_id = ? 
              AND w.expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
        ");
        $stmtAlert->execute([$curUserId]);
        $warranty_alert_count = (int)$stmtAlert->fetchColumn();

        // 1. Warranties expiring soon or expired recently
        $stmtWNotif = $pdo->prepare("
            SELECT w.id, w.product_name, w.expiry_date, w.warranty_start, r.id AS receipt_id, r.shop_name, r.amount
            FROM warranties w
            JOIN receipts r ON r.id = w.receipt_id
            WHERE r.user_id = ?
              AND (
                  w.expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
                  OR w.expiry_date BETWEEN DATE_SUB(CURDATE(), INTERVAL 14 DAY) AND DATE_SUB(CURDATE(), INTERVAL 1 DAY)
              )
            ORDER BY w.expiry_date ASC
            LIMIT 10
        ");
        $stmtWNotif->execute([$curUserId]);
        $wNotifs = $stmtWNotif->fetchAll();

        foreach ($wNotifs as $wn) {
            $wStat = get_warranty_status($wn['expiry_date'], $wn['warranty_start']);
            $diffDays = $wStat['days'];
            
            if ($diffDays < 0) {
                $notifications_list[] = [
                    'id'          => 'w-exp-' . $wn['id'],
                    'category'    => 'warranty',
                    'type'        => 'expired',
                    'title'       => 'Warranty Expired',
                    'message'     => $wn['product_name'] . ' expired ' . abs($diffDays) . ' days ago (' . format_date($wn['expiry_date']) . ').',
                    'time_label'  => abs($diffDays) . 'd ago',
                    'link'        => BASE_URL . '/warranties.php?status=expired',
                    'icon'        => 'clock',
                    'action_text' => 'Check warranty'
                ];
            } elseif ($diffDays <= 7) {
                $notifications_list[] = [
                    'id'          => 'w-urgent-' . $wn['id'],
                    'category'    => 'warranty',
                    'type'        => 'urgent',
                    'title'       => 'Urgent: Expiration in ' . ($diffDays === 0 ? 'Today' : $diffDays . 'd'),
                    'message'     => $wn['product_name'] . ' from ' . $wn['shop_name'] . ' warranty ends ' . ($diffDays === 0 ? 'today' : 'in ' . $diffDays . ' days') . '.',
                    'time_label'  => $diffDays === 0 ? 'Today' : $diffDays . 'd left',
                    'link'        => BASE_URL . '/warranties.php?status=soon',
                    'icon'        => 'alert-triangle',
                    'action_text' => 'Review item'
                ];
            } else {
                $notifications_list[] = [
                    'id'          => 'w-soon-' . $wn['id'],
                    'category'    => 'warranty',
                    'type'        => 'warning',
                    'title'       => 'Warranty Expiring Soon',
                    'message'     => $wn['product_name'] . ' from ' . $wn['shop_name'] . ' ends on ' . format_date($wn['expiry_date']) . '.',
                    'time_label'  => $diffDays . 'd left',
                    'link'        => BASE_URL . '/warranties.php?status=soon',
                    'icon'        => 'clock',
                    'action_text' => 'View details'
                ];
            }
        }

        // 2. Recent receipt uploads
        $stmtRNotif = $pdo->prepare("
            SELECT r.id, r.shop_name, r.amount, r.category, r.purchase_date
            FROM receipts r
            WHERE r.user_id = ?
            ORDER BY r.id DESC
            LIMIT 3
        ");
        $stmtRNotif->execute([$curUserId]);
        $rNotifs = $stmtRNotif->fetchAll();

        foreach ($rNotifs as $rn) {
            $notifications_list[] = [
                'id'          => 'r-new-' . $rn['id'],
                'category'    => 'activity',
                'type'        => 'info',
                'title'       => 'Receipt Recorded',
                'message'     => 'Store bill for ' . $rn['shop_name'] . ' (' . format_currency($rn['amount']) . ') archived in ' . $rn['category'] . '.',
                'time_label'  => format_date($rn['purchase_date']),
                'link'        => BASE_URL . '/receipts.php?q=' . urlencode($rn['shop_name']),
                'icon'        => 'receipt',
                'action_text' => 'Inspect bill'
            ];
        }

        // 3. Pro Tip
        $notifications_list[] = [
            'id'          => 'tip-digital-records',
            'category'    => 'activity',
            'type'        => 'tip',
            'title'       => 'Smart Vault Tip',
            'message'     => 'Attach clear receipt images to high-value electronics to safeguard manufacturer warranty claims.',
            'time_label'  => 'Pro Tip',
            'link'        => BASE_URL . '/add_receipt.php',
            'icon'        => 'sparkles',
            'action_text' => 'Add receipt'
        ];

    } catch (Throwable $e) {
        $warranty_alert_count = 0;
        $notifications_list = [];
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title><?= e($page_title) ?> | <?= e(APP_NAME) ?></title>
    <meta name="description" content="Organize your digital receipts, manage expense categories, and track product warranty expiration dates with SmartReceipt.">
    <!-- Immediate theme setter: classy alabaster light theme by default -->
    <script>
        (function() {
            var theme = localStorage.getItem('smartreceipt_theme');
            if (!theme || theme === 'dark') {
                theme = 'light';
                localStorage.setItem('smartreceipt_theme', 'light');
            }
            document.documentElement.setAttribute('data-theme', theme);
        })();
    </script>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
    <?php if (is_logged_in()): ?>
    <script>
        window.__INITIAL_NOTIFICATIONS__ = <?= json_encode($notifications_list, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
    </script>
    <?php endif; ?>
</head>
<body>

<?php if (is_logged_in()): ?>
<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

<div class="app-shell">
    <!-- Sidebar Navigation -->
    <aside class="sidebar" id="sidebar">
        <div class="brand">
            <div class="brand-mark">
                <?= svg_icon('receipt', '', 22) ?>
            </div>
            <div class="brand-text">
                <strong><?= e(APP_NAME) ?></strong>
                <span><?= e(APP_TAGLINE) ?></span>
            </div>
        </div>

        <nav class="nav-menu">
            <a href="<?= BASE_URL ?>/dashboard.php" class="nav-link <?= $current_page === 'dashboard.php' ? 'active' : '' ?>">
                <?= svg_icon('dashboard', '', 18) ?>
                <span>Dashboard</span>
            </a>
            <a href="<?= BASE_URL ?>/add_receipt.php" class="nav-link <?= $current_page === 'add_receipt.php' ? 'active' : '' ?>">
                <?= svg_icon('plus-circle', '', 18) ?>
                <span>Add Receipt</span>
            </a>
            <a href="<?= BASE_URL ?>/receipts.php" class="nav-link <?= $current_page === 'receipts.php' ? 'active' : '' ?>">
                <?= svg_icon('receipt', '', 18) ?>
                <span>All Receipts</span>
            </a>
            <a href="<?= BASE_URL ?>/warranties.php" class="nav-link <?= $current_page === 'warranties.php' ? 'active' : '' ?>">
                <?= svg_icon('shield-check', '', 18) ?>
                <span>Warranty Tracker</span>
                <?php if ($warranty_alert_count > 0): ?>
                    <span class="nav-badge"><?= $warranty_alert_count ?></span>
                <?php endif; ?>
            </a>
            <a href="<?= BASE_URL ?>/profile.php" class="nav-link <?= $current_page === 'profile.php' ? 'active' : '' ?>">
                <?= svg_icon('settings', '', 18) ?>
                <span>Profile Settings</span>
            </a>
        </nav>

        <div class="sidebar-bottom">
            <a href="<?= BASE_URL ?>/profile.php" class="user-card user-card-clickable <?= $current_page === 'profile.php' ? 'active' : '' ?>" title="View Profile & Settings">
                <div class="user-avatar">
                    <?= strtoupper(substr(current_user_name(), 0, 1)) ?>
                </div>
                <div class="user-meta">
                    <span class="user-name"><?= e(current_user_name()) ?></span>
                    <span class="user-email"><?= e(current_user_email()) ?></span>
                </div>
                <span class="user-card-gear" title="Profile Settings">
                    <?= svg_icon('settings', '', 15) ?>
                </span>
            </a>
            <a href="<?= BASE_URL ?>/logout.php" class="logout-button">
                <?= svg_icon('logout', '', 16) ?>
                <span>Sign Out</span>
            </a>
        </div>
    </aside>

    <!-- Main Workspace Area -->
    <div class="main-area">
        <header class="topbar">
            <div class="topbar-left">
                <button class="menu-toggle" onclick="toggleSidebar()" aria-label="Toggle Navigation">
                    <?= svg_icon('menu', '', 20) ?>
                </button>
                <div class="topbar-title">
                    <span class="eyebrow">Smart Vault</span>
                    <h1><?= e($page_title) ?></h1>
                </div>
            </div>

            <div class="topbar-right">
                <!-- Theme Toggle Button -->
                <button type="button" class="theme-toggle-btn" id="themeToggleBtn" onclick="toggleTheme()" aria-label="Toggle Dark/Light Mode" title="Toggle Dark/Light Theme">
                    <span class="theme-icon-dark" title="Switch to Light Mode"><?= svg_icon('sun', '', 18) ?></span>
                    <span class="theme-icon-light" title="Switch to Dark Mode"><?= svg_icon('moon', '', 18) ?></span>
                </button>

                <!-- Notification Center Bell & Dropdown -->
                <div class="notif-wrapper" id="notifWrapper">
                    <button type="button" class="notif-btn" id="notifBtn" onclick="toggleNotifications(event)" aria-label="Notifications" title="Notifications" aria-expanded="false">
                        <?= svg_icon('bell', '', 19) ?>
                        <span class="notif-badge" id="notifBadge" style="display: none;">0</span>
                        <span class="notif-ping" id="notifPing" style="display: none;"></span>
                    </button>

                    <!-- Notification Popover Menu -->
                    <div class="notif-dropdown" id="notifDropdown" onclick="event.stopPropagation()">
                        <div class="notif-dropdown-header">
                            <div class="notif-header-title">
                                <h4>Notifications</h4>
                                <span class="notif-counter-pill" id="notifCounterPill">0 new</span>
                            </div>
                            <div class="notif-header-actions">
                                <button type="button" class="btn-text-action" onclick="markAllNotificationsAsRead()" title="Mark all as read">
                                    <?= svg_icon('check', '', 13) ?>
                                    <span>Mark all read</span>
                                </button>
                            </div>
                        </div>

                        <!-- Filter Tabs -->
                        <div class="notif-tabs">
                            <button type="button" class="notif-tab active" data-tab="all" onclick="switchNotifTab('all', this)">All</button>
                            <button type="button" class="notif-tab" data-tab="warranty" onclick="switchNotifTab('warranty', this)">Warranties</button>
                            <button type="button" class="notif-tab" data-tab="activity" onclick="switchNotifTab('activity', this)">Activity</button>
                        </div>

                        <!-- Notification List -->
                        <div class="notif-list" id="notifList">
                            <!-- Populated dynamically via app.js -->
                        </div>

                        <div class="notif-footer">
                            <a href="<?= BASE_URL ?>/warranties.php" class="notif-footer-link">
                                <span>Open Warranty Sentinel</span>
                                <?= svg_icon('arrow-right', '', 13) ?>
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Add Receipt Action Button -->
                <a class="btn btn-primary btn-sm" href="<?= BASE_URL ?>/add_receipt.php">
                    <?= svg_icon('plus', '', 16) ?>
                    <span>New Receipt</span>
                </a>
            </div>
        </header>

        <main class="content">
            <?php if ($flash): ?>
                <div class="alert <?= e($flash['type']) ?>" role="alert">
                    <?= svg_icon($flash['type'] === 'success' ? 'check-circle' : 'alert-triangle', '', 18) ?>
                    <span><?= e($flash['message']) ?></span>
                    <button type="button" class="alert-dismiss" onclick="dismissAlert(this)" aria-label="Close">
                        <?= svg_icon('x', '', 16) ?>
                    </button>
                </div>
            <?php endif; ?>

<?php else: ?>
    <!-- Public Visitor Navigation -->
    <header class="public-nav">
        <a class="public-brand" href="<?= BASE_URL ?>/index.php">
            <div class="brand-mark">
                <?= svg_icon('receipt', '', 20) ?>
            </div>
            <div>
                <strong><?= e(APP_NAME) ?></strong>
                <small><?= e(APP_TAGLINE) ?></small>
            </div>
        </a>

        <div class="public-links">
            <!-- Theme Toggle Button -->
            <button type="button" class="theme-toggle-btn" onclick="toggleTheme()" aria-label="Toggle Dark/Light Mode" title="Toggle Theme">
                <span class="theme-icon-dark" title="Switch to Light Mode"><?= svg_icon('sun', '', 18) ?></span>
                <span class="theme-icon-light" title="Switch to Dark Mode"><?= svg_icon('moon', '', 18) ?></span>
            </button>
            <a href="<?= BASE_URL ?>/login.php" class="btn btn-ghost btn-sm">Sign In</a>
            <a href="<?= BASE_URL ?>/register.php" class="btn btn-primary btn-sm">Get Started Free</a>
        </div>
    </header>

    <main>
        <?php if ($flash): ?>
            <div style="max-width: 1200px; margin: 20px auto 0; padding: 0 20px;">
                <div class="alert <?= e($flash['type']) ?>" role="alert">
                    <?= svg_icon($flash['type'] === 'success' ? 'check-circle' : 'alert-triangle', '', 18) ?>
                    <span><?= e($flash['message']) ?></span>
                    <button type="button" class="alert-dismiss" onclick="dismissAlert(this)" aria-label="Close">
                        <?= svg_icon('x', '', 16) ?>
                    </button>
                </div>
            </div>
        <?php endif; ?>
<?php endif; ?>