<?php
// manage_applications.php
session_start();
require_once '../config/database.php';
require_once 'includes/auth_check.php';

$success = '';
$error   = '';

// ── Update application status ──────────────────────────────────
if (isset($_GET['action'], $_GET['id']) && is_numeric($_GET['id'])) {
    $id     = (int)$_GET['id'];
    $action = $_GET['action'];
    if (in_array($action, ['approved', 'rejected', 'pending'])) {
        $stmt = mysqli_prepare($conn, "UPDATE adoption_applications SET status=? WHERE application_id=?");
        mysqli_stmt_bind_param($stmt, 'si', $action, $id);
        if (mysqli_stmt_execute($stmt)) {
            // If approved, mark pet as adopted
            if ($action === 'approved') {
                $get = mysqli_prepare($conn, "SELECT pet_id FROM adoption_applications WHERE application_id=?");
                mysqli_stmt_bind_param($get, 'i', $id);
                mysqli_stmt_execute($get);
                $row = mysqli_fetch_assoc(mysqli_stmt_get_result($get));
                if ($row) mysqli_query($conn, "UPDATE pets SET status='adopted' WHERE pet_id={$row['pet_id']}");
            }
            log_activity($conn, $_SESSION['user_id'], $_SESSION['full_name'], 'superadmin', 'update_application', "Set application #$id to $action");
            $success = "Application #$id marked as $action.";
        }
    }
}

// ── Fetch applications ─────────────────────────────────────────
$filter = $_GET['status'] ?? '';
$sql = "SELECT aa.*, u.full_name AS adopter_name, u.email AS adopter_email,
               p.name AS pet_name, p.species, p.image_path
        FROM adoption_applications aa
        JOIN users u ON aa.user_id = u.user_id
        JOIN pets  p ON aa.pet_id  = p.pet_id";
