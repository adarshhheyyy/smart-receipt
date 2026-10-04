<?php
/**
 * SmartReceipt - Landing Page
 */

require_once __DIR__ . '/config/config.php';

if (is_logged_in()) {
    redirect(BASE_URL . '/dashboard.php');
}

$page_title = 'Organize Every Receipt & Bill';
require_once __DIR__ . '/includes/header.php';
?>

<!-- Hero Section -->
<section class="hero">
    <div class="hero-copy">
        <div class="badge-pill">
            <?= svg_icon('sparkles', '', 14) ?>
            <span>SMART DIGITAL RECORDS & WARRANTY TRACKER</span>
        </div>
        <h1>Every receipt.<br><em>One intelligent vault.</em></h1>
        <p>Archive store bills, eliminate fading paper receipts, gain clear category spending breakdowns, and never lose out on a product warranty claim again.</p>
        
        <div class="hero-actions">
            <a class="btn btn-primary btn-lg" href="<?= BASE_URL ?>/register.php">
                <span>Start Free Vault</span>
                <?= svg_icon('arrow-right', '', 16) ?>
            </a>
            <a class="btn btn-ghost btn-lg" href="<?= BASE_URL ?>/login.php">
                <span>Sign In to Account</span>
            </a>
        </div>

        <div class="trust-row">
            <span><?= svg_icon('check-circle', '', 16) ?> Zero Paper Clutter</span>
            <span><?= svg_icon('shield-check', '', 16) ?> 30-Day Expiry Alerts</span>
            <span><?= svg_icon('trending-up', '', 16) ?> Category Analytics</span>
        </div>
    </div>

    <div class="mockup-wrap">
        <div class="mockup-window">
            <div class="window-dots">
                <span></span><span></span><span></span>
            </div>
            <div class="mockup-inner">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                    <div class="mock-stat-card">
                        <small>Monthly Spending</small>
                        <strong>₹ 24,680.00</strong>
                        <span style="font-size: 11px; color: #10b981; font-weight: 600;">↑ 12.4% vs last month</span>
                    </div>
                    <div class="mock-stat-card">
                        <small>Active Warranties</small>
                        <strong style="color: #0ea5e9;">8 Protected</strong>
                        <span style="font-size: 11px; color: #f59e0b; font-weight: 600;">1 expiring soon</span>
                    </div>
                </div>

                <div class="mock-stat-card" style="padding: 12px 14px;">
                    <small style="margin-bottom: 6px; display: block; font-weight: 700;">Recent Recorded Purchases</small>
                    <div style="display: flex; flex-direction: column; gap: 8px;">
                        <div style="display: flex; align-items: center; justify-content: space-between; font-size: 12px;">
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <span class="category-pill" style="--cat-color: #0284c7; --cat-bg: #e0f2fe; padding: 2px 7px; font-size: 10px;">
                                    <?= svg_icon('cpu', '', 11) ?> Electronics
                                </span>
                                <strong>Croma Megastore</strong>
                            </div>
                            <span class="amount-text" style="font-size: 12.5px;">₹ 48,990.00</span>
                        </div>
                        <div style="display: flex; align-items: center; justify-content: space-between; font-size: 12px;">
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <span class="category-pill" style="--cat-color: #6366f1; --cat-bg: #eef2ff; padding: 2px 7px; font-size: 10px;">
                                    <?= svg_icon('shopping-bag', '', 11) ?> Shopping
                                </span>
                                <strong>Decathlon Sports</strong>
                            </div>
                            <span class="amount-text" style="font-size: 12.5px;">₹ 3,450.00</span>
                        </div>
                        <div style="display: flex; align-items: center; justify-content: space-between; font-size: 12px;">
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <span class="category-pill" style="--cat-color: #059669; --cat-bg: #ecfdf5; padding: 2px 7px; font-size: 10px;">
                                    <?= svg_icon('home', '', 11) ?> Home
                                </span>
                                <strong>IKEA Furniture</strong>
                            </div>
                            <span class="amount-text" style="font-size: 12.5px;">₹ 7,820.00</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Feature Grid Section -->
<section class="feature-section">
    <div class="section-intro">
        <span class="eyebrow">BUILT FOR PEACE OF MIND</span>
        <h2>Everything you need to master your bills</h2>
        <p>A streamlined, modern suite designed to keep your physical receipts safe and your warranty claims valid.</p>
    </div>

    <div class="feature-cards-grid">
        <article class="feature-box">
            <div class="feature-icon-wrap">
                <?= svg_icon('receipt', '', 22) ?>
            </div>
            <h3>Digital Records</h3>
            <p>Snap photo or upload digital invoices. Store vendor, date, amount, and notes in one searchable vault.</p>
        </article>

        <article class="feature-box">
            <div class="feature-icon-wrap" style="background: #e0f2fe; color: #0284c7;">
                <?= svg_icon('shield-check', '', 22) ?>
            </div>
            <h3>Warranty Sentinel</h3>
            <p>Keep track of appliance and gadget guarantees. Visual countdown badges warn you before coverage lapses.</p>
        </article>

        <article class="feature-box">
            <div class="feature-icon-wrap" style="background: #fef3c7; color: #d97706;">
                <?= svg_icon('trending-up', '', 22) ?>
            </div>
            <h3>Category Breakdown</h3>
            <p>Understand where your money goes every month with automatic category grouping and percentage bars.</p>
        </article>

        <article class="feature-box">
            <div class="feature-icon-wrap" style="background: #ecfdf5; color: #059669;">
                <?= svg_icon('search', '', 22) ?>
            </div>
            <h3>Instant Retrieval</h3>
            <p>Filter by store name, month, or category in seconds. No more digging through shoe boxes of thermal receipts.</p>
        </article>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>