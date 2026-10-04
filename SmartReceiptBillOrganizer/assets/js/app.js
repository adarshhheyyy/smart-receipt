/**
 * SmartReceipt - Client-Side Interactivity & State Management
 * Dark Mode Engine, Notification Center, Floating Toasts, Lightbox & Presets
 */

'use strict';

/**
 * ============================================================================
 * Theme Switcher (Dark / Light Mode)
 * ============================================================================
 */
function getActiveTheme() {
    return document.documentElement.getAttribute('data-theme') || localStorage.getItem('smartreceipt_theme') || 'light';
}

function applyTheme(theme, notifyUser = false) {
    document.documentElement.setAttribute('data-theme', theme);
    localStorage.setItem('smartreceipt_theme', theme);

    // Sync all theme toggle buttons state/title if present
    document.querySelectorAll('.theme-toggle-btn').forEach(btn => {
        btn.setAttribute('title', theme === 'dark' ? 'Switch to Light Mode' : 'Switch to Dark Mode');
        btn.setAttribute('aria-label', theme === 'dark' ? 'Switch to Light Mode' : 'Switch to Dark Mode');
    });

    if (notifyUser && typeof showToast === 'function') {
        showToast({
            title: theme === 'dark' ? 'Dark Mode Activated' : 'Classy Light Mode Activated',
            message: theme === 'dark' ? 'Obsidian slate fintech theme enabled.' : 'Warm alabaster executive theme enabled.',
            type: 'info',
            duration: 2500
        });
    }
}

function toggleTheme() {
    const currentTheme = getActiveTheme();
    const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
    applyTheme(newTheme, true);
}

/**
 * ============================================================================
 * Notification Center & Alerts Manager
 * ============================================================================
 */
const NOTIF_STORAGE_KEY = 'smartreceipt_read_notifications_v1';
let currentNotifFilter = 'all';

function getReadNotificationIds() {
    try {
        const stored = localStorage.getItem(NOTIF_STORAGE_KEY);
        return stored ? JSON.parse(stored) : [];
    } catch (e) {
        return [];
    }
}

function setReadNotificationIds(ids) {
    try {
        localStorage.setItem(NOTIF_STORAGE_KEY, JSON.stringify(ids));
    } catch (e) {
        console.error('Failed to save read notifications', e);
    }
}

function getNotificationsData() {
    return Array.isArray(window.__INITIAL_NOTIFICATIONS__) ? window.__INITIAL_NOTIFICATIONS__ : [];
}

/**
 * Toggle the Notification Dropdown menu
 */
function toggleNotifications(event) {
    if (event) event.stopPropagation();
    const dropdown = document.getElementById('notifDropdown');
    const btn = document.getElementById('notifBtn');
    if (!dropdown || !btn) return;

    const isOpen = dropdown.classList.contains('active');
    if (isOpen) {
        closeNotifications();
    } else {
        dropdown.classList.add('active');
        btn.setAttribute('aria-expanded', 'true');
        renderNotifications(currentNotifFilter);
    }
}

/**
 * Close the Notification Dropdown menu
 */
function closeNotifications() {
    const dropdown = document.getElementById('notifDropdown');
    const btn = document.getElementById('notifBtn');
    if (dropdown) dropdown.classList.remove('active');
    if (btn) btn.setAttribute('aria-expanded', 'false');
}

/**
 * Switch Filter Tabs (All / Warranties / Activity)
 */
function switchNotifTab(tab, tabBtn) {
    currentNotifFilter = tab;
    document.querySelectorAll('.notif-tab').forEach(b => b.classList.remove('active'));
    if (tabBtn) tabBtn.classList.add('active');
    renderNotifications(tab);
}

/**
 * Mark a single notification item as read
 */
function markNotificationAsRead(id, event) {
    if (event) {
        event.stopPropagation();
        event.preventDefault();
    }
    const readIds = getReadNotificationIds();
    if (!readIds.includes(id)) {
        readIds.push(id);
        setReadNotificationIds(readIds);
    }
    renderNotifications(currentNotifFilter);
}

/**
 * Mark all current notifications as read
 */
function markAllNotificationsAsRead() {
    const allNotifs = getNotificationsData();
    const allIds = allNotifs.map(n => n.id);
    setReadNotificationIds(allIds);
    renderNotifications(currentNotifFilter);

    if (typeof showToast === 'function') {
        showToast({
            title: 'Notifications Cleared',
            message: 'All alerts have been marked as read.',
            type: 'success',
            duration: 3000
        });
    }
}

