<?php
session_start();
require_once '../config/database.php';
require_once 'includes/auth_check.php';

$success = '';
$error   = '';

// ── Suspend adopter ────────────────────────────────────────────
if (isset($_GET['suspend']) && is_numeric($_GET['suspend'])) {
    $id   = (int)$_GET['suspend'];
    $stmt = mysqli_prepare($conn, "SELECT full_name, role FROM users WHERE user_id=? AND role='adopter'");
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $u = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    if ($u) {
        mysqli_prepare($conn, "UPDATE users SET status='suspended' WHERE user_id=?");
        $upd = mysqli_prepare($conn, "UPDATE users SET status='suspended' WHERE user_id=?");
        mysqli_stmt_bind_param($upd, 'i', $id);
        mysqli_stmt_execute($upd);
        log_activity($conn, $_SESSION['user_id'], $_SESSION['full_name'], 'superadmin', 'suspend_adopter', "Suspended adopter: {$u['full_name']}");
        $success = "Adopter '{$u['full_name']}' has been suspended.";
    } else {
        $error = 'Invalid action.';
    }
}

// ── Archive adopter ────────────────────────────────────────────
if (isset($_GET['archive']) && is_numeric($_GET['archive'])) {
    $id   = (int)$_GET['archive'];
    $stmt = mysqli_prepare($conn, "SELECT full_name, role FROM users WHERE user_id=? AND role='adopter'");
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $u = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    if ($u) {
        $upd = mysqli_prepare($conn, "UPDATE users SET status='archived' WHERE user_id=?");
        mysqli_stmt_bind_param($upd, 'i', $id);
        mysqli_stmt_execute($upd);
        log_activity($conn, $_SESSION['user_id'], $_SESSION['full_name'], 'superadmin', 'archive_adopter', "Archived adopter: {$u['full_name']}");
        $success = "Adopter '{$u['full_name']}' has been archived.";
    } else {
        $error = 'Invalid action.';
    }
}

// ── Restore adopter ────────────────────────────────────────────
if (isset($_GET['restore']) && is_numeric($_GET['restore'])) {
    $id   = (int)$_GET['restore'];
    $stmt = mysqli_prepare($conn, "SELECT full_name FROM users WHERE user_id=? AND role='adopter'");
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $u = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    if ($u) {
        $upd = mysqli_prepare($conn, "UPDATE users SET status='active' WHERE user_id=?");
        mysqli_stmt_bind_param($upd, 'i', $id);
        mysqli_stmt_execute($upd);
        log_activity($conn, $_SESSION['user_id'], $_SESSION['full_name'], 'superadmin', 'restore_adopter', "Restored adopter: {$u['full_name']}");
        $success = "Adopter '{$u['full_name']}' has been restored to active.";
    } else {
        $error = 'Invalid action.';
    }
}

// ── Fetch adopters ─────────────────────────────────────────────
$search     = trim($_GET['search'] ?? '');
$tab        = $_GET['tab'] ?? 'active'; // active | suspended | archived

$sql = "SELECT u.user_id, u.full_name, u.email, u.contact_number, u.created_at, u.status,
               COUNT(aa.application_id) AS app_count
        FROM users u
        LEFT JOIN adoption_applications aa ON u.user_id = aa.user_id
        WHERE u.role = 'adopter'";

// Tab filter
if ($tab === 'suspended') {
    $sql .= " AND u.status = 'suspended'";
} elseif ($tab === 'archived') {
    $sql .= " AND u.status = 'archived'";
} else {
    $sql .= " AND u.status = 'active'";
}

if ($search) {
    $s    = mysqli_real_escape_string($conn, $search);
    $sql .= " AND (u.full_name LIKE '%$s%' OR u.email LIKE '%$s%')";
}
$sql .= " GROUP BY u.user_id ORDER BY u.created_at DESC";
$adopters = mysqli_query($conn, $sql);

