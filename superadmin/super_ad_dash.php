<?php
session_start();
require_once '../config/database.php';
require_once 'includes/auth_check.php';

// ── Stats ──────────────────────────────────────────────────────
$stats = [];

$r = mysqli_query($conn, "SELECT COUNT(*) FROM users WHERE role = 'admin'");
$stats['admins'] = mysqli_fetch_row($r)[0];

$r = mysqli_query($conn, "SELECT COUNT(*) FROM users WHERE role = 'adopter'");
$stats['adopters'] = mysqli_fetch_row($r)[0];

$r = mysqli_query($conn, "SELECT COUNT(*) FROM pets WHERE status = 'available'");
$stats['available'] = mysqli_fetch_row($r)[0];

$r = mysqli_query($conn, "SELECT COUNT(*) FROM pets WHERE status = 'adopted'");
$stats['adopted'] = mysqli_fetch_row($r)[0];

$r = mysqli_query($conn, "SELECT COUNT(*) FROM adoption_applications WHERE status = 'pending'");
$stats['pending_apps'] = mysqli_fetch_row($r)[0];

$r = mysqli_query($conn, "SELECT COUNT(*) FROM care_records");
$stats['care_records'] = mysqli_fetch_row($r)[0];

// ── Recent logs ────────────────────────────────────────────────
$logs = mysqli_query($conn,
    "SELECT * FROM activity_logs ORDER BY created_at DESC LIMIT 8"
);