/**
 * Render Notification List and update Unread Counters
 */
function renderNotifications(filter = 'all') {
    const listEl = document.getElementById('notifList');
    const badgeEl = document.getElementById('notifBadge');
    const pingEl = document.getElementById('notifPing');
    const counterPillEl = document.getElementById('notifCounterPill');

    const allNotifs = getNotificationsData();
    const readIds = getReadNotificationIds();

    // Calculate unread count across ALL notifications
    const totalUnreadCount = allNotifs.filter(n => !readIds.includes(n.id)).length;

    // Update Topbar Bell Badge
    if (badgeEl) {
        if (totalUnreadCount > 0) {
            badgeEl.textContent = totalUnreadCount > 9 ? '9+' : totalUnreadCount;
            badgeEl.style.display = 'grid';
        } else {
            badgeEl.style.display = 'none';
        }
    }
    if (pingEl) {
        pingEl.style.display = totalUnreadCount > 0 ? 'block' : 'none';
    }
    if (counterPillEl) {
        counterPillEl.textContent = `${totalUnreadCount} new`;
    }

    if (!listEl) return;

    // Filter items based on active tab
    const filtered = allNotifs.filter(item => {
        if (filter === 'warranty') return item.category === 'warranty';
        if (filter === 'activity') return item.category === 'activity';
        return true;
    });

    if (filtered.length === 0) {
        listEl.innerHTML = `
            <div class="notif-empty">
                <div class="notif-empty-icon">
                    <svg class="svg-icon" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>
                    </svg>
                </div>
                <h5>All caught up!</h5>
                <p>No new alerts found in this category.</p>
            </div>
        `;
        return;
    }

    listEl.innerHTML = filtered.map(item => {
        const isRead = readIds.includes(item.id);
        const iconMarkup = getNotificationSvg(item.type || 'info');
        const badgeClass = `notif-type-${item.type || 'info'}`;

        return `
            <div class="notif-item ${isRead ? 'read' : 'unread'}" onclick="handleNotifClick('${escapeHtml(item.id)}', '${escapeHtml(item.link)}')">
                <div class="notif-item-icon ${badgeClass}">
                    ${iconMarkup}
                </div>
                <div class="notif-item-body">
                    <div class="notif-item-header">
                        <strong class="notif-item-title">${escapeHtml(item.title)}</strong>
                        <span class="notif-time-badge">${escapeHtml(item.time_label || '')}</span>
                    </div>
                    <p class="notif-item-message">${escapeHtml(item.message)}</p>
                    <div class="notif-item-actions">
                        <span class="notif-action-link">${escapeHtml(item.action_text || 'View')} &rarr;</span>
                        ${!isRead ? `
                            <button type="button" class="notif-mark-single-read" onclick="markNotificationAsRead('${escapeHtml(item.id)}', event)" title="Mark as read">
                                <svg class="svg-icon" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <polyline points="20 6 9 17 4 12"/>
                                </svg>
                                <span>Mark read</span>
                            </button>
                        ` : ''}
                    </div>
                </div>
                ${!isRead ? `<span class="notif-unread-dot" title="Unread notification"></span>` : ''}
            </div>
        `;
    }).join('');
}

function handleNotifClick(id, link) {
    const readIds = getReadNotificationIds();
    if (!readIds.includes(id)) {
        readIds.push(id);
        setReadNotificationIds(readIds);
    }
    closeNotifications();
    if (link && link !== '#') {
        window.location.href = link;
    }
}

function getNotificationSvg(type) {
    switch (type) {
        case 'urgent':
            return '<svg class="svg-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>';
        case 'warning':
            return '<svg class="svg-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>';
        case 'expired':
            return '<svg class="svg-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>';
        case 'tip':
            return '<svg class="svg-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m12 3-1.9 5.8a2 2 0 0 1-1.3 1.3L3 12l5.8 1.9a2 2 0 0 1 1.3 1.3L12 21l1.9-5.8a2 2 0 0 1 1.3-1.3L21 12l-5.8-1.9a2 2 0 0 1-1.3-1.3Z"/></svg>';
        case 'info':
        default:
            return '<svg class="svg-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 2v20l2-1 2 1 2-1 2 1 2-1 2 1 2-1 2 1V2l-2 1-2-1-2 1-2-1-2 1-2-1-2 1Z"/><path d="M16 8h-8"/><path d="M16 12h-8"/><path d="M12 16h-4"/></svg>';
    }
}

