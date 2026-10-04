<?php
/**
 * SmartReceipt - Delete Receipt Action
 */

require_once __DIR__ . '/config/db.php';
require_login();

$userId = (int)$_SESSION['user_id'];
$id = (int)($_POST['id'] ?? $_GET['id'] ?? 0);

if ($id > 0) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
    }

    // Verify ownership and get image path
    $stmt = $pdo->prepare("SELECT image_path FROM receipts WHERE id = ? AND user_id = ?");
    $stmt->execute([$id, $userId]);
    $receipt = $stmt->fetch();

    if ($receipt) {
        // Delete receipt (cascade deletes warranty automatically via foreign key constraint)
        $delStmt = $pdo->prepare("DELETE FROM receipts WHERE id = ? AND user_id = ?");
        $delStmt->execute([$id, $userId]);

        // Clean up stored image file if exists
        if (!empty($receipt['image_path'])) {
            $fullPath = __DIR__ . '/' . $receipt['image_path'];
            if (file_exists($fullPath) && is_file($fullPath)) {
                @unlink($fullPath);
            }
        }

        flash('success', 'Receipt deleted successfully.');
    } else {
        flash('error', 'Receipt not found or permission denied.');
    }
}

redirect(BASE_URL . '/receipts.php');