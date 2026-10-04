<?php
/**
 * SmartReceipt - Add New Receipt
 */

require_once __DIR__ . '/config/db.php';
require_login();

$errors = [];
$old = [
    'shop_name'       => '',
    'purchase_date'   => date('Y-m-d'),
    'amount'          => '',
    'category'        => 'Shopping',
    'notes'           => '',
    'product_name'    => '',
    'warranty_start'  => date('Y-m-d'),
    'warranty_expiry' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    // Populate old values
    foreach ($old as $key => $default) {
        $old[$key] = trim($_POST[$key] ?? '');
    }

    $amount = (float)$old['amount'];

    // Validation rules
    if ($old['shop_name'] === '') {
        $errors[] = 'Please specify the shop or store name.';
    }
    if ($old['purchase_date'] === '') {
        $errors[] = 'Purchase date is required.';
    }
    if ($amount <= 0) {
        $errors[] = 'Receipt amount must be a positive number greater than 0.';
    }
    if (!array_key_exists($old['category'], RECEIPT_CATEGORIES)) {
        $errors[] = 'Please choose a valid spending category.';
    }

    // Receipt image handling
    $imagePath = null;
    if (!empty($_FILES['receipt_image']['name']) && $_FILES['receipt_image']['error'] === UPLOAD_ERR_OK) {
        $tmpPath = $_FILES['receipt_image']['tmp_name'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $tmpPath);
        finfo_close($finfo);

        if (!isset(ALLOWED_IMAGE_TYPES[$mime])) {
            $errors[] = 'Only JPG, PNG, and WEBP image formats are supported.';
        } elseif ($_FILES['receipt_image']['size'] > MAX_UPLOAD_BYTES) {
            $errors[] = 'Receipt image size must not exceed 3 MB.';
        } else {
            $ext = ALLOWED_IMAGE_TYPES[$mime];
            $fileName = 'receipt_' . bin2hex(random_bytes(16)) . '.' . $ext;
            $destination = UPLOAD_DIR . $fileName;

            if (move_uploaded_file($tmpPath, $destination)) {
                $imagePath = 'uploads/' . $fileName;
            } else {
                $errors[] = 'Failed to upload receipt image. Please check directory permissions.';
            }
        }
    }

    // Warranty validation
    $hasWarranty = ($old['product_name'] !== '' || $old['warranty_expiry'] !== '');
    if ($hasWarranty) {
        if ($old['product_name'] === '') {
            $errors[] = 'Product name is required when warranty details are provided.';
        }
        if ($old['warranty_expiry'] === '') {
            $errors[] = 'Warranty expiration date is required.';
        } elseif ($old['warranty_start'] !== '' && $old['warranty_expiry'] < $old['warranty_start']) {
            $errors[] = 'Warranty expiry date cannot precede the warranty start date.';
        }
    }

    // If no errors, persist in database
    if (empty($errors)) {
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("
                INSERT INTO receipts (user_id, shop_name, purchase_date, amount, category, notes, image_path) 
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                (int)$_SESSION['user_id'],
                $old['shop_name'],
                $old['purchase_date'],
                $amount,
                $old['category'],
                $old['notes'],
                $imagePath
            ]);

            $receiptId = (int)$pdo->lastInsertId();

            if ($hasWarranty) {
                $wStart = $old['warranty_start'] ?: $old['purchase_date'];
                $stmtW = $pdo->prepare("
                    INSERT INTO warranties (receipt_id, product_name, warranty_start, expiry_date) 
                    VALUES (?, ?, ?, ?)
                ");
                $stmtW->execute([
                    $receiptId,
                    $old['product_name'],
                    $wStart,
                    $old['warranty_expiry']
                ]);
            }

            $pdo->commit();
            flash('success', 'Receipt and warranty details saved successfully.');
            redirect(BASE_URL . '/receipts.php');
        } catch (Throwable $e) {
            $pdo->rollBack();
            if ($imagePath && file_exists(__DIR__ . '/' . $imagePath)) {
                unlink(__DIR__ . '/' . $imagePath);
            }
            $errors[] = 'An error occurred while saving the receipt. Please try again.';
        }
    }
}

$page_title = 'Add Receipt';
require_once __DIR__ . '/includes/header.php';
?>

