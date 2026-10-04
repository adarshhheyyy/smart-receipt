<?php
/**
 * SmartReceipt - Global Footer Template
 */
?>

<?php if (is_logged_in()): ?>
        </main>
        <footer class="footer">
            <div style="display: flex; align-items: center; justify-content: space-between; max-width: 1400px; margin: 0 auto; flex-wrap: wrap; gap: 10px;">
                <span><?= e(APP_NAME) ?> &bull; <?= e(APP_TAGLINE) ?> &copy; <?= date('Y') ?></span>
                <span style="color: var(--text-subtle); font-size: 11.5px;">Secure Digital Records Vault</span>
            </div>
        </footer>
    </div>
</div>
<?php else: ?>
    </main>
    <footer class="public-footer">
        <div style="max-width: 1200px; margin: 0 auto; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px;">
            <span><strong><?= e(APP_NAME) ?></strong> &bull; <?= e(APP_TAGLINE) ?> &copy; <?= date('Y') ?></span>
            <span>Fast, private, and effortless purchase tracking.</span>
        </div>
    </footer>
<?php endif; ?>

<!-- Receipt Image Lightbox Modal -->
<div class="modal-overlay" id="receiptModal" onclick="closeReceiptModal()">
    <div class="modal-content" onclick="event.stopPropagation()">
        <div class="modal-header">
            <div>
                <h4 id="modalReceiptTitle">Receipt Preview</h4>
                <small id="modalReceiptNotes"></small>
            </div>
            <button type="button" class="icon-btn" onclick="closeReceiptModal()" aria-label="Close Preview">
                <?= svg_icon('x', '', 18) ?>
            </button>
        </div>
        <div class="modal-body">
            <img id="modalReceiptImg" src="" alt="Receipt Preview">
        </div>
    </div>
</div>

<!-- Floating Toast Notification System Container -->
<div class="toast-container" id="toastContainer" aria-live="polite" aria-atomic="true"></div>

<script src="<?= BASE_URL ?>/assets/js/app.js"></script>
</body>
</html>