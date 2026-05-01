<?php
session_start();
require_once '../config/database.php';
require_once 'includes/auth_check.php';

$success = '';
$error   = '';
$edit_record = null;

// ── Delete ─────────────────────────────────────────────────────
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    mysqli_query($conn, "DELETE FROM care_records WHERE record_id=$id");
    log_activity($conn, $_SESSION['user_id'], $_SESSION['full_name'], 'superadmin', 'delete_care_record', "Deleted care record #$id");
    $success = 'Care record deleted.';
}

// ── Load for edit ──────────────────────────────────────────────
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $id   = (int)$_GET['edit'];
    $stmt = mysqli_prepare($conn, "SELECT * FROM care_records WHERE record_id=?");
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $edit_record = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
}

// ── Add / Update ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pet_id         = (int)($_POST['pet_id'] ?? 0);
    $record_type    = trim($_POST['record_type'] ?? '');
    $title          = trim($_POST['title'] ?? '');
    $description    = trim($_POST['description'] ?? '');
    $date_of_record = $_POST['date_of_record'] ?? date('Y-m-d');
    $record_id      = (int)($_POST['record_id'] ?? 0);
    $created_by     = $_SESSION['user_id'];

    if (!$pet_id || empty($record_type) || empty($title)) {
        $error = 'Please select a pet, record type, and enter a title.';
    } else {
        if ($record_id > 0) {
            $stmt = mysqli_prepare($conn,
                "UPDATE care_records SET pet_id=?, record_type=?, title=?, description=?, date_of_record=? WHERE record_id=?"
            );
            mysqli_stmt_bind_param($stmt, 'issssi', $pet_id, $record_type, $title, $description, $date_of_record, $record_id);
        } else {
            $stmt = mysqli_prepare($conn,
                "INSERT INTO care_records (pet_id, record_type, title, description, date_of_record, created_by) VALUES (?,?,?,?,?,?)"
            );
            mysqli_stmt_bind_param($stmt, 'issssi', $pet_id, $record_type, $title, $description, $date_of_record, $created_by);
        }
        if (mysqli_stmt_execute($stmt)) {
            log_activity($conn, $_SESSION['user_id'], $_SESSION['full_name'], 'superadmin', $record_id ? 'edit_care_record' : 'add_care_record', "Pet ID: $pet_id, Type: $record_type");
            $success = $record_id ? 'Care record updated.' : 'Care record added.';
            $edit_record = null;
        } else { $error = 'Could not save record. ' . mysqli_error($conn); }
    }
}

// ── Fetch all pets for dropdown ────────────────────────────────
$pets_list = mysqli_query($conn, "SELECT pet_id, name, species FROM pets ORDER BY name");

// ── Fetch care records ─────────────────────────────────────────
$filter_pet = $_GET['pet'] ?? '';
$sql = "SELECT cr.*, p.name AS pet_name, p.species
        FROM care_records cr
        JOIN pets p ON cr.pet_id = p.pet_id";
