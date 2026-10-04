<?php
/**
 * SmartReceipt - All Receipts Archive & Search
 */

require_once __DIR__ . '/config/db.php';
require_login();

$userId = (int)$_SESSION['user_id'];

// Filter inputs
$q        = trim($_GET['q'] ?? '');
$category = trim($_GET['category'] ?? '');
$month    = trim($_GET['month'] ?? '');

// Build query
$sql = "
    SELECT r.*, w.product_name, w.expiry_date, w.warranty_start 
    FROM receipts r 
    LEFT JOIN warranties w ON w.receipt_id = r.id 
    WHERE r.user_id = ?
";
$params = [$userId];

if ($q !== '') {
    $sql .= " AND (r.shop_name LIKE ? OR r.notes LIKE ? OR w.product_name LIKE ?)";
    $like = '%' . $q . '%';
    array_push($params, $like, $like, $like);
}

if ($category !== '' && array_key_exists($category, RECEIPT_CATEGORIES)) {
    $sql .= " AND r.category = ?";
    $params[] = $category;
}

if ($month !== '') {
    $sql .= " AND DATE_FORMAT(r.purchase_date, '%Y-%m') = ?";
    $params[] = $month;
}

$sql .= " ORDER BY r.purchase_date DESC, r.id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$receipts = $stmt->fetchAll();

// Calculate total sum of filtered receipts
$totalFilteredAmount = 0.0;
foreach ($receipts as $r) {
    $totalFilteredAmount += (float)$r['amount'];
}

$isFiltered = ($q !== '' || $category !== '' || $month !== '');
$page_title = 'All Receipts';
require_once __DIR__ . '/includes/header.php';
?>

<!-- Page Header & Action -->
<div class="welcome-banner" style="margin-bottom: 24px;">
    <div class="welcome-text">
        <span class="eyebrow">Digital Records Archive</span>
        <h2>All Receipts & Bills</h2>
        <p>Search, filter, inspect, and organize your entire purchase history.</p>
    </div>
    <div>
        <a class="btn btn-primary" href="<?= BASE_URL ?>/add_receipt.php">
            <?= svg_icon('plus', '', 16) ?>
            <span>Add Receipt</span>
        </a>
    </div>
</div>

<!-- Search & Filtering Section -->
<section class="filter-panel">
    <form method="get" class="filter-form" action="<?= BASE_URL ?>/receipts.php">
        <div class="search-input-wrap">
            <?= svg_icon('search', '', 18) ?>
            <input type="search" name="q" value="<?= e($q) ?>" placeholder="Search by store name, notes, or product...">
        </div>

        <select name="category" aria-label="Filter by Category">
            <option value="">All Categories</option>
            <?php foreach (RECEIPT_CATEGORIES as $catName => $catMeta): ?>
                <option value="<?= e($catName) ?>" <?= $category === $catName ? 'selected' : '' ?>>
                    <?= e($catName) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <input type="month" name="month" value="<?= e($month) ?>" aria-label="Filter by Month">

        <button class="btn btn-dark btn-sm" type="submit">
            <?= svg_icon('filter', '', 14) ?>
            <span>Filter</span>
        </button>

        <?php if ($isFiltered): ?>
            <a class="btn btn-ghost btn-sm" href="<?= BASE_URL ?>/receipts.php">
                <?= svg_icon('x', '', 14) ?>
                <span>Reset</span>
            </a>
        <?php endif; ?>
    </form>

    <div class="filter-results-summary">
        <span>
            Found <strong><?= count($receipts) ?></strong> receipt<?= count($receipts) !== 1 ? 's' : '' ?>
            <?= $isFiltered ? ' matching your filters' : '' ?>
        </span>
        <span>
            Total: <strong class="amount-text"><?= format_currency($totalFilteredAmount) ?></strong>
        </span>
    </div>
</section>

