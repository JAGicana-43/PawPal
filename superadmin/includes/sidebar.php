<?php
$current = basename($_SERVER['PHP_SELF']);
?>
<aside class="sidebar">
    <div class="sidebar-brand">
        <!-- Logo image — falls back to paw emoji if not found -->
        <?php if (file_exists('../assets/images/logo.png')): ?>
            <img src="../assets/images/logo.png" alt="PawPal" style="height:50px;width:auto;object-fit:contain;">
        <?php else: ?>
            <span class="brand-icon">🐾</span>
        <?php endif; ?>
        <div>
            <div class="brand-name">PawPal</div>
            <div class="brand-role">Superadmin Panel</div>
        </div>
    </div>

    <nav class="sidebar-nav">
        <div class="nav-group-label">Main</div>
        <a href="super_ad_dash.php" class="nav-item <?= $current === 'super_ad_dash.php' ? 'active' : '' ?>">
            <i class="bi bi-grid-1x2-fill"></i> Overview
        </a>

        <div class="nav-group-label">Management</div>
        <a href="manage_admins.php" class="nav-item <?= $current === 'manage_admins.php' ? 'active' : '' ?>">
            <i class="bi bi-shield-lock-fill"></i> Manage Admins
        </a>
        <a href="manage_adopters.php" class="nav-item <?= $current === 'manage_adopters.php' ? 'active' : '' ?>">
            <i class="bi bi-people-fill"></i> Manage Adopters
        </a>
        <a href="manage_pets.php" class="nav-item <?= $current === 'manage_pets.php' ? 'active' : '' ?>">
            <i class="bi bi-heart-fill"></i> Manage Pets
        </a>
        <a href="manage_applications.php" class="nav-item <?= $current === 'manage_applications.php' ? 'active' : '' ?>">
            <i class="bi bi-file-earmark-check-fill"></i> Applications
        </a>
        <a href="care_records.php" class="nav-item <?= $current === 'care_records.php' ? 'active' : '' ?>">
            <i class="bi bi-clipboard2-pulse-fill"></i> Care Records
        </a>

        <div class="nav-group-label">System</div>
        <a href="activity_logs.php" class="nav-item <?= $current === 'activity_logs.php' ? 'active' : '' ?>">
            <i class="bi bi-journal-text"></i> Activity Logs
        </a>
        <a href="settings.php" class="nav-item <?= $current === 'settings.php' ? 'active' : '' ?>">
            <i class="bi bi-gear-fill"></i> Settings
        </a>
    </nav>

    <div class="sidebar-footer">
        <a href="settings.php" class="admin-info" style="text-decoration:none;" title="Go to Settings">
            <div class="admin-avatar"><?= strtoupper(substr($_SESSION['full_name'], 0, 1)) ?></div>
            <div>
                <div class="admin-name"><?= htmlspecialchars($_SESSION['full_name']) ?></div>
                <div class="admin-tag">Superadmin</div>
            </div>
        </a>
        <!-- Logout button triggers confirmation overlay -->
        <button onclick="openLogoutModal()" class="logout-btn" title="Logout">
            <i class="bi bi-box-arrow-right"></i>
        </button>
    </div>
</aside>

<!-- ══ Logout Confirmation Overlay ══ -->
<div id="logoutOverlay" style="
    display:none; position:fixed; inset:0; z-index:9999;
    background:rgba(30,10,5,0.55); backdrop-filter:blur(4px);
    align-items:center; justify-content:center; padding:1rem;">
    <div style="
        background:#fff; border-radius:20px; max-width:400px; width:100%;
        box-shadow:0 20px 60px rgba(0,0,0,0.20); overflow:hidden;
        animation: popIn 0.25s cubic-bezier(.22,.68,0,1.2) both;">

        <!-- Header -->
        <div style="padding:1.6rem 1.6rem 0; text-align:center;">
            <div style="
                width:64px; height:64px; border-radius:50%;
                background:#FFEBEE; border:2px solid #FFCDD2;
                display:flex; align-items:center; justify-content:center;
                font-size:1.8rem; margin:0 auto 1rem;">
                🚪
            </div>
            <h3 style="font-family:'Baloo 2',cursive; color:#3E2723; font-size:1.3rem; margin-bottom:0.4rem;">
                Log out?
            </h3>
            <p style="font-size:0.88rem; color:#795548; font-weight:600; line-height:1.5; margin-bottom:1.4rem;">
                Are you sure you want to log out of the<br>PawPal Superadmin Panel?
            </p>
        </div>

        <!-- Buttons -->
        <div style="padding:0 1.6rem 1.6rem; display:flex; gap:0.75rem;">
            <button onclick="closeLogoutModal()" style="
                flex:1; background:#F5EDE7; color:#6D4C41;
                border:none; border-radius:12px; padding:0.75rem;
                font-weight:800; font-size:0.95rem;
                font-family:'Baloo 2',cursive; cursor:pointer;
                transition:background 0.2s;">
                Cancel
            </button>
            <a href="../auth/logout.php" style="
                flex:1; background:#e53935; color:#fff;
                border:none; border-radius:12px; padding:0.75rem;
                font-weight:800; font-size:0.95rem;
                font-family:'Baloo 2',cursive; cursor:pointer;
                transition:background 0.2s; text-decoration:none;
                display:flex; align-items:center; justify-content:center; gap:0.4rem;">
                <i class="bi bi-box-arrow-right"></i> Yes, Log Out
            </a>
        </div>
    </div>