// Tab counts
$cnt = [];
foreach (['active','suspended','archived'] as $t) {
    $r = mysqli_query($conn, "SELECT COUNT(*) FROM users WHERE role='adopter' AND status='$t'");
    $cnt[$t] = mysqli_fetch_row($r)[0];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Adopters — PawPal Superadmin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Baloo+2:wght@400;600;700;800&family=Nunito:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root { --cream:#FFF8F0; --orange:#FF7043; --orange-dark:#E64A19; --brown:#6D4C41; --brown-light:#A1887F; --text-dark:#3E2723; --text-muted:#795548; --sidebar-w:240px; }
        * { box-sizing:border-box; margin:0; padding:0; }
        body { font-family:'Nunito',sans-serif; background:var(--cream); color:var(--text-dark); min-height:100vh; }
        h1,h2,h3 { font-family:'Baloo 2',cursive; }
        .main-wrap { margin-left:var(--sidebar-w); padding:2rem 2.2rem; min-height:100vh; }
        .page-title { font-size:1.7rem; font-weight:800; margin-bottom:0.2rem; }
        .page-sub   { font-size:0.85rem; color:var(--text-muted); font-weight:600; margin-bottom:1.5rem; }

        /* Tabs */
        .tabs { display:flex; gap:0.5rem; margin-bottom:1.2rem; flex-wrap:wrap; }
        .tab-btn {
            display:inline-flex; align-items:center; gap:0.4rem;
            padding:0.5rem 1.1rem; border-radius:50px;
            font-size:0.82rem; font-weight:700;
            text-decoration:none; transition:all 0.18s;
            border:1.5px solid #F0E6DE;
            background:#fff; color:var(--brown);
        }
        .tab-btn:hover { border-color:var(--orange); color:var(--orange); }
        .tab-btn.active-tab { background:var(--orange); color:#fff; border-color:var(--orange); box-shadow:0 4px 12px rgba(255,112,67,0.25); }
        .tab-count {
            background:rgba(255,255,255,0.25); border-radius:50px;
            padding:0.05rem 0.45rem; font-size:0.75rem; font-weight:800;
        }
        .tab-btn:not(.active-tab) .tab-count { background:#F0E6DE; color:var(--brown); }

        .filter-bar { background:#fff; border:1.5px solid #F0E6DE; border-radius:14px; padding:0.9rem 1.2rem; display:flex; gap:0.6rem; align-items:center; margin-bottom:1.3rem; flex-wrap:wrap; }
        .ipt-sm { border:2px solid #E8D8CC; border-radius:8px; padding:0.45rem 0.75rem; font-family:'Nunito',sans-serif; font-size:0.85rem; color:var(--text-dark); background:#fff; outline:none; }
        .ipt-sm:focus { border-color:var(--orange); }
        .btn-filter { background:var(--orange); color:#fff; border:none; border-radius:8px; padding:0.45rem 1rem; font-weight:700; font-size:0.85rem; font-family:'Nunito',sans-serif; cursor:pointer; }
        .btn-reset  { background:#F5EDE7; color:var(--brown); border:none; border-radius:8px; padding:0.45rem 0.9rem; font-weight:700; font-size:0.85rem; font-family:'Nunito',sans-serif; cursor:pointer; text-decoration:none; }

        .card-box { background:#fff; border:1.5px solid #F0E6DE; border-radius:16px; padding:1.3rem 1.5rem; }
        .card-title { font-size:1.1rem; font-weight:800; margin-bottom:1.1rem; display:flex; align-items:center; gap:0.5rem; }

        .alert-success { background:#E8F5E9; border:1.5px solid #A5D6A7; border-radius:10px; color:#2E7D32; padding:0.7rem 1rem; font-size:0.85rem; font-weight:600; margin-bottom:1.2rem; display:flex; gap:0.5rem; align-items:center; }
        .alert-error   { background:#FFF0ED; border:1.5px solid #FFCCBC; border-radius:10px; color:#BF360C; padding:0.7rem 1rem; font-size:0.85rem; font-weight:600; margin-bottom:1.2rem; display:flex; gap:0.5rem; align-items:center; }

        table { width:100%; border-collapse:collapse; }
        thead th { font-size:0.72rem; font-weight:800; text-transform:uppercase; letter-spacing:0.8px; color:var(--brown-light); padding:0.6rem 1rem; background:#FFF8F4; border-bottom:1.5px solid #F0E6DE; text-align:left; }
        tbody td { padding:0.85rem 1rem; font-size:0.86rem; border-bottom:1px solid #F5EDE7; vertical-align:middle; }
        tbody tr:last-child td { border-bottom:none; }
        tbody tr:hover { background:#FFFAF7; }

        .avatar-sm { width:34px; height:34px; border-radius:50%; background:var(--orange); color:#fff; font-weight:800; font-size:0.85rem; display:inline-flex; align-items:center; justify-content:center; margin-right:0.5rem; flex-shrink:0; }
        .avatar-sm.suspended { background:#FF8F00; }
        .avatar-sm.archived  { background:#9E9E9E; }

        .app-count { background:#FFF0E8; color:var(--orange); font-size:0.75rem; font-weight:800; padding:0.2rem 0.6rem; border-radius:50px; }

        /* Status badges */
        .badge-active    { background:#E8F5E9; color:#2E7D32; font-size:0.7rem; font-weight:800; padding:0.2rem 0.6rem; border-radius:50px; text-transform:uppercase; }
        .badge-suspended { background:#FFF8E1; color:#F57F17; font-size:0.7rem; font-weight:800; padding:0.2rem 0.6rem; border-radius:50px; text-transform:uppercase; }
        .badge-archived  { background:#F5F5F5; color:#757575; font-size:0.7rem; font-weight:800; padding:0.2rem 0.6rem; border-radius:50px; text-transform:uppercase; }

        /* Action buttons */
        .action-group { display:flex; gap:0.4rem; flex-wrap:wrap; }
        .btn-action { border:none; border-radius:8px; padding:0.3rem 0.65rem; font-size:0.76rem; font-weight:700; cursor:pointer; text-decoration:none; display:inline-flex; align-items:center; gap:0.3rem; transition:all 0.18s; font-family:'Nunito',sans-serif; }
        .btn-suspend { background:#FFF8E1; color:#F57F17; }
        .btn-suspend:hover { background:#F57F17; color:#fff; }
        .btn-archive { background:#FFEBEE; color:#C62828; }
        .btn-archive:hover { background:#C62828; color:#fff; }
        .btn-restore { background:#E8F5E9; color:#2E7D32; }
        .btn-restore:hover { background:#2E7D32; color:#fff; }

        .empty-state { text-align:center; padding:3rem; color:var(--text-muted); }
        .empty-state i { font-size:2.5rem; display:block; margin-bottom:0.5rem; color:#E8D8CC; }
        .count-badge { background:#FFF0E8; color:var(--orange); font-size:0.78rem; font-weight:800; padding:0.2rem 0.7rem; border-radius:50px; margin-left:0.5rem; }
    </style>
</head>
<body>
<?php require_once 'includes/sidebar.php'; ?>
<div class="main-wrap">
    <div class="page-title">Manage Adopters</div>
    <div class="page-sub">View, suspend, archive, or restore adopter accounts.</div>

    <?php if ($success): ?><div class="alert-success"><i class="bi bi-check-circle-fill"></i><?= htmlspecialchars($success) ?></div><?php endif; ?>
    <?php if ($error):   ?><div class="alert-error"><i class="bi bi-exclamation-circle-fill"></i><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <!-- Tabs -->
    <div class="tabs">
        <a href="?tab=active<?= $search ? '&search='.urlencode($search) : '' ?>"
           class="tab-btn <?= $tab === 'active' ? 'active-tab' : '' ?>">
            <i class="bi bi-person-check-fill"></i> Active
            <span class="tab-count"><?= $cnt['active'] ?></span>
        </a>
        <a href="?tab=suspended<?= $search ? '&search='.urlencode($search) : '' ?>"
           class="tab-btn <?= $tab === 'suspended' ? 'active-tab' : '' ?>">
            <i class="bi bi-pause-circle-fill"></i> Suspended
            <span class="tab-count"><?= $cnt['suspended'] ?></span>
        </a>
        <a href="?tab=archived<?= $search ? '&search='.urlencode($search) : '' ?>"
           class="tab-btn <?= $tab === 'archived' ? 'active-tab' : '' ?>">
            <i class="bi bi-archive-fill"></i> Archived
            <span class="tab-count"><?= $cnt['archived'] ?></span>
        </a>
    </div>

    <!-- Search -->
    <form method="GET" class="filter-bar">
        <input type="hidden" name="tab" value="<?= htmlspecialchars($tab) ?>">
        <input type="text" class="ipt-sm" name="search" placeholder="🔍 Search name or email..."
               value="<?= htmlspecialchars($search) ?>" style="min-width:240px">
        <button type="submit" class="btn-filter"><i class="bi bi-funnel-fill me-1"></i>Search</button>
        <a href="?tab=<?= $tab ?>" class="btn-reset">Reset</a>
    </form>

    <div class="card-box">
        <div class="card-title">
            <i class="bi bi-people-fill" style="color:var(--orange)"></i>
            <?= ucfirst($tab) ?> Adopters
            <span class="count-badge"><?= mysqli_num_rows($adopters) ?></span>
        </div>

        <?php if (mysqli_num_rows($adopters) === 0): ?>
        <div class="empty-state">
            <i class="bi bi-people"></i>
            No <?= $tab ?> adopters found.
        </div>
        <?php else: ?>
        <div style="overflow-x:auto">
        <table>
            <thead>
                <tr>
                    <th>Adopter</th>
                    <th>Email</th>
                    <th>Contact</th>
                    <th>Applications</th>
                    <th>Status</th>
                    <th>Joined</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($a = mysqli_fetch_assoc($adopters)): ?>
                <tr>
                    <td>
                        <div style="display:flex;align-items:center">
                            <span class="avatar-sm <?= $a['status'] ?>"><?= strtoupper(substr($a['full_name'], 0, 1)) ?></span>
                            <span style="font-weight:700"><?= htmlspecialchars($a['full_name']) ?></span>
                        </div>
                    </td>
                    <td><?= htmlspecialchars($a['email']) ?></td>
                    <td><?= htmlspecialchars($a['contact_number'] ?: '—') ?></td>
                    <td><span class="app-count"><?= $a['app_count'] ?> apps</span></td>
                    <td><span class="badge-<?= $a['status'] ?>"><?= ucfirst($a['status']) ?></span></td>
                    <td style="color:var(--text-muted);white-space:nowrap"><?= date('M j, Y', strtotime($a['created_at'])) ?></td>
                    <td>
                        <div class="action-group">
                            <?php if ($a['status'] === 'active'): ?>
                                <a href="?suspend=<?= $a['user_id'] ?>&tab=active"
                                   class="btn-action btn-suspend"
                                   onclick="return confirm('Suspend <?= htmlspecialchars($a['full_name']) ?>?\nThey will not be able to log in.')">
                                    <i class="bi bi-pause-circle"></i> Suspend
                                </a>
                                <a href="?archive=<?= $a['user_id'] ?>&tab=active"
                                   class="btn-action btn-archive"
                                   onclick="return confirm('Archive <?= htmlspecialchars($a['full_name']) ?>?\nTheir data will be preserved.')">
                                    <i class="bi bi-archive"></i> Archive
                                </a>
                            <?php elseif ($a['status'] === 'suspended'): ?>
                                <a href="?restore=<?= $a['user_id'] ?>&tab=suspended"
                                   class="btn-action btn-restore"
                                   onclick="return confirm('Restore <?= htmlspecialchars($a['full_name']) ?> to active?')">
                                    <i class="bi bi-arrow-counterclockwise"></i> Restore
                                </a>
                                <a href="?archive=<?= $a['user_id'] ?>&tab=suspended"
                                   class="btn-action btn-archive"
                                   onclick="return confirm('Archive <?= htmlspecialchars($a['full_name']) ?>?')">
                                    <i class="bi bi-archive"></i> Archive
                                </a>
                            <?php elseif ($a['status'] === 'archived'): ?>
                                <a href="?restore=<?= $a['user_id'] ?>&tab=archived"
                                   class="btn-action btn-restore"
                                   onclick="return confirm('Restore <?= htmlspecialchars($a['full_name']) ?> to active?')">
                                    <i class="bi bi-arrow-counterclockwise"></i> Restore
                                </a>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- ══ Confirmation Modal (replaces browser confirm) ══ -->
<div id="confirmOverlay" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(30,10,5,0.55);backdrop-filter:blur(4px);align-items:center;justify-content:center;padding:1rem;">
    <div style="background:#fff;border-radius:20px;max-width:380px;width:100%;box-shadow:0 20px 60px rgba(0,0,0,0.2);overflow:hidden;animation:popIn 0.25s cubic-bezier(.22,.68,0,1.2) both;">
        <div style="padding:1.6rem 1.6rem 0;text-align:center;">
            <div id="confirmIcon" style="width:60px;height:60px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:1.7rem;margin:0 auto 1rem;"></div>
            <h3 id="confirmTitle" style="font-family:'Baloo 2',cursive;color:#3E2723;font-size:1.2rem;margin-bottom:0.4rem;"></h3>
            <p id="confirmMsg" style="font-size:0.86rem;color:#795548;font-weight:600;line-height:1.5;margin-bottom:1.4rem;"></p>
        </div>
        <div style="padding:0 1.6rem 1.6rem;display:flex;gap:0.75rem;">
            <button onclick="closeConfirm()" style="flex:1;background:#F5EDE7;color:#6D4C41;border:none;border-radius:12px;padding:0.75rem;font-weight:800;font-size:0.95rem;font-family:'Baloo 2',cursive;cursor:pointer;">Cancel</button>
            <a id="confirmBtn" href="#" style="flex:1;color:#fff;border:none;border-radius:12px;padding:0.75rem;font-weight:800;font-size:0.95rem;font-family:'Baloo 2',cursive;cursor:pointer;text-decoration:none;display:flex;align-items:center;justify-content:center;gap:0.4rem;"></a>
        </div>
    </div>
</div>
<style>
@keyframes popIn { from{opacity:0;transform:scale(0.92) translateY(10px)} to{opacity:1;transform:scale(1) translateY(0)} }
</style>
</body>
</html>