function escapeHtml(str) {
    if (!str) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

/**
 * ============================================================================
 * High-End Floating Toast Notification Engine
 * ============================================================================
 */
window.showToast = function ({
    title = 'Notice',
    message = '',
    type = 'info',
    duration = 4500,
    actionUrl = null,
    actionText = null
}) {
    const container = document.getElementById('toastContainer');
    if (!container) return;

    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.setAttribute('role', 'alert');

    let iconSvg = '';
    if (type === 'success') {
        iconSvg = '<svg class="svg-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>';
    } else if (type === 'warning') {
        iconSvg = '<svg class="svg-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>';
    } else if (type === 'error') {
        iconSvg = '<svg class="svg-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>';
    } else {
        iconSvg = '<svg class="svg-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>';
    }

    toast.innerHTML = `
        <div class="toast-icon-wrap">
            ${iconSvg}
        </div>
        <div class="toast-body">
            <strong class="toast-title">${escapeHtml(title)}</strong>
            <p class="toast-message">${escapeHtml(message)}</p>
            ${actionUrl && actionText ? `
                <a href="${escapeHtml(actionUrl)}" class="toast-action-btn">${escapeHtml(actionText)} &rarr;</a>
            ` : ''}
        </div>
        <button type="button" class="toast-close" aria-label="Close notification">&times;</button>
        <div class="toast-progress">
            <div class="toast-progress-bar" style="animation-duration: ${duration}ms;"></div>
        </div>
    `;

    const closeBtn = toast.querySelector('.toast-close');
    let timer = null;

    const dismissToast = () => {
        if (timer) clearTimeout(timer);
        toast.classList.add('toast-hiding');
        setTimeout(() => toast.remove(), 260);
    };

    closeBtn.addEventListener('click', dismissToast);
    timer = setTimeout(dismissToast, duration);

    // Pause on hover
    toast.addEventListener('mouseenter', () => {
        const bar = toast.querySelector('.toast-progress-bar');
        if (bar) bar.style.animationPlayState = 'paused';
        if (timer) clearTimeout(timer);
    });

    toast.addEventListener('mouseleave', () => {
        const bar = toast.querySelector('.toast-progress-bar');
        if (bar) bar.style.animationPlayState = 'running';
        timer = setTimeout(dismissToast, 1800);
    });

    container.appendChild(toast);
};

/**
 * ============================================================================
 * Mobile Sidebar Drawer Navigation
 * ============================================================================
 */
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    if (sidebar) sidebar.classList.toggle('open');
    if (overlay) overlay.classList.toggle('active');
}

function closeSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    if (sidebar) sidebar.classList.remove('open');
    if (overlay) overlay.classList.remove('active');
}

/**
 * ============================================================================
 * Live Receipt Image Preview Handler
 * ============================================================================
 */
function previewReceipt(input) {
    const previewContainer = document.getElementById('previewContainer');
    const previewImg = document.getElementById('receiptPreview');
    const uploadPrompt = document.getElementById('uploadPrompt');

    if (!input || !input.files || !input.files[0]) {
        return;
    }

    const file = input.files[0];
    if (!file.type.startsWith('image/')) {
        showToast({ title: 'Invalid File', message: 'Please select an image file (JPG, PNG, or WEBP).', type: 'error' });
        input.value = '';
        return;
    }

    if (file.size > 3 * 1024 * 1024) {
        showToast({ title: 'File Too Large', message: 'Receipt image size must not exceed 3 MB.', type: 'error' });
        input.value = '';
        return;
    }

    const objectUrl = URL.createObjectURL(file);
    if (previewImg) previewImg.src = objectUrl;
    if (previewContainer) previewContainer.style.display = 'block';
    if (uploadPrompt) uploadPrompt.style.display = 'none';
}

function clearReceiptImage(event) {
    if (event) event.stopPropagation();
    const fileInput = document.getElementById('receiptImageInput');
    const previewContainer = document.getElementById('previewContainer');
    const previewImg = document.getElementById('receiptPreview');
    const uploadPrompt = document.getElementById('uploadPrompt');

    if (fileInput) fileInput.value = '';
    if (previewImg) previewImg.src = '';
    if (previewContainer) previewContainer.style.display = 'none';
    if (uploadPrompt) uploadPrompt.style.display = 'flex';
}