</div>

<style>
@keyframes popIn {
    from { opacity:0; transform:scale(0.92) translateY(10px); }
    to   { opacity:1; transform:scale(1) translateY(0); }
}

.sidebar {
    width: 240px;
    min-height: 100vh;
    background: #fff;
    border-right: 1.5px solid #F0E6DE;
    display: flex;
    flex-direction: column;
    position: fixed;
    left: 0; top: 0; bottom: 0;
    z-index: 100;
    padding: 0;
}
.sidebar-brand {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 1.3rem 1.4rem 1.1rem;
    border-bottom: 1.5px solid #F0E6DE;
}
.brand-icon { font-size: 1.6rem; }
.brand-name {
    font-family: 'Baloo 2', cursive;
    font-weight: 800;
    font-size: 1.15rem;
    color: #3E2723;
    line-height: 1.1;
}
.brand-role {
    font-size: 0.7rem;
    font-weight: 700;
    color: #FF7043;
    text-transform: uppercase;
    letter-spacing: 0.8px;
}
.sidebar-nav {
    flex: 1;
    padding: 1rem 0.75rem;
    overflow-y: auto;
}
.nav-group-label {
    font-size: 0.65rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 1.2px;
    color: #A1887F;
    padding: 0.8rem 0.65rem 0.3rem;
}
.nav-item {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    padding: 0.6rem 0.9rem;
    border-radius: 10px;
    color: #6D4C41;
    font-size: 0.88rem;
    font-weight: 600;
    text-decoration: none;
    transition: all 0.18s;
    margin-bottom: 2px;
}
.nav-item:hover { background: #FFF0E8; color: #FF7043; }
.nav-item.active {
    background: #FF7043;
    color: #fff;
    box-shadow: 0 4px 12px rgba(255,112,67,0.28);
}
.nav-item i { font-size: 0.95rem; width: 18px; text-align: center; }

.sidebar-footer {
    padding: 1rem 1.2rem;
    border-top: 1.5px solid #F0E6DE;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.6rem;
}
.admin-info {
    display: flex; align-items: center; gap: 0.6rem; min-width: 0;
    border-radius: 10px; padding: 0.3rem 0.5rem;
    transition: background 0.18s;
}
.admin-info:hover { background: #FFF0E8; }
.admin-avatar {
    width: 34px; height: 34px;
    border-radius: 50%;
    background: #FF7043;
    color: #fff;
    font-weight: 800;
    font-size: 0.95rem;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
}
.admin-name {
    font-size: 0.82rem; font-weight: 700;
    color: #3E2723;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    max-width: 120px;
}
.admin-tag {
    font-size: 0.68rem; font-weight: 700;
    color: #FF7043;
    text-transform: uppercase; letter-spacing: 0.5px;
}
.logout-btn {
    background: none;
    border: none;
    color: #A1887F;
    font-size: 1.1rem;
    cursor: pointer;
    padding: 0.35rem;
    border-radius: 8px;
    transition: all 0.18s;
    display: flex; align-items: center;
    flex-shrink: 0;
}
.logout-btn:hover { color: #e53935; background: #FFEBEE; }
</style>

<script>
function openLogoutModal() {
    const overlay = document.getElementById('logoutOverlay');
    overlay.style.display = 'flex';
    document.body.style.overflow = 'hidden';
}
function closeLogoutModal() {
    document.getElementById('logoutOverlay').style.display = 'none';
    document.body.style.overflow = '';
}
// Close on backdrop click
document.getElementById('logoutOverlay').addEventListener('click', function(e) {
    if (e.target === this) closeLogoutModal();
});
// Close on Escape
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeLogoutModal();
});
</script>