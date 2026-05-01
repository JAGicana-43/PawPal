<?php
$current = basename($_SERVER['PHP_SELF']);
?>
<aside class="sidebar">
    <div class="sidebar-brand">
        <span class="brand-icon">🐾</span>
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
    </nav>

    <div class="sidebar-footer">
        <div class="admin-info">
            <div class="admin-avatar"><?= strtoupper(substr($_SESSION['full_name'], 0, 1)) ?></div>
            <div>
                <div class="admin-name"><?= htmlspecialchars($_SESSION['full_name']) ?></div>
                <div class="admin-tag">Superadmin</div>
            </div>
        </div>
        <a href="../auth/logout.php" class="logout-btn" title="Logout">
            <i class="bi bi-box-arrow-right"></i>
        </a>
    </div>
</aside>

<style>
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
    padding: 1.5rem 1.4rem 1.2rem;
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
.nav-item:hover {
    background: #FFF0E8;
    color: #FF7043;
}
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
.admin-info { display: flex; align-items: center; gap: 0.6rem; min-width: 0; }
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
    font-size: 0.82rem;
    font-weight: 700;
    color: #3E2723;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 120px;
}
.admin-tag {
    font-size: 0.68rem;
    font-weight: 700;
    color: #FF7043;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.logout-btn {
    color: #A1887F;
    font-size: 1.1rem;
    text-decoration: none;
    padding: 0.35rem;
    border-radius: 8px;
    transition: all 0.18s;
    display: flex; align-items: center;
    flex-shrink: 0;
}
.logout-btn:hover { color: #e53935; background: #FFEBEE; }
</style>
