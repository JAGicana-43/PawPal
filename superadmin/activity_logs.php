<?php
session_start();
require_once '../config/database.php';
require_once 'includes/auth_check.php';

// ── Filters ────────────────────────────────────────────────────
$filter_role   = $_GET['role']   ?? '';
$filter_action = $_GET['action'] ?? '';
$search        = trim($_GET['search'] ?? '');

$where = [];
$params = [];
$types  = '';

if ($filter_role)   { $where[] = "role = ?";   $params[] = $filter_role;   $types .= 's'; }
if ($filter_action) { $where[] = "action = ?"; $params[] = $filter_action; $types .= 's'; }
if ($search)        { $where[] = "(full_name LIKE ? OR ip_address LIKE ? OR description LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%"; $types .= 'sss'; }

$sql = "SELECT * FROM activity_logs";
if ($where) $sql .= " WHERE " . implode(' AND ', $where);
$sql .= " ORDER BY created_at DESC LIMIT 200";

$stmt = mysqli_prepare($conn, $sql);
if ($params) mysqli_stmt_bind_param($stmt, $types, ...$params);
mysqli_stmt_execute($stmt);
$logs = mysqli_stmt_get_result($stmt);

// ── Distinct actions for filter dropdown ───────────────────────
$actions_res = mysqli_query($conn, "SELECT DISTINCT action FROM activity_logs ORDER BY action");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activity Logs — PawPal Superadmin</title>
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

        /* Filter bar */
        .filter-bar {
            background:#fff; border:1.5px solid #F0E6DE; border-radius:14px;
            padding:1rem 1.3rem; display:flex; gap:0.75rem; flex-wrap:wrap;
            align-items:center; margin-bottom:1.5rem;
        }
        .ipt-sm {
            border:2px solid #E8D8CC; border-radius:8px; padding:0.5rem 0.8rem;
            font-family:'Nunito',sans-serif; font-size:0.85rem; color:var(--text-dark);
            background:#fff; outline:none; transition:border-color 0.2s;
        }
        .ipt-sm:focus { border-color:var(--orange); }
        .btn-filter {
            background:var(--orange); color:#fff; border:none; border-radius:8px;
            padding:0.5rem 1.1rem; font-weight:700; font-size:0.85rem;
            font-family:'Nunito',sans-serif; cursor:pointer; transition:all 0.18s;
        }
        .btn-filter:hover { background:var(--orange-dark); }
        .btn-reset {
            background:#F5EDE7; color:var(--brown); border:none; border-radius:8px;
            padding:0.5rem 1rem; font-weight:700; font-size:0.85rem;
            font-family:'Nunito',sans-serif; cursor:pointer; text-decoration:none;
            display:inline-flex; align-items:center;
        }
        .btn-reset:hover { background:#E8D8CC; }

        /* Card */
        .card-box { background:#fff; border:1.5px solid #F0E6DE; border-radius:16px; padding:1.3rem 1.5rem; }
        .card-title { font-size:1.1rem; font-weight:800; color:var(--text-dark); margin-bottom:1.1rem; display:flex; align-items:center; gap:0.5rem; }

        /* Table */
        .table-wrap { overflow-x:auto; }
        table { width:100%; border-collapse:collapse; }
        thead th { font-size:0.72rem; font-weight:800; text-transform:uppercase; letter-spacing:0.8px; color:var(--brown-light); padding:0.6rem 1rem; background:#FFF8F4; border-bottom:1.5px solid #F0E6DE; text-align:left; white-space:nowrap; }
        tbody td { padding:0.8rem 1rem; font-size:0.86rem; border-bottom:1px solid #F5EDE7; vertical-align:middle; }
        tbody tr:last-child td { border-bottom:none; }
        tbody tr:hover { background:#FFFAF7; }

        /* Badges */
        .badge-role {
            font-size:0.7rem; font-weight:800; padding:0.2rem 0.6rem;
            border-radius:50px; text-transform:uppercase; letter-spacing:0.4px;
        }
        .badge-superadmin { background:#EDE7F6; color:#4527A0; }
        .badge-admin      { background:#E3F2FD; color:#1565C0; }
        .badge-adopter    { background:#E8F5E9; color:#2E7D32; }

        .action-tag {
            font-size:0.75rem; font-weight:700; color:var(--orange);
            background:#FFF0E8; padding:0.2rem 0.6rem; border-radius:6px;
        }

        .empty-state { text-align:center; padding:3rem 1rem; color:var(--text-muted); }
        .empty-state i { font-size:2.5rem; display:block; margin-bottom:0.5rem; color:#E8D8CC; }

        .count-badge {
            background:#FFF0E8; color:var(--orange); font-size:0.78rem;
            font-weight:800; padding:0.2rem 0.7rem; border-radius:50px;
            margin-left:0.5rem;
        }
    </style>
</head>
<body>
<?php require_once 'includes/sidebar.php'; ?>
<div class="main-wrap">
    <div class="page-title">Activity Logs</div>
    <div class="page-sub">Full history of who logged in, what actions were taken, and when.</div>

    <!-- Filters -->
    <form method="GET" class="filter-bar">
        <input type="text" class="ipt-sm" name="search" placeholder="🔍 Search name, IP..." value="<?= htmlspecialchars($search) ?>" style="min-width:200px">

        <select class="ipt-sm" name="role">
            <option value="">All Roles</option>
            <option value="superadmin" <?= $filter_role === 'superadmin' ? 'selected' : '' ?>>Superadmin</option>
            <option value="admin"      <?= $filter_role === 'admin'      ? 'selected' : '' ?>>Admin</option>
            <option value="adopter"    <?= $filter_role === 'adopter'    ? 'selected' : '' ?>>Adopter</option>
        </select>

        <select class="ipt-sm" name="action">
            <option value="">All Actions</option>
            <?php while ($a = mysqli_fetch_row($actions_res)): ?>
            <option value="<?= htmlspecialchars($a[0]) ?>" <?= $filter_action === $a[0] ? 'selected' : '' ?>>
                <?= htmlspecialchars($a[0]) ?>
            </option>
            <?php endwhile; ?>
        </select>

        <button type="submit" class="btn-filter"><i class="bi bi-funnel-fill me-1"></i>Filter</button>
        <a href="activity_logs.php" class="btn-reset"><i class="bi bi-x-circle me-1"></i>Reset</a>
    </form>

    <!-- Table -->
    <div class="card-box">
        <div class="card-title">
            <i class="bi bi-journal-text" style="color:var(--orange)"></i>
            Log Entries
            <span class="count-badge"><?= mysqli_num_rows($logs) ?></span>
        </div>

        <?php if (mysqli_num_rows($logs) === 0): ?>
        <div class="empty-state">
            <i class="bi bi-journal-x"></i>
            No log entries found.
        </div>
        <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>User</th>
                        <th>Role</th>
                        <th>Action</th>
                        <th>Description</th>
                        <th>IP Address</th>
                        <th>Date & Time</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $i = 1; while ($log = mysqli_fetch_assoc($logs)): ?>
                    <tr>
                        <td style="color:var(--brown-light);font-size:0.78rem"><?= $i++ ?></td>
                        <td style="font-weight:700"><?= htmlspecialchars($log['full_name']) ?></td>
                        <td><span class="badge-role badge-<?= $log['role'] ?>"><?= $log['role'] ?></span></td>
                        <td><span class="action-tag"><?= htmlspecialchars($log['action']) ?></span></td>
                        <td style="color:var(--text-muted)"><?= htmlspecialchars($log['description'] ?: '—') ?></td>
                        <td style="font-family:monospace;font-size:0.82rem"><?= htmlspecialchars($log['ip_address']) ?></td>
                        <td style="white-space:nowrap;color:var(--text-muted)"><?= date('M j, Y g:i A', strtotime($log['created_at'])) ?></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
