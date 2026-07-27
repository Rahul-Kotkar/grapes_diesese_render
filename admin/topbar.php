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

        <div class="topbar-action-icon notification-wrapper" title="Notifications" onclick="toggleNotifications()">
            <i class="fa-regular fa-bell"></i>
            <?php
            // Fetch recent high risk alerts (last 24 hours) using a fresh connection 
            // since the main $conn might have been closed by the parent script.
            $topbarConn = getDBConnection();
            $notifSql = "SELECT created_at, temperature, humidity, dsi FROM sensor_data 
                         WHERE risk_level = 'High' AND created_at >= NOW() - INTERVAL 1 DAY 
                         ORDER BY created_at DESC LIMIT 5";
            $notifRes = $topbarConn->query($notifSql);
            $notifications = [];
            if ($notifRes) {
                while($n = $notifRes->fetch_assoc()){
                    $notifications[] = $n;
                }
            }
            $hasNotifs = count($notifications) > 0;
            ?>
            <?php if($hasNotifs): ?>
                <span class="notification-dot"></span>
            <?php endif; ?>
            
            <div class="notification-dropdown" id="notification-dropdown">
                <div class="notif-header">High Risk Alerts (24h)</div>
                <div class="notif-body">
                    <?php if(!$hasNotifs): ?>
                        <div class="notif-item" style="text-align:center; color:#999; padding: 15px;">No high risk alerts.</div>
                    <?php else: ?>
                        <?php foreach($notifications as $notif): ?>
                            <div class="notif-item">
                                <div class="notif-title"><i class="fa-solid fa-triangle-exclamation" style="color:var(--danger-color);"></i> High Disease Risk Detected</div>
                                <div class="notif-desc">DSI: <b><?= $notif['dsi'] ?></b> | Temp: <?= $notif['temperature'] ?>°C | Hum: <?= $notif['humidity'] ?>%</div>
                                <div class="notif-time"><?= date('H:i, d M', strtotime($notif['created_at'])) ?></div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
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

function toggleNotifications() {
    const dropdown = document.getElementById('notification-dropdown');
    if (dropdown) dropdown.classList.toggle('show');
}

// Close dropdown when clicking outside
document.addEventListener('click', function(event) {
    const wrapper = document.querySelector('.notification-wrapper');
    const dropdown = document.getElementById('notification-dropdown');
    if (wrapper && dropdown && !wrapper.contains(event.target)) {
        dropdown.classList.remove('show');
    }
});
</script>
<div class="sidebar-overlay" id="sidebar-overlay" onclick="toggleSidebar()"></div>
