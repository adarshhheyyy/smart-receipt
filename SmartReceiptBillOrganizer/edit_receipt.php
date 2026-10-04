<?php
/**
 * SmartReceipt - Edit Receipt Record
 */

require_once __DIR__ . '/config/db.php';
require_login();

$userId = (int)$_SESSION['user_id'];
$id = (int)($_GET['id'] ?? 0);

// Load receipt with associated warranty
$stmt = $pdo->prepare("
    SELECT r.*, w.id AS warranty_id, w.product_name, w.warranty_start, w.expiry_date 
    FROM receipts r 
    LEFT JOIN warranties w ON w.receipt_id = r.id 
    WHERE r.id = ? AND r.user_id = ?
");
$stmt->execute([$id, $userId]);
$receipt = $stmt->fetch();

if (!$receipt) {
    flash('error', 'The requested receipt record could not be found.');
    redirect(BASE_URL . '/receipts.php');
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $shopName       = trim($_POST['shop_name'] ?? '');
    $purchaseDate   = trim($_POST['purchase_date'] ?? '');
    $amount         = (float)($_POST['amount'] ?? 0);
    $category       = trim($_POST['category'] ?? '');
    $notes          = trim($_POST['notes'] ?? '');
    $productName    = trim($_POST['product_name'] ?? '');
    $warrantyStart  = trim($_POST['warranty_start'] ?? '');
    $warrantyExpiry = trim($_POST['warranty_expiry'] ?? '');

    // Validation
    if ($shopName === '') {
        $errors[] = 'Shop or merchant name is required.';
    }
    if ($purchaseDate === '') {
        $errors[] = 'Purchase date is required.';
    }
    if ($amount <= 0) {
        $errors[] = 'Amount must be greater than zero.';
    }
    if (!array_key_exists($category, RECEIPT_CATEGORIES)) {
        $errors[] = 'Please select a valid category.';
    }

    $hasWarranty = ($productName !== '' || $warrantyExpiry !== '');
    if ($hasWarranty) {
        if ($productName === '') {
            $errors[] = 'Product name is required for warranty tracking.';
        }
        if ($warrantyExpiry === '') {
            $errors[] = 'Warranty expiry date is required.';
        } elseif ($warrantyStart !== '' && $warrantyExpiry < $warrantyStart) {
            $errors[] = 'Warranty expiry date cannot precede the coverage start date.';
        }
    }

    // Process new image upload if provided
    $imagePath = $receipt['image_path'];
    if (!empty($_FILES['receipt_image']['name']) && $_FILES['receipt_image']['error'] === UPLOAD_ERR_OK) {
        $tmpPath = $_FILES['receipt_image']['tmp_name'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $tmpPath);
        finfo_close($finfo);

        if (!isset(ALLOWED_IMAGE_TYPES[$mime])) {
            $errors[] = 'Only JPG, PNG, and WEBP image files are allowed.';
        } elseif ($_FILES['receipt_image']['size'] > MAX_UPLOAD_BYTES) {
            $errors[] = 'Uploaded image must be under 3 MB.';
        } else {
            $ext = ALLOWED_IMAGE_TYPES[$mime];
            $newFileName = 'receipt_' . bin2hex(random_bytes(16)) . '.' . $ext;
            $destination = UPLOAD_DIR . $newFileName;

            if (move_uploaded_file($tmpPath, $destination)) {
                // Delete previous file if exists
                if ($imagePath && file_exists(__DIR__ . '/' . $imagePath)) {
                    @unlink(__DIR__ . '/' . $imagePath);
                }
                $imagePath = 'uploads/' . $newFileName;
            } else {
                $errors[] = 'Failed to save the new image upload.';
            }
        }
    }

    // Persist changes
    if (empty($errors)) {
        $pdo->beginTransaction();
        try {
            $stmtUpdate = $pdo->prepare("
                UPDATE receipts 
                SET shop_name = ?, purchase_date = ?, amount = ?, category = ?, notes = ?, image_path = ? 
                WHERE id = ? AND user_id = ?
            ");
            $stmtUpdate->execute([
                $shopName,
                $purchaseDate,
                $amount,
                $category,
                $notes,
                $imagePath,
                $id,
                $userId
            ]);

            // Handle warranties update
            if ($hasWarranty) {
                $wStart = $warrantyStart ?: $purchaseDate;
                if (!empty($receipt['warranty_id'])) {
                    $stmtW = $pdo->prepare("
                        UPDATE warranties 
                        SET product_name = ?, warranty_start = ?, expiry_date = ? 
                        WHERE id = ?
                    ");
                    $stmtW->execute([$productName, $wStart, $warrantyExpiry, (int)$receipt['warranty_id']]);
                } else {
                    $stmtW = $pdo->prepare("
                        INSERT INTO warranties (receipt_id, product_name, warranty_start, expiry_date) 
                        VALUES (?, ?, ?, ?)
                    ");
                    $stmtW->execute([$id, $productName, $wStart, $warrantyExpiry]);
                }
            } elseif (!empty($receipt['warranty_id'])) {
                $stmtDelW = $pdo->prepare("DELETE FROM warranties WHERE id = ?");
                $stmtDelW->execute([(int)$receipt['warranty_id']]);
            }

            $pdo->commit();
            flash('success', 'Receipt details updated successfully.');
            redirect(BASE_URL . '/receipts.php');
        } catch (Throwable $e) {
            $pdo->rollBack();
            $errors[] = 'Could not update receipt. Please try again.';
        }
    }

    // Keep modified values in form on error
    $receipt = array_merge($receipt, [
        'shop_name'       => $shopName,
        'purchase_date'   => $purchaseDate,
        'amount'          => $amount,
        'category'        => $category,
        'notes'           => $notes,
        'product_name'    => $productName,
        'warranty_start'  => $warrantyStart,
        'expiry_date'     => $warrantyExpiry,
        'image_path'      => $imagePath,
    ]);
}

$page_title = 'Edit Receipt';
require_once __DIR__ . '/includes/header.php';
?>

<!-- Header -->
<div class="welcome-banner" style="margin-bottom: 24px;">
    <div class="welcome-text">
        <span class="eyebrow">Modify Record #<?= (int)$receipt['id'] ?></span>
        <h2>Edit Receipt</h2>
        <p>Update purchase notes, replace receipt photos, or manage warranty coverage dates.</p>
    </div>
    <div style="display: flex; gap: 10px;">
        <a class="btn btn-ghost" href="<?= BASE_URL ?>/receipts.php">
            <?= svg_icon('arrow-right', 'rotate-180', 16) ?>
            <span>Back to Receipts</span>
        </a>
    </div>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert error permanent" role="alert">
        <?= svg_icon('alert-triangle', '', 20) ?>
        <div>
            <strong>Please resolve the following:</strong>
            <ul style="margin: 6px 0 0 18px;">
                <?php foreach ($errors as $err): ?>
                    <li><?= e($err) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
<?php endif; ?>

<!-- Form Shell -->
<form method="post" enctype="multipart/form-data" class="form-shell" id="editReceiptForm">
    <?= csrf_input() ?>

    <!-- Step 1: Purchase Details -->
    <section class="form-section">
        <div class="section-head">
            <div class="step-num">01</div>
            <div class="section-head-text">
                <h3>Purchase Details</h3>
                <p>Modify vendor name, date, cost, or description.</p>
            </div>
        </div>

        <div class="form-grid two-col">
            <div class="form-group">
                <label for="shopName">Shop / Merchant Name <span class="required">*</span></label>
                <input type="text" id="shopName" name="shop_name" class="form-control" 
                       value="<?= e($receipt['shop_name']) ?>" required>
            </div>

            <div class="form-group">
                <label for="purchaseDate">Purchase Date <span class="required">*</span></label>
                <input type="date" id="purchaseDate" name="purchase_date" class="form-control" 
                       value="<?= e($receipt['purchase_date']) ?>" required>
            </div>

            <div class="form-group">
                <label for="amount">Total Amount (<?= CURRENCY_SYMBOL ?>) <span class="required">*</span></label>
                <input type="number" id="amount" name="amount" class="form-control" 
                       step="0.01" min="0.01" value="<?= e($receipt['amount']) ?>" required>
            </div>

            <div class="form-group">
                <label for="category">Category <span class="required">*</span></label>
                <select id="category" name="category" class="form-control" required>
                    <?php foreach (RECEIPT_CATEGORIES as $catName => $catMeta): ?>
                        <option value="<?= e($catName) ?>" <?= $receipt['category'] === $catName ? 'selected' : '' ?>>
                            <?= e($catName) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group full-width">
                <label for="notes">Purchase Notes / Description</label>
                <textarea id="notes" name="notes" class="form-control" rows="3"><?= e($receipt['notes']) ?></textarea>
            </div>
        </div>
    </section>

    <!-- Step 2: Receipt Image -->
    <section class="form-section">
        <div class="section-head">
            <div class="step-num">02</div>
            <div class="section-head-text">
                <h3>Receipt Image</h3>
                <p>View current receipt scan or upload a replacement file.</p>
            </div>
        </div>

        <?php if (!empty($receipt['image_path'])): ?>
            <div style="display: flex; align-items: center; gap: 16px; margin-bottom: 20px; padding: 14px; background: var(--bg-base); border-radius: var(--radius-md); border: 1px solid var(--border-color);">
                <div class="receipt-thumb-wrap" style="width: 64px; height: 64px;" 
                     onclick="openReceiptModal('<?= BASE_URL . '/' . e($receipt['image_path']) ?>', '<?= e($receipt['shop_name']) ?>', '<?= e($receipt['notes'] ?? '') ?>')">
                    <img src="<?= BASE_URL . '/' . e($receipt['image_path']) ?>" alt="Current Receipt">
                </div>
                <div>
                    <strong style="display: block; font-size: 13.5px;">Current Saved Image</strong>
                    <span style="font-size: 12px; color: var(--text-muted);">Click image to view full-size lightbox. Upload below only if you want to replace it.</span>
                </div>
            </div>
        <?php endif; ?>

        <div class="upload-dropzone">
            <input type="file" id="receiptImageInput" name="receipt_image" accept="image/jpeg,image/png,image/webp" onchange="previewReceipt(this)">
            
            <div id="uploadPrompt" style="display: flex; flex-direction: column; align-items: center; gap: 8px;">
                <div class="upload-icon-box">
                    <?= svg_icon('upload-cloud', '', 26) ?>
                </div>
                <div class="upload-title"><?= !empty($receipt['image_path']) ? 'Choose replacement image' : 'Upload receipt image' ?></div>
                <div class="upload-hint">JPG, PNG, or WEBP up to 3 MB</div>
            </div>

            <div id="previewContainer" class="preview-container">
                <img id="receiptPreview" src="" alt="New Receipt Preview">
                <div style="margin-top: 8px;">
                    <button type="button" class="btn btn-secondary btn-sm" onclick="clearReceiptImage(event)">
                        <?= svg_icon('trash', '', 14) ?>
                        <span>Cancel Replacement</span>
                    </button>
                </div>
            </div>
        </div>
    </section>

    <!-- Step 3: Warranty Sentinel -->
    <section class="form-section">
        <div class="section-head">
            <div class="step-num">03</div>
            <div class="section-head-text">
                <div style="display: flex; align-items: center; gap: 8px;">
                    <h3>Warranty Sentinel</h3>
                    <span class="category-pill" style="--cat-color: #64748b; --cat-bg: #f1f5f9; font-size: 11px;">Optional</span>
                </div>
                <p>Leave blank if this item does not have a warranty.</p>
            </div>
        </div>

        <div class="form-grid two-col">
            <div class="form-group full-width">
                <label for="productName">Product Name</label>
                <input type="text" id="productName" name="product_name" class="form-control" 
                       value="<?= e($receipt['product_name'] ?? '') ?>" placeholder="e.g. Dell Monitor, Whirlpool Refrigerator">
            </div>

            <div class="form-group">
                <label for="warrantyStart">Coverage Start Date</label>
                <input type="date" id="warrantyStart" name="warranty_start" class="form-control" 
                       value="<?= e($receipt['warranty_start'] ?? $receipt['purchase_date']) ?>">
            </div>

            <div class="form-group">
                <label for="warrantyExpiry">Coverage Expiration Date</label>
                <input type="date" id="warrantyExpiry" name="warranty_expiry" class="form-control" 
                       value="<?= e($receipt['expiry_date'] ?? '') ?>">

                <!-- Quick Presets -->
                <div class="warranty-presets">
                    <span style="font-size: 11.5px; color: var(--text-muted); align-self: center;">Quick Add:</span>
                    <button type="button" class="preset-chip" onclick="setWarrantyPreset(6)">+6 Months</button>
                    <button type="button" class="preset-chip" onclick="setWarrantyPreset(12)">+1 Year</button>
                    <button type="button" class="preset-chip" onclick="setWarrantyPreset(24)">+2 Years</button>
                    <button type="button" class="preset-chip" onclick="setWarrantyPreset(36)">+3 Years</button>
                </div>
            </div>
        </div>
    </section>

    <!-- Actions Bar -->
    <div class="form-actions-bar">
        <a class="btn btn-ghost" href="<?= BASE_URL ?>/receipts.php">Cancel</a>
        <button class="btn btn-primary btn-lg" type="submit">
            <?= svg_icon('check', '', 18) ?>
            <span>Save Changes</span>
        </button>
    </div>
</form>

<?php require_once __DIR__ . '/includes/footer.php'; ?>