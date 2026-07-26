<?php
/**
 * topbar.php
 * Reusable top header bar with live clock, profile, notifications, search.
 */
?>
<header class="topbar">
    <div class="topbar-left">
        <button class="sidebar-toggle-btn" id="sidebar-toggle-btn" onclick="toggleSidebar()">
            <i class="fa-solid fa-bars"></i>
        </button>
        <div class="header-breadcrumb">
            <span class="breadcrumb-item">SmartAgri</span>
            <i class="fa-solid fa-chevron-right breadcrumb-separator"></i>
            <span class="breadcrumb-current" id="header-page-title"><?= htmlspecialchars($pageTitle ?? 'Dashboard') ?></span>
        </div>
    </div>

    <div class="topbar-right">
        <div class="topbar-clock" id="live-clock">
            <i class="fa-regular fa-clock"></i>
            <span id="clock-display">Loading...</span>
        </div>

        <div class="topbar-action-icon" title="Notifications">
            <i class="fa-regular fa-bell"></i>
            <span class="notification-dot"></span>
        </div>

        <div class="topbar-divider"></div>

        <div class="topbar-profile">
            <div class="profile-avatar">A</div>
            <div class="profile-details">
                <span class="profile-name">Admin</span>
                <span class="profile-status">GPR Farm</span>
            </div>
        </div>
    </div>
</header>
<script>
function updateLiveClock() {
    const now = new Date();
    const options = { 
        timeZone: 'Asia/Kolkata', 
        day: '2-digit', 
        month: 'short', 
        year: 'numeric',
        hour: '2-digit', 
        minute: '2-digit', 
        second: '2-digit',
        hour12: true 
    };
    const str = now.toLocaleString('en-IN', options);
    const clockEl = document.getElementById('clock-display');
    if (clockEl) clockEl.textContent = str;
}
setInterval(updateLiveClock, 1000);
updateLiveClock();

function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebar-overlay');
    if (sidebar) sidebar.classList.toggle('open');
    if (overlay) overlay.classList.toggle('show');
}
</script>
<div class="sidebar-overlay" id="sidebar-overlay" onclick="toggleSidebar()"></div>