/**
 * ============================================================================
 * Receipt Lightbox Modal
 * ============================================================================
 */
function openReceiptModal(imageSrc, shopTitle, notes) {
    const modal = document.getElementById('receiptModal');
    const modalImg = document.getElementById('modalReceiptImg');
    const modalTitle = document.getElementById('modalReceiptTitle');
    const modalNotes = document.getElementById('modalReceiptNotes');

    if (!modal || !modalImg) return;

    modalImg.src = imageSrc;
    if (modalTitle) modalTitle.textContent = shopTitle || 'Receipt View';
    if (modalNotes) modalNotes.textContent = notes || '';

    modal.classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeReceiptModal() {
    const modal = document.getElementById('receiptModal');
    if (!modal) return;
    modal.classList.remove('active');
    document.body.style.overflow = '';
}

/**
 * ============================================================================
 * Warranty Duration Preset Calculator
 * ============================================================================
 */
function setWarrantyPreset(months) {
    const startInput = document.getElementById('warrantyStart');
    const expiryInput = document.getElementById('warrantyExpiry');
    const purchaseDateInput = document.getElementById('purchaseDate');

    if (!expiryInput) return;

    let startDateVal = startInput ? startInput.value : '';
    if (!startDateVal && purchaseDateInput) {
        startDateVal = purchaseDateInput.value;
        if (startInput) startInput.value = startDateVal;
    }

    const baseDate = startDateVal ? new Date(startDateVal) : new Date();
    if (isNaN(baseDate.getTime())) return;

    baseDate.setMonth(baseDate.getMonth() + parseInt(months, 10));
    const year = baseDate.getFullYear();
    const month = String(baseDate.getMonth() + 1).padStart(2, '0');
    const day = String(baseDate.getDate()).padStart(2, '0');

    expiryInput.value = `${year}-${month}-${day}`;
}

/**
 * ============================================================================
 * Flash Alert Box Dismissal
 * ============================================================================
 */
function dismissAlert(btn) {
    const alertBox = btn.closest('.alert');
    if (alertBox) {
        alertBox.style.opacity = '0';
        alertBox.style.transform = 'translateY(-10px)';
        setTimeout(() => alertBox.remove(), 250);
    }
}

/**
 * Delete Confirmation
 */
function confirmDelete(event, itemName) {
    const message = `Are you sure you want to permanently delete the receipt from "${itemName || 'this store'}"? This action cannot be undone.`;
    if (!confirm(message)) {
        event.preventDefault();
        return false;
    }
    return true;
}

/**
 * ============================================================================
 * DOMContentLoaded Initializer
 * ============================================================================
 */
document.addEventListener('DOMContentLoaded', function () {
    // 1. Sync theme on load
    const activeTheme = getActiveTheme();
    applyTheme(activeTheme, false);

    // 2. Initialize notifications
    renderNotifications('all');

    // 3. Close popovers when clicking outside
    document.addEventListener('click', function (e) {
        const notifWrapper = document.getElementById('notifWrapper');
        if (notifWrapper && !notifWrapper.contains(e.target)) {
            closeNotifications();
        }
    });

    // 4. Drag & Drop Upload Dropzone
    const dropzone = document.querySelector('.upload-dropzone');
    const fileInput = document.getElementById('receiptImageInput');

    if (dropzone && fileInput) {
        ['dragenter', 'dragover'].forEach(eventName => {
            dropzone.addEventListener(eventName, function (e) {
                e.preventDefault();
                e.stopPropagation();
                dropzone.classList.add('drag-over');
            }, false);
        });

        ['dragleave', 'drop'].forEach(eventName => {
            dropzone.addEventListener(eventName, function (e) {
                e.preventDefault();
                e.stopPropagation();
                dropzone.classList.remove('drag-over');
            }, false);
        });

        dropzone.addEventListener('drop', function (e) {
            const dt = e.dataTransfer;
            if (dt && dt.files && dt.files.length) {
                fileInput.files = dt.files;
                previewReceipt(fileInput);
            }
        });
    }

    // 5. Global Escape Key handler
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            closeReceiptModal();
            closeSidebar();
            closeNotifications();
        }
    });

    // 6. Auto-dismiss flash notifications after 6 seconds
    const flashAlert = document.querySelector('.alert:not(.permanent)');
    if (flashAlert) {
        setTimeout(function () {
            flashAlert.style.transition = 'opacity 0.4s ease, transform 0.4s ease';
            flashAlert.style.opacity = '0';
            flashAlert.style.transform = 'translateY(-10px)';
            setTimeout(() => flashAlert.remove(), 400);
        }, 6000);
    }

    // 7. Proactive Warranty Expiry Toast on Dashboard/Vault load
    try {
        const allNotifs = getNotificationsData();
        const readIds = getReadNotificationIds();
        const urgentNotif = allNotifs.find(n => (n.type === 'urgent' || n.type === 'warning') && !readIds.includes(n.id));

        if (urgentNotif && !sessionStorage.getItem('smartreceipt_warranty_toast_shown')) {
            sessionStorage.setItem('smartreceipt_warranty_toast_shown', 'true');
            setTimeout(() => {
                showToast({
                    title: urgentNotif.title,
                    message: urgentNotif.message,
                    type: urgentNotif.type === 'urgent' ? 'warning' : 'info',
                    duration: 7500,
                    actionUrl: urgentNotif.link,
                    actionText: 'Inspect'
                });
            }, 900);
        }
    } catch (e) {
        // Safe failover
    }
});