<!-- Receipts Data Table Panel -->
<section class="panel">
    <?php if (!empty($receipts)): ?>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Store / Item</th>
                        <th>Category</th>
                        <th>Purchase Date</th>
                        <th>Amount</th>
                        <th>Warranty Status</th>
                        <th style="text-align: right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($receipts as $r): 
                        $hasWarranty = !empty($r['expiry_date']);
                        $wStatus = $hasWarranty ? get_warranty_status($r['expiry_date'], $r['warranty_start']) : null;
                    ?>
                        <tr>
                            <td>
                                <div class="receipt-item-cell">
                                    <div class="receipt-thumb-wrap"
                                         <?php if (!empty($r['image_path'])): ?>
                                            onclick="openReceiptModal('<?= BASE_URL . '/' . e($r['image_path']) ?>', '<?= e($r['shop_name']) ?>', '<?= e($r['notes'] ?? '') ?>')"
                                            title="Click to view full receipt"
                                         <?php endif; ?>>
                                        <?php if (!empty($r['image_path'])): ?>
                                            <img src="<?= BASE_URL . '/' . e($r['image_path']) ?>" alt="Receipt Image">
                                        <?php else: ?>
                                            <?= svg_icon('receipt', '', 20) ?>
                                        <?php endif; ?>
                                    </div>
                                    <div class="receipt-item-meta">
                                        <strong><?= e($r['shop_name']) ?></strong>
                                        <span><?= !empty($r['notes']) ? e($r['notes']) : 'No notes attached' ?></span>
                                    </div>
                                </div>
                            </td>
                            <td><?= render_category_badge($r['category']) ?></td>
                            <td><?= format_date($r['purchase_date']) ?></td>
                            <td><strong class="amount-text"><?= format_currency($r['amount']) ?></strong></td>
                            <td>
                                <?php if ($wStatus): ?>
                                    <div style="display: flex; flex-direction: column; gap: 4px;">
                                        <span class="status-badge <?= e($wStatus['badge_class']) ?>">
                                            <?= e($wStatus['label']) ?>
                                        </span>
                                        <?php if (!empty($r['product_name'])): ?>
                                            <small style="color: var(--text-muted); font-size: 11px;">
                                                <?= e($r['product_name']) ?>
                                            </small>
                                        <?php endif; ?>
                                    </div>
                                <?php else: ?>
                                    <span style="color: var(--text-subtle); font-size: 12px;">No warranty</span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align: right;">
                                <div class="actions-cell" style="justify-content: flex-end;">
                                    <a class="icon-btn" href="<?= BASE_URL ?>/edit_receipt.php?id=<?= (int)$r['id'] ?>" title="Edit receipt">
                                        <?= svg_icon('edit', '', 16) ?>
                                    </a>
                                    <form method="post" action="<?= BASE_URL ?>/delete_receipt.php" style="display: inline;" onsubmit="return confirmDelete(event, '<?= e(addslashes($r['shop_name'])) ?>')">
                                        <?= csrf_input() ?>
                                        <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                                        <button type="submit" class="icon-btn danger" title="Delete receipt">
                                            <?= svg_icon('trash', '', 16) ?>
                                        </button>
                                    </form>
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
                <?= svg_icon('search', '', 28) ?>
            </div>
            <h3>No receipts matched your criteria</h3>
            <p><?= $isFiltered ? 'Try clearing or adjusting your search filters to find what you need.' : 'You have not added any receipts yet.' ?></p>
            <div style="display: flex; gap: 10px; justify-content: center;">
                <?php if ($isFiltered): ?>
                    <a class="btn btn-secondary" href="<?= BASE_URL ?>/receipts.php">Clear Filters</a>
                <?php endif; ?>
                <a class="btn btn-primary" href="<?= BASE_URL ?>/add_receipt.php">
                    <?= svg_icon('plus', '', 16) ?>
                    <span>Add New Receipt</span>
                </a>
            </div>
        </div>
    <?php endif; ?>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>