if ($filter_pet && is_numeric($filter_pet)) $sql .= " WHERE cr.pet_id=$filter_pet";
$sql .= " ORDER BY cr.date_of_record DESC";
$records = mysqli_query($conn, $sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Care Records — PawPal Superadmin</title>
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

        .layout { display:grid; grid-template-columns:360px 1fr; gap:1.5rem; align-items:start; }
        .card-box { background:#fff; border:1.5px solid #F0E6DE; border-radius:16px; padding:1.4rem 1.5rem; }
        .card-title { font-size:1.1rem; font-weight:800; margin-bottom:1.1rem; display:flex; align-items:center; gap:0.5rem; }

        .lbl { display:block; font-size:0.73rem; font-weight:700; text-transform:uppercase; letter-spacing:0.5px; color:var(--brown); margin-bottom:0.3rem; }
        .ipt { width:100%; border:2px solid #E8D8CC; border-radius:10px; padding:0.62rem 0.9rem; font-family:'Nunito',sans-serif; font-size:0.88rem; color:var(--text-dark); background:#fff; outline:none; transition:border-color 0.2s; }
        .ipt:focus { border-color:var(--orange); box-shadow:0 0 0 3px rgba(255,112,67,0.12); }
        .mb { margin-bottom:0.9rem; }

        .btn-submit { width:100%; background:var(--orange); color:#fff; border:none; border-radius:10px; padding:0.72rem; font-weight:800; font-size:0.95rem; font-family:'Baloo 2',cursive; cursor:pointer; box-shadow:0 4px 14px rgba(255,112,67,0.28); transition:all 0.2s; }
        .btn-submit:hover { background:var(--orange-dark); transform:translateY(-1px); }
        .btn-cancel { width:100%; background:#F5EDE7; color:var(--brown); border:none; border-radius:10px; padding:0.72rem; font-weight:800; font-size:0.9rem; font-family:'Baloo 2',cursive; cursor:pointer; margin-top:0.5rem; text-decoration:none; display:block; text-align:center; transition:all 0.18s; }
        .btn-cancel:hover { background:#E8D8CC; }

        .alert-success { background:#E8F5E9; border:1.5px solid #A5D6A7; border-radius:10px; color:#2E7D32; padding:0.7rem 1rem; font-size:0.85rem; font-weight:600; margin-bottom:1.2rem; display:flex; gap:0.5rem; align-items:center; }
        .alert-error   { background:#FFF0ED; border:1.5px solid #FFCCBC; border-radius:10px; color:#BF360C; padding:0.7rem 1rem; font-size:0.85rem; font-weight:600; margin-bottom:1.2rem; display:flex; gap:0.5rem; align-items:center; }

        .filter-bar { background:#fff; border:1.5px solid #F0E6DE; border-radius:14px; padding:0.9rem 1.2rem; display:flex; gap:0.6rem; align-items:center; margin-bottom:1.2rem; flex-wrap:wrap; }
        .ipt-sm { border:2px solid #E8D8CC; border-radius:8px; padding:0.45rem 0.75rem; font-family:'Nunito',sans-serif; font-size:0.85rem; color:var(--text-dark); background:#fff; outline:none; }
        .btn-filter { background:var(--orange); color:#fff; border:none; border-radius:8px; padding:0.45rem 1rem; font-weight:700; font-size:0.85rem; font-family:'Nunito',sans-serif; cursor:pointer; }
        .btn-reset  { background:#F5EDE7; color:var(--brown); border:none; border-radius:8px; padding:0.45rem 0.9rem; font-weight:700; font-size:0.85rem; font-family:'Nunito',sans-serif; cursor:pointer; text-decoration:none; }

        table { width:100%; border-collapse:collapse; }
        thead th { font-size:0.72rem; font-weight:800; text-transform:uppercase; letter-spacing:0.8px; color:var(--brown-light); padding:0.6rem 1rem; background:#FFF8F4; border-bottom:1.5px solid #F0E6DE; text-align:left; white-space:nowrap; }
        tbody td { padding:0.8rem 1rem; font-size:0.86rem; border-bottom:1px solid #F5EDE7; vertical-align:middle; }
        tbody tr:last-child td { border-bottom:none; }
        tbody tr:hover { background:#FFFAF7; }

        .type-badge { font-size:0.72rem; font-weight:800; padding:0.2rem 0.6rem; border-radius:6px; text-transform:uppercase; }
        .type-vaccination { background:#E3F2FD; color:#1565C0; }
        .type-vet_visit   { background:#E8F5E9; color:#2E7D32; }
        .type-health_note { background:#FFF8E1; color:#F57F17; }

        .btn-edit { background:#FFF0E8; color:var(--orange); border:none; border-radius:7px; padding:0.3rem 0.7rem; font-size:0.78rem; font-weight:700; cursor:pointer; text-decoration:none; transition:all 0.18s; }
        .btn-edit:hover { background:var(--orange); color:#fff; }
        .btn-del  { background:#FFEBEE; color:#C62828; border:none; border-radius:7px; padding:0.3rem 0.7rem; font-size:0.78rem; font-weight:700; cursor:pointer; text-decoration:none; transition:all 0.18s; }
        .btn-del:hover  { background:#C62828; color:#fff; }

        .empty-state { text-align:center; padding:3rem; color:var(--text-muted); }
        .empty-state i { font-size:2.5rem; display:block; margin-bottom:0.5rem; color:#E8D8CC; }
    </style>
</head>
<body>
<?php require_once 'includes/sidebar.php'; ?>
<div class="main-wrap">
    <div class="page-title">Care Records</div>
    <div class="page-sub">Track vaccinations, vet visits, and health notes for pets.</div>

    <?php if ($success): ?><div class="alert-success"><i class="bi bi-check-circle-fill"></i><?= htmlspecialchars($success) ?></div><?php endif; ?>
    <?php if ($error):   ?><div class="alert-error"><i class="bi bi-exclamation-circle-fill"></i><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <div class="layout">

        <!-- ── Form ── -->
        <div class="card-box">
            <div class="card-title">
                <i class="bi bi-<?= $edit_record ? 'pencil-square' : 'plus-circle-fill' ?>" style="color:var(--orange)"></i>
                <?= $edit_record ? 'Edit Record' : 'Add Care Record' ?>
            </div>
            <form method="POST">
                <?php if ($edit_record): ?>
                <input type="hidden" name="record_id" value="<?= $edit_record['record_id'] ?>">
                <?php endif; ?>

                <div class="mb">
                    <label class="lbl">Pet *</label>
                    <select class="ipt" name="pet_id" required>
                        <option value="">Select a pet</option>
                        <?php
                        $pets_list2 = mysqli_query($conn, "SELECT pet_id, name, species FROM pets ORDER BY name");
                        while ($p = mysqli_fetch_assoc($pets_list2)):
                        ?>
                        <option value="<?= $p['pet_id'] ?>" <?= ($edit_record['pet_id'] ?? 0) == $p['pet_id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($p['name']) ?> (<?= $p['species'] ?>)
                        </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="mb">
                    <label class="lbl">Record Type *</label>
                    <select class="ipt" name="record_type" required>
                        <option value="">Select type</option>
                        <option value="vaccination" <?= ($edit_record['record_type'] ?? '') === 'vaccination' ? 'selected' : '' ?>>💉 Vaccination</option>
                        <option value="vet_visit"   <?= ($edit_record['record_type'] ?? '') === 'vet_visit'   ? 'selected' : '' ?>>🏥 Vet Visit</option>
                        <option value="health_note" <?= ($edit_record['record_type'] ?? '') === 'health_note' ? 'selected' : '' ?>>📋 Health Note</option>
                    </select>
                </div>

                <div class="mb">
                    <label class="lbl">Title *</label>
                    <input type="text" class="ipt" name="title" placeholder="e.g. Rabies Vaccine, Annual Checkup"
                           value="<?= htmlspecialchars($edit_record['title'] ?? '') ?>" required>
                </div>

                <div class="mb">
                    <label class="lbl">Date *</label>
                    <input type="date" class="ipt" name="date_of_record"
                           value="<?= $edit_record['date_of_record'] ?? date('Y-m-d') ?>" required>
                </div>

                <div class="mb">
                    <label class="lbl">Description / Notes</label>
                    <textarea class="ipt" name="description" rows="3"
                              placeholder="Details about the procedure..."><?= htmlspecialchars($edit_record['description'] ?? '') ?></textarea>
                </div>

                <button type="submit" class="btn-submit">
                    <i class="bi bi-<?= $edit_record ? 'check-circle-fill' : 'plus-circle-fill' ?> me-2"></i>
                    <?= $edit_record ? 'Update Record' : 'Add Record' ?>
                </button>
                <?php if ($edit_record): ?>
                <a href="care_records.php" class="btn-cancel"><i class="bi bi-x-circle me-1"></i>Cancel</a>
                <?php endif; ?>
            </form>
        </div>

        <!-- ── Records list ── -->
        <div>
            <form method="GET" class="filter-bar">
                <select class="ipt-sm" name="pet">
                    <option value="">All Pets</option>
                    <?php while ($p = mysqli_fetch_assoc($pets_list)): ?>
                    <option value="<?= $p['pet_id'] ?>" <?= $filter_pet == $p['pet_id'] ? 'selected' : '' ?>><?= htmlspecialchars($p['name']) ?></option>
                    <?php endwhile; ?>
                </select>
                <button type="submit" class="btn-filter"><i class="bi bi-funnel-fill me-1"></i>Filter</button>
                <a href="care_records.php" class="btn-reset">Reset</a>
            </form>

            <div class="card-box">
                <?php if (mysqli_num_rows($records) === 0): ?>
                <div class="empty-state"><i class="bi bi-clipboard2-pulse"></i>No care records found.</div>
                <?php else: ?>
                <div style="overflow-x:auto">
                <table>
                    <thead>
                        <tr>
                            <th>Pet</th>
                            <th>Type</th>
                            <th>Title</th>
                            <th>Date</th>
                            <th>Notes</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($rec = mysqli_fetch_assoc($records)): ?>
                        <tr>
                            <td style="font-weight:700">
                                <?= htmlspecialchars($rec['pet_name']) ?>
                                <span style="color:var(--text-muted);font-weight:400;font-size:0.78rem">(<?= $rec['species'] ?>)</span>
                            </td>
                            <td><span class="type-badge type-<?= $rec['record_type'] ?>"><?= ucfirst(str_replace('_', ' ', $rec['record_type'])) ?></span></td>
                            <td style="font-weight:600"><?= htmlspecialchars($rec['title']) ?></td>
                            <td style="white-space:nowrap"><?= date('M j, Y', strtotime($rec['date_of_record'])) ?></td>
                            <td style="color:var(--text-muted);max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
                                <?= htmlspecialchars($rec['description'] ?: '—') ?>
                            </td>
                            <td>
                                <div style="display:flex;gap:0.4rem">
                                    <a href="?edit=<?= $rec['record_id'] ?>" class="btn-edit"><i class="bi bi-pencil"></i></a>
                                    <a href="?delete=<?= $rec['record_id'] ?>" class="btn-del"
                                       onclick="return confirm('Delete this record?')"><i class="bi bi-trash3"></i></a>
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

    </div>
</div>
</body>
</html>