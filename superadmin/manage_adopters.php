<?php
session_start();
require_once '../config/database.php';
require_once 'includes/auth_check.php';

$success = '';
$error   = '';

// ── Delete adopter ─────────────────────────────────────────────
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id   = (int)$_GET['delete'];
    $stmt = mysqli_prepare($conn, "SELECT full_name, role FROM users WHERE user_id=?");
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $u = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    if ($u && $u['role'] === 'adopter') {
        mysqli_query($conn, "DELETE FROM users WHERE user_id=$id AND role='adopter'");
        log_activity($conn, $_SESSION['user_id'], $_SESSION['full_name'], 'superadmin', 'delete_adopter', "Removed adopter: {$u['full_name']}");
        $success = "Adopter '{$u['full_name']}' has been removed.";
    } else {
        $error = 'Invalid action.';
    }
}

// ── Fetch adopters ─────────────────────────────────────────────
$search = trim($_GET['search'] ?? '');
$sql = "SELECT u.user_id, u.full_name, u.email, u.contact_number, u.created_at,
               COUNT(aa.application_id) AS app_count
        FROM users u
        LEFT JOIN adoption_applications aa ON u.user_id = aa.user_id
        WHERE u.role = 'adopter'";
if ($search) $sql .= " AND (u.full_name LIKE '%" . mysqli_real_escape_string($conn, $search) . "%' OR u.email LIKE '%" . mysqli_real_escape_string($conn, $search) . "%')";
$sql .= " GROUP BY u.user_id ORDER BY u.created_at DESC";
$adopters = mysqli_query($conn, $sql);
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
        .app-count { background:#FFF0E8; color:var(--orange); font-size:0.75rem; font-weight:800; padding:0.2rem 0.6rem; border-radius:50px; }
        .btn-del { background:#FFEBEE; color:#C62828; border:none; border-radius:8px; padding:0.3rem 0.7rem; font-size:0.78rem; font-weight:700; cursor:pointer; text-decoration:none; display:inline-flex; align-items:center; gap:0.3rem; transition:all 0.18s; }
        .btn-del:hover { background:#C62828; color:#fff; }
        .empty-state { text-align:center; padding:3rem; color:var(--text-muted); }
        .empty-state i { font-size:2.5rem; display:block; margin-bottom:0.5rem; color:#E8D8CC; }

        .count-badge { background:#FFF0E8; color:var(--orange); font-size:0.78rem; font-weight:800; padding:0.2rem 0.7rem; border-radius:50px; margin-left:0.5rem; }
    </style>
</head>
<body>
<?php require_once 'includes/sidebar.php'; ?>
<div class="main-wrap">
    <div class="page-title">Manage Adopters</div>
    <div class="page-sub">View and manage all registered adopter accounts.</div>

    <?php if ($success): ?><div class="alert-success"><i class="bi bi-check-circle-fill"></i><?= htmlspecialchars($success) ?></div><?php endif; ?>
    <?php if ($error):   ?><div class="alert-error"><i class="bi bi-exclamation-circle-fill"></i><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <form method="GET" class="filter-bar">
        <input type="text" class="ipt-sm" name="search" placeholder="🔍 Search name or email..." value="<?= htmlspecialchars($search) ?>" style="min-width:240px">
        <button type="submit" class="btn-filter"><i class="bi bi-funnel-fill me-1"></i>Search</button>
        <a href="manage_adopters.php" class="btn-reset">Reset</a>
    </form>

    <div class="card-box">
        <div class="card-title">
            <i class="bi bi-people-fill" style="color:var(--orange)"></i>
            Adopters
            <span class="count-badge"><?= mysqli_num_rows($adopters) ?></span>
        </div>

        <?php if (mysqli_num_rows($adopters) === 0): ?>
        <div class="empty-state"><i class="bi bi-people"></i>No adopters found.</div>
        <?php else: ?>
        <div style="overflow-x:auto">
        <table>
            <thead>
                <tr>
                    <th>Adopter</th>
                    <th>Email</th>
                    <th>Contact</th>
                    <th>Applications</th>
                    <th>Joined</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($a = mysqli_fetch_assoc($adopters)): ?>
                <tr>
                    <td style="display:flex;align-items:center">
                        <span class="avatar-sm"><?= strtoupper(substr($a['full_name'], 0, 1)) ?></span>
                        <span style="font-weight:700"><?= htmlspecialchars($a['full_name']) ?></span>
                    </td>
                    <td><?= htmlspecialchars($a['email']) ?></td>
                    <td><?= htmlspecialchars($a['contact_number'] ?: '—') ?></td>
                    <td><span class="app-count"><?= $a['app_count'] ?> apps</span></td>
                    <td style="color:var(--text-muted);white-space:nowrap"><?= date('M j, Y', strtotime($a['created_at'])) ?></td>
                    <td>
                        <a href="?delete=<?= $a['user_id'] ?>" class="btn-del" onclick="return confirm('Remove <?= htmlspecialchars($a['full_name']) ?>?')">
                            <i class="bi bi-trash3"></i> Remove
                        </a>
                    </td>
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