// ── Recent applications ────────────────────────────────────────
$apps = mysqli_query($conn,
    "SELECT aa.*, u.full_name AS adopter_name, p.name AS pet_name
     FROM adoption_applications aa
     JOIN users u ON aa.user_id = u.user_id
     JOIN pets p ON aa.pet_id = p.pet_id
     ORDER BY aa.applied_at DESC LIMIT 6"
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Overview — PawPal Superadmin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Baloo+2:wght@400;600;700;800&family=Nunito:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --cream:       #FFF8F0;
            --orange:      #FF7043;
            --orange-dark: #E64A19;
            --soft-orange: #FFB347;
            --brown:       #6D4C41;
            --brown-light: #A1887F;
            --text-dark:   #3E2723;
            --text-muted:  #795548;
            --sidebar-w:   240px;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Nunito', sans-serif;
            background: var(--cream);
            color: var(--text-dark);
            min-height: 100vh;
        }
        h1,h2,h3,h4,h5 { font-family: 'Baloo 2', cursive; }

        .main-wrap {
            margin-left: var(--sidebar-w);
            min-height: 100vh;
            padding: 2rem 2.2rem;
        }

        /* ── Top bar ── */
        .topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 2rem;
        }
        .page-title { font-size: 1.7rem; font-weight: 800; color: var(--text-dark); }
        .page-sub   { font-size: 0.85rem; color: var(--text-muted); font-weight: 600; }
        .topbar-right { display: flex; align-items: center; gap: 0.75rem; }
        .date-badge {
            background: #fff;
            border: 1.5px solid #F0E6DE;
            border-radius: 10px;
            padding: 0.45rem 1rem;
            font-size: 0.82rem;
            font-weight: 700;
            color: var(--brown);
        }

        /* ── Stat cards ── */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1.1rem;
            margin-bottom: 1.8rem;
        }
        .stat-card {
            background: #fff;
            border: 1.5px solid #F0E6DE;
            border-radius: 16px;
            padding: 1.3rem 1.5rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            transition: box-shadow 0.2s, transform 0.2s;
        }
        .stat-card:hover {
            box-shadow: 0 6px 20px rgba(255,112,67,0.10);
            transform: translateY(-2px);
        }
        .stat-icon {
            width: 52px; height: 52px;
            border-radius: 14px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.4rem;
            flex-shrink: 0;
        }
        .stat-icon.orange { background: #FFF0E8; }
        .stat-icon.green  { background: #E8F5E9; }
        .stat-icon.blue   { background: #E3F2FD; }
        .stat-icon.purple { background: #F3E5F5; }
        .stat-icon.red    { background: #FFEBEE; }
        .stat-icon.teal   { background: #E0F2F1; }
        .stat-num  { font-family: 'Baloo 2', cursive; font-size: 1.9rem; font-weight: 800; color: var(--text-dark); line-height: 1; }
        .stat-lbl  { font-size: 0.8rem; font-weight: 700; color: var(--text-muted); margin-top: 2px; }

        /* ── Section heading ── */
        .section-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1rem;
        }
        .section-title {
            font-size: 1.1rem;
            font-weight: 800;
            color: var(--text-dark);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .view-all {
            font-size: 0.8rem;
            font-weight: 700;
            color: var(--orange);
            text-decoration: none;
        }
        .view-all:hover { text-decoration: underline; }

        /* ── Two column layout ── */
        .two-col {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.2rem;
        }

        /* ── Card ── */
        .card-box {
            background: #fff;
            border: 1.5px solid #F0E6DE;
            border-radius: 16px;
            padding: 1.3rem 1.5rem;
        }

        /* ── Log list ── */
        .log-item {
            display: flex;
            align-items: flex-start;
            gap: 0.75rem;
            padding: 0.7rem 0;
            border-bottom: 1px solid #F5EDE7;
        }
        .log-item:last-child { border-bottom: none; }
        .log-dot {
            width: 34px; height: 34px;
            border-radius: 50%;
            background: #FFF0E8;
            display: flex; align-items: center; justify-content: center;
            font-size: 0.85rem;
            flex-shrink: 0;
            color: var(--orange);
        }
        .log-action { font-size: 0.85rem; font-weight: 700; color: var(--text-dark); }
        .log-meta   { font-size: 0.75rem; color: var(--text-muted); font-weight: 600; }

        /* ── App list ── */
        .app-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0.7rem 0;
            border-bottom: 1px solid #F5EDE7;
        }
        .app-item:last-child { border-bottom: none; }
        .app-info { font-size: 0.85rem; font-weight: 700; color: var(--text-dark); }
        .app-sub  { font-size: 0.75rem; color: var(--text-muted); font-weight: 600; }
        .badge-status {
            font-size: 0.72rem;
            font-weight: 800;
            padding: 0.25rem 0.7rem;
            border-radius: 50px;
            text-transform: uppercase;
            letter-spacing: 0.4px;
        }
        .badge-pending  { background: #FFF8E1; color: #F57F17; }
        .badge-approved { background: #E8F5E9; color: #2E7D32; }
        .badge-rejected { background: #FFEBEE; color: #C62828; }

        /* ── Quick actions ── */
        .quick-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.75rem;
            margin-bottom: 1.8rem;
        }
        .quick-btn {
            display: flex;
            align-items: center;
            gap: 0.65rem;
            background: #fff;
            border: 1.5px solid #F0E6DE;
            border-radius: 12px;
            padding: 0.9rem 1.1rem;
            color: var(--text-dark);
            font-size: 0.88rem;
            font-weight: 700;
            text-decoration: none;
            transition: all 0.18s;
            font-family: 'Nunito', sans-serif;
            cursor: pointer;
        }
        .quick-btn:hover {
            border-color: var(--orange);
            color: var(--orange);
            background: #FFF8F5;
            transform: translateY(-1px);
        }
        .quick-btn i { font-size: 1.1rem; color: var(--orange); }

        @media (max-width: 1100px) {
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .two-col    { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<?php require_once 'includes/sidebar.php'; ?>

<div class="main-wrap">

    <!-- Top bar -->
    <div class="topbar">
        <div>
            <div class="page-title">Good <?= (date('H') < 12) ? 'morning' : ((date('H') < 18) ? 'afternoon' : 'evening') ?>, <?= htmlspecialchars(explode(' ', $_SESSION['full_name'])[0]) ?> 👋</div>
            <div class="page-sub">Here's what's happening at PawPal today.</div>
        </div>
        <div class="topbar-right">
            <div class="date-badge"><i class="bi bi-calendar3 me-1"></i><?= date('F j, Y') ?></div>
        </div>
    </div>

    <!-- Stat cards -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon orange"><i class="bi bi-shield-lock-fill" style="color:#FF7043"></i></div>
            <div>
                <div class="stat-num"><?= $stats['admins'] ?></div>
                <div class="stat-lbl">Total Admins</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon blue"><i class="bi bi-people-fill" style="color:#1565C0"></i></div>
            <div>
                <div class="stat-num"><?= $stats['adopters'] ?></div>
                <div class="stat-lbl">Registered Adopters</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon green"><i class="bi bi-heart-fill" style="color:#2E7D32"></i></div>
            <div>
                <div class="stat-num"><?= $stats['available'] ?></div>
                <div class="stat-lbl">Pets Available</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon purple"><i class="bi bi-house-heart-fill" style="color:#6A1B9A"></i></div>
            <div>
                <div class="stat-num"><?= $stats['adopted'] ?></div>
                <div class="stat-lbl">Pets Adopted</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon red"><i class="bi bi-file-earmark-check-fill" style="color:#C62828"></i></div>
            <div>
                <div class="stat-num"><?= $stats['pending_apps'] ?></div>
                <div class="stat-lbl">Pending Applications</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon teal"><i class="bi bi-clipboard2-pulse-fill" style="color:#00695C"></i></div>
            <div>
                <div class="stat-num"><?= $stats['care_records'] ?></div>
                <div class="stat-lbl">Care Records</div>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="section-head">
        <div class="section-title"><i class="bi bi-lightning-charge-fill" style="color:var(--orange)"></i> Quick Actions</div>
    </div>
    <div class="quick-grid">
        <a href="manage_admins.php?action=add" class="quick-btn"><i class="bi bi-person-plus-fill"></i> Add New Admin</a>
        <a href="manage_pets.php?action=add" class="quick-btn"><i class="bi bi-plus-circle-fill"></i> Add New Pet</a>
        <a href="manage_applications.php" class="quick-btn"><i class="bi bi-file-earmark-check-fill"></i> Review Applications</a>
        <a href="activity_logs.php" class="quick-btn"><i class="bi bi-journal-text"></i> View Activity Logs</a>
    </div>

    <!-- Recent logs + Recent Applications -->
    <div class="two-col">

        <!-- Recent Logs -->
        <div class="card-box">
            <div class="section-head">
                <div class="section-title"><i class="bi bi-journal-text" style="color:var(--orange)"></i> Recent Activity</div>
                <a href="activity_logs.php" class="view-all">View all →</a>
            </div>
            <?php if (mysqli_num_rows($logs) === 0): ?>
                <p style="color:var(--text-muted);font-size:0.85rem;text-align:center;padding:1rem 0">No activity yet.</p>
            <?php else: ?>
                <?php while ($log = mysqli_fetch_assoc($logs)): ?>
                <div class="log-item">
                    <div class="log-dot">
                        <?php
                        $icons = [
                            'login'        => 'bi-box-arrow-in-right',
                            'logout'       => 'bi-box-arrow-right',
                            'create_admin' => 'bi-person-plus',
                            'delete_admin' => 'bi-person-dash',
                            'add_pet'      => 'bi-plus-circle',
                            'delete_pet'   => 'bi-trash',
                        ];
                        $icon = $icons[$log['action']] ?? 'bi-activity';
                        ?>
                        <i class="bi <?= $icon ?>"></i>
                    </div>
                    <div>
                        <div class="log-action"><?= htmlspecialchars($log['full_name']) ?> — <?= htmlspecialchars($log['action']) ?></div>
                        <div class="log-meta">
                            <span class="badge-status badge-<?= $log['role'] === 'superadmin' ? 'approved' : 'pending' ?>"><?= $log['role'] ?></span>
                            &nbsp;<?= date('M j, g:i A', strtotime($log['created_at'])) ?>
                            &nbsp;· <?= htmlspecialchars($log['ip_address']) ?>
                        </div>
                        <?php if ($log['description']): ?>
                        <div class="log-meta"><?= htmlspecialchars($log['description']) ?></div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endwhile; ?>
            <?php endif; ?>
        </div>

        <!-- Recent Applications -->
        <div class="card-box">
            <div class="section-head">
                <div class="section-title"><i class="bi bi-file-earmark-check-fill" style="color:var(--orange)"></i> Recent Applications</div>
                <a href="manage_applications.php" class="view-all">View all →</a>
            </div>
            <?php if (mysqli_num_rows($apps) === 0): ?>
                <p style="color:var(--text-muted);font-size:0.85rem;text-align:center;padding:1rem 0">No applications yet.</p>
            <?php else: ?>
                <?php while ($app = mysqli_fetch_assoc($apps)): ?>
                <div class="app-item">
                    <div>
                        <div class="app-info"><?= htmlspecialchars($app['adopter_name']) ?></div>
                        <div class="app-sub">wants to adopt <strong><?= htmlspecialchars($app['pet_name']) ?></strong> · <?= date('M j', strtotime($app['applied_at'])) ?></div>
                    </div>
                    <span class="badge-status badge-<?= $app['status'] ?>"><?= ucfirst($app['status']) ?></span>
                </div>
                <?php endwhile; ?>
            <?php endif; ?>
        </div>

    </div>

</div>
</body>
</html>