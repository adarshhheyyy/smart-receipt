<?php
/**
 * SmartReceipt - Main User Dashboard
 */

require_once __DIR__ . '/config/db.php';
require_login();

$userId = (int)$_SESSION['user_id'];

// 1. Total receipts
$stmt = $pdo->prepare("SELECT COUNT(*) FROM receipts WHERE user_id = ?");
$stmt->execute([$userId]);
$totalReceipts = (int)$stmt->fetchColumn();

// 2. Monthly spending (current calendar month)
$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(amount), 0) 
    FROM receipts 
    WHERE user_id = ? 
      AND purchase_date >= DATE_FORMAT(CURDATE(), '%Y-%m-01')
");
$stmt->execute([$userId]);
$monthlyExpense = (float)$stmt->fetchColumn();

// 3. Warranty metrics
$stmt = $pdo->prepare("
    SELECT COUNT(*) 
    FROM warranties w 
    JOIN receipts r ON r.id = w.receipt_id 
    WHERE r.user_id = ? 
      AND w.expiry_date >= CURDATE()
");
$stmt->execute([$userId]);
$activeWarranties = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("
    SELECT COUNT(*) 
    FROM warranties w 
    JOIN receipts r ON r.id = w.receipt_id 
    WHERE r.user_id = ? 
      AND w.expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
");
$stmt->execute([$userId]);
$expiringSoon = (int)$stmt->fetchColumn();

// 4. Category breakdown
$stmt = $pdo->prepare("
    SELECT category, COALESCE(SUM(amount), 0) AS total, COUNT(*) AS count
    FROM receipts 
    WHERE user_id = ? 
    GROUP BY category 
    ORDER BY total DESC
");
$stmt->execute([$userId]);
$categories = $stmt->fetchAll();

$maxCategoryTotal = 1.0;
foreach ($categories as $catRow) {
    $maxCategoryTotal = max($maxCategoryTotal, (float)$catRow['total']);
}

// 5. Warranty watch list (up to 4 upcoming expirations)
$stmt = $pdo->prepare("
    SELECT w.*, r.shop_name, r.category 
    FROM warranties w 
    JOIN receipts r ON r.id = w.receipt_id 
    WHERE r.user_id = ? 
      AND w.expiry_date >= CURDATE() 
    ORDER BY w.expiry_date ASC 
    LIMIT 4
");
$stmt->execute([$userId]);
$warrantyWatch = $stmt->fetchAll();

// 6. Recent receipts list (up to 6 latest)
$stmt = $pdo->prepare("
    SELECT r.*, w.product_name, w.expiry_date, w.warranty_start 
    FROM receipts r 
    LEFT JOIN warranties w ON w.receipt_id = r.id 
    WHERE r.user_id = ? 
    ORDER BY r.purchase_date DESC, r.id DESC 
    LIMIT 6
");
$stmt->execute([$userId]);
$recentReceipts = $stmt->fetchAll();

// 7. Warranties expiring soon for proactive alert banner
$urgentWarranties = [];
if ($expiringSoon > 0) {
    $stmtUrgent = $pdo->prepare("
        SELECT w.*, r.shop_name, r.category 
        FROM warranties w 
        JOIN receipts r ON r.id = w.receipt_id 
        WHERE r.user_id = ? 
          AND w.expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
        ORDER BY w.expiry_date ASC 
        LIMIT 2
    ");
    $stmtUrgent->execute([$userId]);
    $urgentWarranties = $stmtUrgent->fetchAll();
}

$page_title = 'Dashboard';
require_once __DIR__ . '/includes/header.php';
?>

<!-- Welcome Banner -->
<div class="welcome-banner">
    <div class="welcome-text">
        <span class="eyebrow">Financial & Asset Intelligence</span>
        <h2>Welcome back, <?= e(explode(' ', trim(current_user_name()))[0]) ?> 👋</h2>
        <p>Here is an overview of your recent expenses and product warranty statuses.</p>
    </div>
    <div style="display: flex; gap: 10px;">
        <a class="btn btn-primary" href="<?= BASE_URL ?>/add_receipt.php">
            <?= svg_icon('plus', '', 16) ?>
            <span>Add Receipt</span>
        </a>
    </div>
</div>

<?php if (!empty($urgentWarranties)): ?>
    <!-- Proactive Warranty Expiry Notification Banner -->
    <div class="warranty-sentinel-banner" role="alert">
        <div class="sentinel-banner-icon">
            <?= svg_icon('alert-triangle', '', 22) ?>
        </div>
        <div class="sentinel-banner-content">
            <div class="sentinel-banner-header">
                <strong>Warranty Expiration Alert: <?= $expiringSoon ?> <?= $expiringSoon === 1 ? 'item requires' : 'items require' ?> attention</strong>
                <span class="sentinel-badge-urgent">Expires Soon</span>
            </div>
            <div class="sentinel-items-list">
                <?php foreach ($urgentWarranties as $uw): 
                    $uStat = get_warranty_status($uw['expiry_date']);
                ?>
                    <div class="sentinel-item-chip">
                        <span class="sentinel-item-name"><?= e($uw['product_name']) ?></span>
                        <span class="sentinel-item-shop">from <?= e($uw['shop_name']) ?></span>
                        <span class="status-badge <?= e($uStat['badge_class']) ?>"><?= e($uStat['label']) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="sentinel-banner-actions">
            <a href="<?= BASE_URL ?>/warranties.php?status=soon" class="btn btn-warning-action btn-sm">
                <span>Inspect Item</span>
                <?= svg_icon('arrow-right', '', 14) ?>
            </a>
        </div>
    </div>
<?php endif; ?>

<!-- Key Stat Cards -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-header">
            <span class="stat-title">Total Receipts</span>
            <div class="stat-icon indigo">
                <?= svg_icon('receipt', '', 20) ?>
            </div>
        </div>
        <div class="stat-value"><?= number_format($totalReceipts) ?></div>
        <div class="stat-meta">
            <?= svg_icon('check-circle', '', 14) ?>
            <span>Indexed in your vault</span>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-header">
            <span class="stat-title">This Month's Spend</span>
            <div class="stat-icon emerald">
                <?= svg_icon('trending-up', '', 20) ?>
            </div>
        </div>
        <div class="stat-value"><?= format_currency($monthlyExpense) ?></div>
        <div class="stat-meta">
            <?= svg_icon('calendar', '', 14) ?>
            <span>Current calendar month</span>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-header">
            <span class="stat-title">Active Warranties</span>
            <div class="stat-icon sky">
                <?= svg_icon('shield-check', '', 20) ?>
            </div>
        </div>
        <div class="stat-value"><?= number_format($activeWarranties) ?></div>
        <div class="stat-meta">
            <?= svg_icon('shield', '', 14) ?>
            <span>Products under coverage</span>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-header">
            <span class="stat-title">Warranty Alerts</span>
            <div class="stat-icon amber">
                <?= svg_icon('clock', '', 20) ?>
            </div>
        </div>
        <div class="stat-value"><?= number_format($expiringSoon) ?></div>
        <div class="stat-meta" style="<?= $expiringSoon > 0 ? 'color: var(--warning-text); font-weight: 700;' : '' ?>">
            <?= svg_icon('alert-triangle', '', 14) ?>
            <span><?= $expiringSoon > 0 ? 'Expiring in next 30 days!' : 'No urgent expirations' ?></span>
        </div>
    </div>
</div>

<!-- Dashboard 2-column Grid -->
<div class="dashboard-grid">
    <!-- Spending by Category -->
    <section class="panel">
        <div class="panel-head">
            <div>
                <h3>Spending by Category</h3>
                <p>Distribution of your purchase expenses across recorded categories.</p>
            </div>
            <a href="<?= BASE_URL ?>/receipts.php" class="panel-link">
                <span>View all</span>
                <?= svg_icon('arrow-right', '', 14) ?>
            </a>
        </div>

        <?php if (!empty($categories)): ?>
            <div class="category-bars">
                <?php foreach ($categories as $cat): 
                    $catTotal = (float)$cat['total'];
                    $pct = max(5, round(($catTotal / $maxCategoryTotal) * 100));
                    $catMeta = RECEIPT_CATEGORIES[$cat['category']] ?? ['color' => '#6366f1', 'icon' => 'box'];
                ?>
                    <div class="category-bar-item">
                        <div class="category-bar-header">
                            <span class="category-bar-name">
                                <?= render_category_badge($cat['category']) ?>
                                <small style="color: var(--text-muted); font-size: 11px;">(<?= $cat['count'] ?>)</small>
                            </span>
                            <span class="category-bar-amount"><?= format_currency($catTotal) ?></span>
                        </div>
                        <div class="bar-track">
                            <div class="bar-fill" style="width: <?= $pct ?>%; background-color: <?= e($catMeta['color']) ?>;"></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <div class="empty-icon">
                    <?= svg_icon('receipt', '', 28) ?>
                </div>
                <h3>No spending records yet</h3>
                <p>Add your first purchase receipt to unlock automatic spending categorization.</p>
                <a class="btn btn-secondary btn-sm" href="<?= BASE_URL ?>/add_receipt.php">Add a receipt</a>
            </div>
        <?php endif; ?>
    </section>

    <!-- Warranty Watchlist -->
    <section class="panel">
        <div class="panel-head">
            <div>
                <h3>Warranty Sentinel</h3>
                <p>Track upcoming warranty coverage expirations.</p>
            </div>
            <a href="<?= BASE_URL ?>/warranties.php" class="panel-link">
                <span>Track all</span>
                <?= svg_icon('arrow-right', '', 14) ?>
            </a>
        </div>

        <?php if (!empty($warrantyWatch)): ?>
            <div class="warranty-watch-list">
                <?php foreach ($warrantyWatch as $w): 
                    $wStatus = get_warranty_status($w['expiry_date']);
                ?>
                    <div class="warranty-mini-card">
                        <div class="warranty-mini-info">
                            <strong class="warranty-mini-title"><?= e($w['product_name']) ?></strong>
                            <span class="warranty-mini-shop"><?= e($w['shop_name']) ?> &bull; Expires <?= format_date($w['expiry_date']) ?></span>
                        </div>
                        <span class="status-badge <?= e($wStatus['badge_class']) ?>">
                            <?= e($wStatus['label']) ?>
                        </span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state" style="padding: 28px 16px;">
                <div class="empty-icon" style="width: 48px; height: 48px; margin-bottom: 12px;">
                    <?= svg_icon('shield-check', '', 22) ?>
                </div>
                <h3>No active warranties</h3>
                <p>When you record appliances or electronics with guarantees, they will be protected here.</p>
                <a class="btn btn-secondary btn-sm" href="<?= BASE_URL ?>/add_receipt.php">Record purchase</a>
            </div>
        <?php endif; ?>
    </section>
</div>

<!-- Recent Receipts Panel -->
<section class="panel">
    <div class="panel-head">
        <div>
            <h3>Recent Receipts</h3>
            <p>Your latest saved store bills and invoices.</p>
        </div>
        <a href="<?= BASE_URL ?>/receipts.php" class="panel-link">
            <span>View all receipts (<?= $totalReceipts ?>)</span>
            <?= svg_icon('arrow-right', '', 14) ?>
        </a>
    </div>

    <?php if (!empty($recentReceipts)): ?>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Store / Item</th>
                        <th>Category</th>
                        <th>Date</th>
                        <th>Amount</th>
                        <th>Warranty</th>
                        <th style="text-align: right;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentReceipts as $receipt): 
                        $hasWarranty = !empty($receipt['expiry_date']);
                        $wStatus = $hasWarranty ? get_warranty_status($receipt['expiry_date']) : null;
                    ?>
                        <tr>
                            <td>
                                <div class="receipt-item-cell">
                                    <div class="receipt-thumb-wrap" 
                                         <?php if (!empty($receipt['image_path'])): ?>
                                            onclick="openReceiptModal('<?= BASE_URL . '/' . e($receipt['image_path']) ?>', '<?= e($receipt['shop_name']) ?>', '<?= e($receipt['notes'] ?? '') ?>')"
                                            title="Click to view full receipt"
                                         <?php endif; ?>>
                                        <?php if (!empty($receipt['image_path'])): ?>
                                            <img src="<?= BASE_URL . '/' . e($receipt['image_path']) ?>" alt="Thumbnail">
                                        <?php else: ?>
                                            <?= svg_icon('receipt', '', 20) ?>
                                        <?php endif; ?>
                                    </div>
                                    <div class="receipt-item-meta">
                                        <strong><?= e($receipt['shop_name']) ?></strong>
                                        <span><?= !empty($receipt['notes']) ? e($receipt['notes']) : 'No notes attached' ?></span>
                                    </div>
                                </div>
                            </td>
                            <td><?= render_category_badge($receipt['category']) ?></td>
                            <td><?= format_date($receipt['purchase_date']) ?></td>
                            <td><strong class="amount-text"><?= format_currency($receipt['amount']) ?></strong></td>
                            <td>
                                <?php if ($wStatus): ?>
                                    <span class="status-badge <?= e($wStatus['badge_class']) ?>">
                                        <?= e($wStatus['label']) ?>
                                    </span>
                                <?php else: ?>
                                    <span style="color: var(--text-subtle); font-size: 12px;">—</span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align: right;">
                                <div class="actions-cell" style="justify-content: flex-end;">
                                    <a class="icon-btn" href="<?= BASE_URL ?>/edit_receipt.php?id=<?= (int)$receipt['id'] ?>" title="Edit record">
                                        <?= svg_icon('edit', '', 16) ?>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="empty-state">
            <div class="empty-icon">
                <?= svg_icon('receipt', '', 28) ?>
            </div>
            <h3>Your digital vault is empty</h3>
            <p>Begin organizing your personal finances by uploading your first receipt now.</p>
            <a class="btn btn-primary" href="<?= BASE_URL ?>/add_receipt.php">
                <?= svg_icon('plus', '', 16) ?>
                <span>Add First Receipt</span>
            </a>
        </div>
    <?php endif; ?>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>