<!-- Header -->
<div class="welcome-banner" style="margin-bottom: 24px;">
    <div class="welcome-text">
        <span class="eyebrow">New Entry</span>
        <h2>Record a Receipt</h2>
        <p>Log your purchase details, upload an invoice image, and set up warranty protection.</p>
    </div>
    <div>
        <a class="btn btn-ghost" href="<?= BASE_URL ?>/receipts.php">
            <?= svg_icon('arrow-right', 'rotate-180', 16) ?>
            <span>Cancel</span>
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
<form method="post" enctype="multipart/form-data" class="form-shell" id="addReceiptForm">
    <?= csrf_input() ?>

    <!-- Step 1: Purchase Details -->
    <section class="form-section">
        <div class="section-head">
            <div class="step-num">01</div>
            <div class="section-head-text">
                <h3>Purchase Details</h3>
                <p>Basic information printed on the store receipt or invoice.</p>
            </div>
        </div>

        <div class="form-grid two-col">
            <div class="form-group">
                <label for="shopName">Shop / Merchant Name <span class="required">*</span></label>
                <input type="text" id="shopName" name="shop_name" class="form-control" 
                       value="<?= e($old['shop_name']) ?>" placeholder="e.g. Croma, Apple Store, IKEA" required>
            </div>

            <div class="form-group">
                <label for="purchaseDate">Purchase Date <span class="required">*</span></label>
                <input type="date" id="purchaseDate" name="purchase_date" class="form-control" 
                       value="<?= e($old['purchase_date']) ?>" required>
            </div>

            <div class="form-group">
                <label for="amount">Total Amount (<?= CURRENCY_SYMBOL ?>) <span class="required">*</span></label>
                <input type="number" id="amount" name="amount" class="form-control" 
                       step="0.01" min="0.01" value="<?= e($old['amount']) ?>" placeholder="0.00" required>
            </div>

            <div class="form-group">
                <label for="category">Category <span class="required">*</span></label>
                <select id="category" name="category" class="form-control" required>
                    <?php foreach (RECEIPT_CATEGORIES as $catName => $catMeta): ?>
                        <option value="<?= e($catName) ?>" <?= $old['category'] === $catName ? 'selected' : '' ?>>
                            <?= e($catName) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group full-width">
                <label for="notes">Purchase Notes / Description <small style="color: var(--text-muted);">(Optional)</small></label>
                <textarea id="notes" name="notes" class="form-control" rows="3" 
                          placeholder="e.g. Order #8921, payment via credit card, includes 2 year extended plan..."><?= e($old['notes']) ?></textarea>
            </div>
        </div>
    </section>

    <!-- Step 2: Receipt Image Upload -->
    <section class="form-section">
        <div class="section-head">
            <div class="step-num">02</div>
            <div class="section-head-text">
                <h3>Receipt Image or Bill Scan</h3>
                <p>Upload a clear photograph or digital scan for safe keeping (JPG, PNG, WEBP &bull; Max 3 MB).</p>
            </div>
        </div>

        <div class="upload-dropzone">
            <input type="file" id="receiptImageInput" name="receipt_image" accept="image/jpeg,image/png,image/webp" onchange="previewReceipt(this)">
            
            <div id="uploadPrompt" style="display: flex; flex-direction: column; align-items: center; gap: 8px;">
                <div class="upload-icon-box">
                    <?= svg_icon('upload-cloud', '', 26) ?>
                </div>
                <div class="upload-title">Choose a receipt image or drag & drop here</div>
                <div class="upload-hint">Supports high-res JPG, PNG, and WEBP up to 3 MB</div>
            </div>

            <div id="previewContainer" class="preview-container">
                <img id="receiptPreview" src="" alt="Selected Receipt Preview">
                <div style="margin-top: 8px;">
                    <button type="button" class="btn btn-secondary btn-sm" onclick="clearReceiptImage(event)">
                        <?= svg_icon('trash', '', 14) ?>
                        <span>Remove Image</span>
                    </button>
                </div>
            </div>
        </div>
    </section>

    <!-- Step 3: Warranty Tracker (Optional) -->
    <section class="form-section">
        <div class="section-head">
            <div class="step-num">03</div>
            <div class="section-head-text">
                <div style="display: flex; align-items: center; gap: 8px;">
                    <h3>Warranty Sentinel</h3>
                    <span class="category-pill" style="--cat-color: #64748b; --cat-bg: #f1f5f9; font-size: 11px;">Optional</span>
                </div>
                <p>Protect valuable appliances or electronics by registering warranty expiration alerts.</p>
            </div>
        </div>

        <div class="form-grid two-col">
            <div class="form-group full-width">
                <label for="productName">Product Name</label>
                <input type="text" id="productName" name="product_name" class="form-control" 
                       value="<?= e($old['product_name']) ?>" placeholder="e.g. MacBook Pro 16, Sony Bravia TV, Dyson Vacuum">
            </div>

            <div class="form-group">
                <label for="warrantyStart">Coverage Start Date</label>
                <input type="date" id="warrantyStart" name="warranty_start" class="form-control" 
                       value="<?= e($old['warranty_start']) ?>">
            </div>

            <div class="form-group">
                <label for="warrantyExpiry">Coverage Expiration Date</label>
                <input type="date" id="warrantyExpiry" name="warranty_expiry" class="form-control" 
                       value="<?= e($old['warranty_expiry']) ?>">
                
                <!-- Quick Duration Presets -->
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
            <span>Save Receipt</span>
        </button>
    </div>
</form>

<?php require_once __DIR__ . '/includes/footer.php'; ?>