<?php
/**
 * SmartReceipt - Warranty Sentinel Tracker
 */

require_once __DIR__ . '/config/db.php';
require_login();

$userId = (int)$_SESSION['user_id'];
$filterStatus = trim($_GET['status'] ?? 'all');

// Fetch all warranties for current user
$stmt = $pdo->prepare("
    SELECT w.*, r.shop_name, r.purchase_date, r.amount, r.image_path, r.category 
    FROM warranties w 
    JOIN receipts r ON r.id = w.receipt_id 
    WHERE r.user_id = ? 
    ORDER BY w.expiry_date ASC
");
$stmt->execute([$userId]);
$allWarranties = $stmt->fetchAll();

// Calculate counts & filter
$activeCount  = 0;
$soonCount    = 0;
$expiredCount = 0;
$filteredList = [];

foreach ($allWarranties as $w) {
    $info = get_warranty_status($w['expiry_date'], $w['warranty_start']);
    
    if ($info['state'] === 'expired') {
        $expiredCount++;
    } elseif ($info['days'] <= 30) {
        $activeCount++;
        $soonCount++;
    } else {
        $activeCount++;
    }

    if ($filterStatus === 'active' && $info['state'] === 'expired') {
        continue;
    }
    if ($filterStatus === 'soon' && ($info['state'] === 'expired' || $info['days'] > 30)) {
        continue;
    }
    if ($filterStatus === 'expired' && $info['state'] !== 'expired') {
        continue;
    }

    $w['meta'] = $info;
    $filteredList[] = $w;
}

$page_title = 'Warranty Tracker';
require_once __DIR__ . '/includes/header.php';
?>

<!-- Header -->
<div class="welcome-banner" style="margin-bottom: 24px;">
    <div class="welcome-text">
        <span class="eyebrow">Product Protection Sentinel</span>
        <h2>Warranty Tracker</h2>
        <p>Monitor manufacturer guarantees and receive timely reminders before claims expire.</p>
    </div>
    <div>
        <a class="btn btn-primary" href="<?= BASE_URL ?>/add_receipt.php">
            <?= svg_icon('plus', '', 16) ?>
            <span>Add Protected Item</span>
        </a>
    </div>
</div>

<!-- Quick Metric Cards -->
<div class="stats-grid" style="grid-template-columns: repeat(3, 1fr);">
    <a href="<?= BASE_URL ?>/warranties.php?status=active" class="stat-card" style="text-decoration: none;">
        <div class="stat-header">
            <span class="stat-title">Active Guarantees</span>
            <div class="stat-icon emerald">
                <?= svg_icon('shield-check', '', 20) ?>
            </div>
        </div>
        <div class="stat-value"><?= number_format($activeCount) ?></div>
        <div class="stat-meta">
            <?= svg_icon('check', '', 14) ?>
            <span>Currently valid coverage</span>
        </div>
    </a>

    <a href="<?= BASE_URL ?>/warranties.php?status=soon" class="stat-card" style="text-decoration: none;">
        <div class="stat-header">
            <span class="stat-title">Expiring Soon</span>
            <div class="stat-icon amber">
                <?= svg_icon('clock', '', 20) ?>
            </div>
        </div>
        <div class="stat-value"><?= number_format($soonCount) ?></div>
        <div class="stat-meta" style="<?= $soonCount > 0 ? 'color: var(--warning-text); font-weight: 700;' : '' ?>">
            <?= svg_icon('alert-triangle', '', 14) ?>
            <span>Within next 30 days</span>
        </div>
    </a>

    <a href="<?= BASE_URL ?>/warranties.php?status=expired" class="stat-card" style="text-decoration: none;">
        <div class="stat-header">
            <span class="stat-title">Expired Warranties</span>
            <div class="stat-icon" style="background: #64748b; color: #fff;">
                <?= svg_icon('x', '', 20) ?>
            </div>
        </div>
        <div class="stat-value"><?= number_format($expiredCount) ?></div>
        <div class="stat-meta">
            <?= svg_icon('calendar', '', 14) ?>
            <span>Coverage elapsed</span>
        </div>
    </a>
</div>

<!-- Warranties Panel with Filter Tabs -->
<section class="panel">
    <div class="panel-head" style="flex-wrap: wrap; gap: 14px;">
        <div>
            <h3>All Registered Products (<?= count($filteredList) ?>)</h3>
            <p>Chronologically sorted by upcoming expiration dates.</p>
        </div>

        <div style="display: flex; gap: 8px; flex-wrap: wrap;">
            <a class="btn btn-sm <?= $filterStatus === 'all' ? 'btn-dark' : 'btn-ghost' ?>" 
               href="<?= BASE_URL ?>/warranties.php?status=all">All (<?= count($allWarranties) ?>)</a>
            <a class="btn btn-sm <?= $filterStatus === 'active' ? 'btn-dark' : 'btn-ghost' ?>" 
               href="<?= BASE_URL ?>/warranties.php?status=active">Active (<?= $activeCount ?>)</a>
            <a class="btn btn-sm <?= $filterStatus === 'soon' ? 'btn-dark' : 'btn-ghost' ?>" 
               href="<?= BASE_URL ?>/warranties.php?status=soon">Expiring Soon (<?= $soonCount ?>)</a>
            <a class="btn btn-sm <?= $filterStatus === 'expired' ? 'btn-dark' : 'btn-ghost' ?>" 
               href="<?= BASE_URL ?>/warranties.php?status=expired">Expired (<?= $expiredCount ?>)</a>
        </div>
    </div>

    <?php if (!empty($filteredList)): ?>
        <div class="warranty-grid">
            <?php foreach ($filteredList as $item): 
                $meta = $item['meta'] ?? get_warranty_status($item['expiry_date'], $item['warranty_start']);
            ?>
                <article class="warranty-card">
                    <div class="warranty-card-top">
                        <span class="category-pill" style="--cat-color: #0284c7; --cat-bg: #e0f2fe;">
                            <?= svg_icon('shield', '', 14) ?>
                            <span><?= e($item['category']) ?></span>
                        </span>
                        <span class="status-badge <?= e($meta['badge_class']) ?>">
                            <?= e($meta['label']) ?>
                        </span>
                    </div>

                    <h3 class="warranty-card-title"><?= e($item['product_name']) ?></h3>
                    <div class="warranty-card-shop">
                        Purchased at <strong><?= e($item['shop_name']) ?></strong> &bull; <?= format_currency($item['amount']) ?>
                    </div>

                    <!-- Coverage Lifecycle Bar -->
                    <div class="warranty-progress-box">
                        <div class="progress-labels">
                            <span>Started: <?= format_date($item['warranty_start']) ?></span>
                            <span>Expires: <?= format_date($item['expiry_date']) ?></span>
                        </div>
                        <div class="progress-track" title="<?= $meta['percent_elapsed'] ?>% elapsed">
                            <div class="progress-fill" style="width: <?= $meta['percent_elapsed'] ?>%; background: <?= $meta['state'] === 'expired' ? '#94a3b8' : ($meta['days'] <= 30 ? '#f59e0b' : '#10b981') ?>;"></div>
                        </div>
                    </div>

                    <div class="warranty-card-footer">
                        <span style="font-weight: 600; color: <?= $meta['days'] <= 30 && $meta['days'] >= 0 ? 'var(--warning-text)' : 'var(--text-muted)' ?>;">
                            <?php if ($meta['days'] < 0): ?>
                                Expired <?= abs($meta['days']) ?> days ago
                            <?php elseif ($meta['days'] === 0): ?>
                                Coverage lapses today!
                            <?php else: ?>
                                <?= $meta['days'] ?> days remaining
                            <?php endif; ?>
                        </span>
                        
                        <div style="display: flex; gap: 8px;">
                            <?php if (!empty($item['image_path'])): ?>
                                <button type="button" class="icon-btn" title="View receipt image"
                                        onclick="openReceiptModal('<?= BASE_URL . '/' . e($item['image_path']) ?>', '<?= e($item['shop_name']) ?>', '<?= e($item['product_name']) ?>')">
                                    <?= svg_icon('receipt', '', 15) ?>
                                </button>
                            <?php endif; ?>
                            <a class="icon-btn" href="<?= BASE_URL ?>/edit_receipt.php?id=<?= (int)$item['receipt_id'] ?>" title="Edit warranty">
                                <?= svg_icon('edit', '', 15) ?>
                            </a>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="empty-state">
            <div class="empty-icon">
                <?= svg_icon('shield-check', '', 28) ?>
            </div>
            <h3>No warranty records found</h3>
            <p><?= $filterStatus !== 'all' ? 'No warranties matched the "' . e($filterStatus) . '" filter.' : 'You have not registered any warranties yet.' ?></p>
            <div style="display: flex; gap: 10px; justify-content: center;">
                <?php if ($filterStatus !== 'all'): ?>
                    <a class="btn btn-secondary" href="<?= BASE_URL ?>/warranties.php">Show All</a>
                <?php endif; ?>
                <a class="btn btn-primary" href="<?= BASE_URL ?>/add_receipt.php">
                    <?= svg_icon('plus', '', 16) ?>
                    <span>Add Item with Warranty</span>
                </a>
            </div>
        </div>
    <?php endif; ?>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>