if ($filter) $sql .= " WHERE aa.status = '" . mysqli_real_escape_string($conn, $filter) . "'";
$sql .= " ORDER BY aa.applied_at DESC";
$apps = mysqli_query($conn, $sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Applications — PawPal Superadmin</title>
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

        .filter-tabs { display:flex; gap:0.5rem; margin-bottom:1.3rem; flex-wrap:wrap; }
        .tab {
            padding:0.45rem 1.1rem; border-radius:50px; font-size:0.82rem; font-weight:700;
            text-decoration:none; border:1.5px solid #F0E6DE; background:#fff; color:var(--brown);
            transition:all 0.18s;
        }
        .tab:hover { border-color:var(--orange); color:var(--orange); }
        .tab.active { background:var(--orange); color:#fff; border-color:var(--orange); }

        .card-box { background:#fff; border:1.5px solid #F0E6DE; border-radius:16px; padding:1.3rem 1.5rem; }
        .alert-success { background:#E8F5E9; border:1.5px solid #A5D6A7; border-radius:10px; color:#2E7D32; padding:0.7rem 1rem; font-size:0.85rem; font-weight:600; margin-bottom:1.2rem; display:flex; gap:0.5rem; align-items:center; }

        table { width:100%; border-collapse:collapse; }
        thead th { font-size:0.72rem; font-weight:800; text-transform:uppercase; letter-spacing:0.8px; color:var(--brown-light); padding:0.6rem 1rem; background:#FFF8F4; border-bottom:1.5px solid #F0E6DE; text-align:left; white-space:nowrap; }
        tbody td { padding:0.85rem 1rem; font-size:0.86rem; border-bottom:1px solid #F5EDE7; vertical-align:middle; }
        tbody tr:last-child td { border-bottom:none; }
        tbody tr:hover { background:#FFFAF7; }

        .pet-thumb { width:38px; height:38px; border-radius:8px; object-fit:cover; background:#F5EDE7; display:inline-flex; align-items:center; justify-content:center; margin-right:0.5rem; vertical-align:middle; font-size:1.2rem; }
        .pet-thumb img { width:38px; height:38px; border-radius:8px; object-fit:cover; }

        .badge-pending  { background:#FFF8E1; color:#F57F17; font-size:0.72rem; font-weight:800; padding:0.25rem 0.7rem; border-radius:50px; }
        .badge-approved { background:#E8F5E9; color:#2E7D32; font-size:0.72rem; font-weight:800; padding:0.25rem 0.7rem; border-radius:50px; }
        .badge-rejected { background:#FFEBEE; color:#C62828; font-size:0.72rem; font-weight:800; padding:0.25rem 0.7rem; border-radius:50px; }

        .action-btns { display:flex; gap:0.4rem; }
        .btn-approve { background:#E8F5E9; color:#2E7D32; border:none; border-radius:7px; padding:0.3rem 0.7rem; font-size:0.78rem; font-weight:700; cursor:pointer; text-decoration:none; transition:all 0.18s; }
        .btn-approve:hover { background:#2E7D32; color:#fff; }
        .btn-reject  { background:#FFEBEE; color:#C62828; border:none; border-radius:7px; padding:0.3rem 0.7rem; font-size:0.78rem; font-weight:700; cursor:pointer; text-decoration:none; transition:all 0.18s; }
        .btn-reject:hover  { background:#C62828; color:#fff; }

        .empty-state { text-align:center; padding:3rem; color:var(--text-muted); }
        .empty-state i { font-size:2.5rem; display:block; margin-bottom:0.5rem; color:#E8D8CC; }
    </style>
</head>
<body>
<?php require_once 'includes/sidebar.php'; ?>
<div class="main-wrap">
    <div class="page-title">Adoption Applications</div>
    <div class="page-sub">Review and manage all adoption requests from adopters.</div>

    <?php if ($success): ?>
    <div class="alert-success"><i class="bi bi-check-circle-fill"></i><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <div class="filter-tabs">
        <a href="manage_applications.php" class="tab <?= !$filter ? 'active' : '' ?>">All</a>
        <a href="?status=pending"  class="tab <?= $filter === 'pending'  ? 'active' : '' ?>">⏳ Pending</a>
        <a href="?status=approved" class="tab <?= $filter === 'approved' ? 'active' : '' ?>">✅ Approved</a>
        <a href="?status=rejected" class="tab <?= $filter === 'rejected' ? 'active' : '' ?>">❌ Rejected</a>
    </div>

    <div class="card-box">
        <?php if (mysqli_num_rows($apps) === 0): ?>
        <div class="empty-state"><i class="bi bi-file-earmark-x"></i>No applications found.</div>
        <?php else: ?>
        <div style="overflow-x:auto">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Pet</th>
                    <th>Adopter</th>
                    <th>Email</th>
                    <th>Status</th>
                    <th>Applied</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($app = mysqli_fetch_assoc($apps)): ?>
                <tr>
                    <td style="color:var(--brown-light);font-size:0.78rem"><?= $app['application_id'] ?></td>
                    <td>
                        <?php if (!empty($app['image_path'])): ?>
                            <img src="../<?= htmlspecialchars($app['image_path']) ?>" class="pet-thumb" style="display:inline-block">
                        <?php else: ?>
                            <span class="pet-thumb"><?= $app['species'] === 'Cat' ? '🐱' : '🐶' ?></span>
                        <?php endif; ?>
                        <strong><?= htmlspecialchars($app['pet_name']) ?></strong>
                    </td>
                    <td style="font-weight:700"><?= htmlspecialchars($app['adopter_name']) ?></td>
                    <td style="color:var(--text-muted)"><?= htmlspecialchars($app['adopter_email']) ?></td>
                    <td><span class="badge-<?= $app['status'] ?>"><?= ucfirst($app['status']) ?></span></td>
                    <td style="color:var(--text-muted);white-space:nowrap"><?= date('M j, Y', strtotime($app['applied_at'])) ?></td>
                    <td>
                        <div class="action-btns">
                            <?php if ($app['status'] !== 'approved'): ?>
                            <a href="?action=approved&id=<?= $app['application_id'] ?>&status=<?= $filter ?>" class="btn-approve" onclick="return confirm('Approve this application?')"><i class="bi bi-check-lg"></i> Approve</a>
                            <?php endif; ?>
                            <?php if ($app['status'] !== 'rejected'): ?>
                            <a href="?action=rejected&id=<?= $app['application_id'] ?>&status=<?= $filter ?>" class="btn-reject" onclick="return confirm('Reject this application?')"><i class="bi bi-x-lg"></i> Reject</a>
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
</body>
</html>