/**
 * ============================================================================
 * Password Strength & Visibility Utilities
 * ============================================================================
 */
function togglePasswordVisibility(inputId, btn) {
    const input = document.getElementById(inputId);
    if (!input) return;

    const isPassword = input.type === 'password';
    input.type = isPassword ? 'text' : 'password';

    if (btn) {
        const iconShow = btn.querySelector('.icon-show');
        const iconHide = btn.querySelector('.icon-hide');
        if (iconShow && iconHide) {
            iconShow.style.display = isPassword ? 'none' : 'inline-block';
            iconHide.style.display = isPassword ? 'inline-block' : 'none';
        }
    }
}

function checkPasswordStrength(password) {
    const meterBar = document.getElementById('pwdMeterBar');
    const meterLabel = document.getElementById('pwdMeterLabel');
    const reqLength = document.getElementById('reqLength');
    const reqUpper = document.getElementById('reqUpper');
    const reqNumber = document.getElementById('reqNumber');
    const reqSymbol = document.getElementById('reqSymbol');

    if (!meterBar || !meterLabel) return;

    const hasLength = password.length >= 8;
    const hasUpper = /[A-Z]/.test(password);
    const hasNumber = /[0-9]/.test(password);
    const hasSymbol = /[^A-Za-z0-9]/.test(password);

    const updateReq = (el, passed) => {
        if (!el) return;
        if (passed) {
            el.classList.add('passed');
        } else {
            el.classList.remove('passed');
        }
    };

    updateReq(reqLength, hasLength);
    updateReq(reqUpper, hasUpper);
    updateReq(reqNumber, hasNumber);
    updateReq(reqSymbol, hasSymbol);

    let score = 0;
    if (hasLength) score++;
    if (hasUpper) score++;
    if (hasNumber) score++;
    if (hasSymbol) score++;

    meterBar.className = 'pwd-meter-bar';
    if (!password) {
        meterLabel.textContent = 'Enter password';
        meterLabel.style.color = 'var(--text-muted)';
        meterBar.style.width = '0%';
    } else if (score <= 1) {
        meterLabel.textContent = 'Weak (add uppercase, number & symbol)';
        meterLabel.style.color = '#ef4444';
        meterBar.classList.add('weak');
        meterBar.style.width = '25%';
    } else if (score === 2) {
        meterLabel.textContent = 'Moderate';
        meterLabel.style.color = '#f59e0b';
        meterBar.classList.add('fair');
        meterBar.style.width = '50%';
    } else if (score === 3) {
        meterLabel.textContent = 'Good (missing 1 requirement)';
        meterLabel.style.color = '#0ea5e9';
        meterBar.classList.add('good');
        meterBar.style.width = '75%';
    } else if (score === 4) {
        meterLabel.textContent = 'Strong & Secure ✓';
        meterLabel.style.color = '#10b981';
        meterBar.classList.add('strong');
        meterBar.style.width = '100%';
    }
}
