<?php
session_start();
require_once '../config/database.php';
require_once 'includes/auth_check.php';

$error    = '';
$success  = '';
$edit_pet = null;

// ── Archive pet ────────────────────────────────────────────────
if (isset($_GET['archive']) && is_numeric($_GET['archive'])) {
    $id   = (int)$_GET['archive'];
    $stmt = mysqli_prepare($conn, "SELECT name FROM pets WHERE pet_id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $pet = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    if ($pet) {
        $upd = mysqli_prepare($conn, "UPDATE pets SET status='archived' WHERE pet_id=?");
        mysqli_stmt_bind_param($upd, 'i', $id);
        mysqli_stmt_execute($upd);
        log_activity($conn, $_SESSION['user_id'], $_SESSION['full_name'], 'superadmin', 'archive_pet', "Archived pet: {$pet['name']}");
        $success = "Pet '{$pet['name']}' has been archived.";
    }
}

// ── Restore pet ────────────────────────────────────────────────
if (isset($_GET['restore']) && is_numeric($_GET['restore'])) {
    $id   = (int)$_GET['restore'];
    $stmt = mysqli_prepare($conn, "SELECT name FROM pets WHERE pet_id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $pet = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    if ($pet) {
        $upd = mysqli_prepare($conn, "UPDATE pets SET status='available' WHERE pet_id=?");
        mysqli_stmt_bind_param($upd, 'i', $id);
        mysqli_stmt_execute($upd);
        log_activity($conn, $_SESSION['user_id'], $_SESSION['full_name'], 'superadmin', 'restore_pet', "Restored pet: {$pet['name']}");
        $success = "Pet '{$pet['name']}' has been restored to available.";
    }
}

// ── Load pet for editing ───────────────────────────────────────
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $id   = (int)$_GET['edit'];
    $stmt = mysqli_prepare($conn, "SELECT * FROM pets WHERE pet_id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $edit_pet = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
}

// ── Add / Update pet ───────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name        = trim($_POST['name'] ?? '');
    $species     = trim($_POST['species'] ?? '');
    $breed       = trim($_POST['breed'] ?? '');
    $age_years   = (int)($_POST['age_years'] ?? 0);
    $age_months  = (int)($_POST['age_months'] ?? 0);
    $gender      = $_POST['gender'] ?? '';
    $description = trim($_POST['description'] ?? '');
    $status      = $_POST['status'] ?? 'available';
    $pet_id      = (int)($_POST['pet_id'] ?? 0);
    $image_path  = trim($_POST['existing_image_path'] ?? '');

    // ── Handle file upload ─────────────────────────────────────
    if (!empty($_FILES['photo_file']['name']) && $_FILES['photo_file']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../uploads/pets/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

        $ext     = strtolower(pathinfo($_FILES['photo_file']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg','jpeg','png','gif','webp'];

        if (!in_array($ext, $allowed)) {
            $error = 'Invalid file type. Only JPG, PNG, GIF, and WEBP are allowed.';
        } elseif ($_FILES['photo_file']['size'] > 5 * 1024 * 1024) {
            $error = 'File too large. Maximum size is 5MB.';
        } else {
            $safe_name = strtolower(preg_replace('/[^a-zA-Z0-9]/', '_', $name));
            $filename  = $safe_name . '_' . time() . '.' . $ext;
            $dest      = $upload_dir . $filename;
            if (move_uploaded_file($_FILES['photo_file']['tmp_name'], $dest)) {
                $image_path = 'uploads/pets/' . $filename;
            } else {
                $error = 'Upload failed. Please check folder permissions.';
            }
        }
    }

    if (empty($error)) {
        if (empty($name) || empty($species) || empty($gender)) {
            $error = 'Please fill in all required fields.';
        } else {
            if ($pet_id > 0) {
                $stmt = mysqli_prepare($conn,
                    "UPDATE pets SET name=?, species=?, breed=?, age_years=?, age_months=?, gender=?, description=?, status=?, image_path=? WHERE pet_id=?"
                );
                mysqli_stmt_bind_param($stmt, 'sssiissssi', $name, $species, $breed, $age_years, $age_months, $gender, $description, $status, $image_path, $pet_id);
                if (mysqli_stmt_execute($stmt)) {
                    log_activity($conn, $_SESSION['user_id'], $_SESSION['full_name'], 'superadmin', 'edit_pet', "Updated pet: $name");
                    $success  = "Pet '$name' updated successfully!";
                    $edit_pet = null;
                } else { $error = 'Update failed. Try again.'; }
            } else {
                $created_by = $_SESSION['user_id'];
                $stmt = mysqli_prepare($conn,
                    "INSERT INTO pets (name, species, breed, age_years, age_months, gender, description, status, image_path, created_by) VALUES (?,?,?,?,?,?,?,?,?,?)"
                );
                mysqli_stmt_bind_param($stmt, 'sssiissssi', $name, $species, $breed, $age_years, $age_months, $gender, $description, $status, $image_path, $created_by);
                if (mysqli_stmt_execute($stmt)) {
                    log_activity($conn, $_SESSION['user_id'], $_SESSION['full_name'], 'superadmin', 'add_pet', "Added pet: $name");
                    $success = "Pet '$name' added successfully!";
                } else { $error = 'Could not add pet. Try again.'; }
            }
        }
    }
}

// ── Fetch pets ─────────────────────────────────────────────────
$search  = trim($_GET['search'] ?? '');
$species = $_GET['species'] ?? '';
$tab     = $_GET['tab'] ?? 'active'; // active | archived

$sql = "SELECT * FROM pets WHERE 1=1";
if ($tab === 'archived') {
    $sql .= " AND status = 'archived'";
} else {
    $sql .= " AND status != 'archived'";
}
if ($search)  $sql .= " AND name LIKE '%" . mysqli_real_escape_string($conn, $search) . "%'";
if ($species) $sql .= " AND species = '" . mysqli_real_escape_string($conn, $species) . "'";
if ($tab !== 'archived' && isset($_GET['status']) && $_GET['status'] !== '') {
    $sql .= " AND status = '" . mysqli_real_escape_string($conn, $_GET['status']) . "'";
}
$sql .= " ORDER BY created_at DESC";
$pets = mysqli_query($conn, $sql);

// Tab counts
$r_active   = mysqli_query($conn, "SELECT COUNT(*) FROM pets WHERE status != 'archived'");
$r_archived = mysqli_query($conn, "SELECT COUNT(*) FROM pets WHERE status = 'archived'");
$cnt_active   = mysqli_fetch_row($r_active)[0];
$cnt_archived = mysqli_fetch_row($r_archived)[0];

$status_filter = $_GET['status'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Pets — PawPal Superadmin</title>
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
        .page-sub   { font-size:0.85rem; color:var(--text-muted); font-weight:600; margin-bottom:1.2rem; }

        /* Tabs */
        .tabs { display:flex; gap:0.5rem; margin-bottom:1.2rem; flex-wrap:wrap; }
        .tab-btn { display:inline-flex; align-items:center; gap:0.4rem; padding:0.5rem 1.1rem; border-radius:50px; font-size:0.82rem; font-weight:700; text-decoration:none; transition:all 0.18s; border:1.5px solid #F0E6DE; background:#fff; color:var(--brown); }
        .tab-btn:hover { border-color:var(--orange); color:var(--orange); }
        .tab-btn.active-tab { background:var(--orange); color:#fff; border-color:var(--orange); box-shadow:0 4px 12px rgba(255,112,67,0.25); }
        .tab-count { background:rgba(255,255,255,0.25); border-radius:50px; padding:0.05rem 0.45rem; font-size:0.75rem; font-weight:800; }
        .tab-btn:not(.active-tab) .tab-count { background:#F0E6DE; color:var(--brown); }

        .layout { display:grid; grid-template-columns:360px 1fr; gap:1.5rem; align-items:start; }
        .card-box { background:#fff; border:1.5px solid #F0E6DE; border-radius:16px; padding:1.4rem 1.5rem; }
        .card-title { font-size:1.1rem; font-weight:800; margin-bottom:1.1rem; display:flex; align-items:center; gap:0.5rem; }

        .lbl { display:block; font-size:0.73rem; font-weight:700; text-transform:uppercase; letter-spacing:0.5px; color:var(--brown); margin-bottom:0.3rem; }
        .ipt { width:100%; border:2px solid #E8D8CC; border-radius:10px; padding:0.62rem 0.9rem; font-family:'Nunito',sans-serif; font-size:0.88rem; color:var(--text-dark); background:#fff; outline:none; transition:border-color 0.2s; }
        .ipt:focus { border-color:var(--orange); box-shadow:0 0 0 3px rgba(255,112,67,0.12); }
        .mb { margin-bottom:0.9rem; }

        .upload-area { border:2px dashed #E8D8CC; border-radius:10px; padding:1rem; text-align:center; cursor:pointer; transition:all 0.2s; background:#FFFAF7; position:relative; }
        .upload-area:hover { border-color:var(--orange); background:#FFF5EF; }
        .upload-area input[type="file"] { position:absolute; inset:0; width:100%; height:100%; opacity:0; cursor:pointer; }
        .upload-icon { font-size:1.6rem; color:#E8D8CC; display:block; margin-bottom:0.3rem; }
        .upload-text { font-size:0.8rem; color:var(--text-muted); font-weight:600; }
        .upload-sub  { font-size:0.72rem; color:#A1887F; margin-top:0.2rem; }

        .photo-preview-wrap { display:none; margin-top:0.7rem; text-align:center; }
        .photo-preview-wrap img { width:80px; height:80px; object-fit:cover; border-radius:10px; border:2px solid #F0E6DE; }
        .preview-name { font-size:0.75rem; color:var(--text-muted); margin-top:0.3rem; font-weight:600; }

        .current-photo-strip { display:flex; align-items:center; gap:0.7rem; background:#FFF5EF; border-radius:10px; padding:0.55rem 0.75rem; margin-bottom:0.6rem; }
        .current-photo-strip img { width:44px; height:44px; object-fit:cover; border-radius:8px; border:1.5px solid #F0E6DE; }
        .current-photo-label { font-size:0.75rem; font-weight:700; color:var(--text-muted); }

        .btn-submit { width:100%; background:var(--orange); color:#fff; border:none; border-radius:10px; padding:0.72rem; font-weight:800; font-size:0.95rem; font-family:'Baloo 2',cursive; cursor:pointer; box-shadow:0 4px 14px rgba(255,112,67,0.28); transition:all 0.2s; }
        .btn-submit:hover { background:var(--orange-dark); transform:translateY(-1px); }
        .btn-cancel { width:100%; background:#F5EDE7; color:var(--brown); border:none; border-radius:10px; padding:0.72rem; font-weight:800; font-size:0.9rem; font-family:'Baloo 2',cursive; cursor:pointer; margin-top:0.5rem; text-decoration:none; display:block; text-align:center; transition:all 0.18s; }
        .btn-cancel:hover { background:#E8D8CC; }

        .alert-success { background:#E8F5E9; border:1.5px solid #A5D6A7; border-radius:10px; color:#2E7D32; padding:0.7rem 1rem; font-size:0.85rem; font-weight:600; margin-bottom:1.2rem; display:flex; gap:0.5rem; align-items:center; }
        .alert-error   { background:#FFF0ED; border:1.5px solid #FFCCBC; border-radius:10px; color:#BF360C; padding:0.7rem 1rem; font-size:0.85rem; font-weight:600; margin-bottom:1.2rem; display:flex; gap:0.5rem; align-items:center; }

        .filter-bar { background:#fff; border:1.5px solid #F0E6DE; border-radius:14px; padding:0.9rem 1.2rem; display:flex; gap:0.6rem; flex-wrap:wrap; align-items:center; margin-bottom:1.2rem; }
        .ipt-sm { border:2px solid #E8D8CC; border-radius:8px; padding:0.45rem 0.75rem; font-family:'Nunito',sans-serif; font-size:0.85rem; color:var(--text-dark); background:#fff; outline:none; }
        .ipt-sm:focus { border-color:var(--orange); }
        .btn-filter { background:var(--orange); color:#fff; border:none; border-radius:8px; padding:0.45rem 1rem; font-weight:700; font-size:0.85rem; font-family:'Nunito',sans-serif; cursor:pointer; }
        .btn-reset  { background:#F5EDE7; color:var(--brown); border:none; border-radius:8px; padding:0.45rem 0.9rem; font-weight:700; font-size:0.85rem; font-family:'Nunito',sans-serif; cursor:pointer; text-decoration:none; }

        /* Pet grid */
        .pets-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(200px,1fr)); gap:1rem; }
        .pet-card { background:#fff; border:1.5px solid #F0E6DE; border-radius:14px; overflow:hidden; transition:all 0.2s; }
        .pet-card:hover { box-shadow:0 6px 20px rgba(255,112,67,0.12); transform:translateY(-2px); }
        .pet-card.archived-card { opacity:0.7; border-style:dashed; }
        .pet-photo { width:100%; height:140px; background:#F5EDE7; display:flex; align-items:center; justify-content:center; font-size:2.5rem; }
        .pet-body { padding:0.8rem; }
        .pet-name { font-weight:800; font-size:0.95rem; color:var(--text-dark); }
        .pet-meta { font-size:0.78rem; color:var(--text-muted); font-weight:600; margin-bottom:0.5rem; }
        .pet-actions { display:flex; gap:0.4rem; margin-top:0.6rem; flex-wrap:wrap; }

        .btn-edit    { flex:1; background:#FFF0E8; color:var(--orange); border:none; border-radius:8px; padding:0.35rem; font-size:0.78rem; font-weight:700; cursor:pointer; text-decoration:none; text-align:center; transition:all 0.18s; }
        .btn-edit:hover { background:var(--orange); color:#fff; }
        .btn-archive { flex:1; background:#FFEBEE; color:#C62828; border:none; border-radius:8px; padding:0.35rem; font-size:0.78rem; font-weight:700; cursor:pointer; text-decoration:none; text-align:center; transition:all 0.18s; }
        .btn-archive:hover { background:#C62828; color:#fff; }
        .btn-restore { flex:1; background:#E8F5E9; color:#2E7D32; border:none; border-radius:8px; padding:0.35rem; font-size:0.78rem; font-weight:700; cursor:pointer; text-decoration:none; text-align:center; transition:all 0.18s; }
        .btn-restore:hover { background:#2E7D32; color:#fff; }

        /* Status badges */
        .badge-available { background:#E8F5E9; color:#2E7D32; font-size:0.68rem; font-weight:800; padding:0.15rem 0.55rem; border-radius:50px; text-transform:uppercase; }
        .badge-adopted   { background:#EDE7F6; color:#4527A0; font-size:0.68rem; font-weight:800; padding:0.15rem 0.55rem; border-radius:50px; text-transform:uppercase; }
        .badge-pending   { background:#FFF8E1; color:#F57F17; font-size:0.68rem; font-weight:800; padding:0.15rem 0.55rem; border-radius:50px; text-transform:uppercase; }
        .badge-archived  { background:#F5F5F5; color:#757575; font-size:0.68rem; font-weight:800; padding:0.15rem 0.55rem; border-radius:50px; text-transform:uppercase; }

        .empty-state { text-align:center; padding:2.5rem; color:var(--text-muted); }
        .empty-state i { font-size:2.5rem; display:block; margin-bottom:0.5rem; color:#E8D8CC; }

        @keyframes popIn { from{opacity:0;transform:scale(0.92) translateY(10px)} to{opacity:1;transform:scale(1) translateY(0)} }
    </style>
</head>
<body>
<?php require_once 'includes/sidebar.php'; ?>
<div class="main-wrap">
    <div class="page-title">Manage Pets</div>
    <div class="page-sub">Add, edit, archive, or restore pets in the system.</div>

    <?php if ($success): ?>
    <div class="alert-success"><i class="bi bi-check-circle-fill"></i><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
    <div class="alert-error"><i class="bi bi-exclamation-circle-fill"></i><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="layout">

        <!-- ── Add / Edit Form ── -->
        <div class="card-box">
            <div class="card-title">
                <i class="bi bi-<?= $edit_pet ? 'pencil-square' : 'plus-circle-fill' ?>" style="color:var(--orange)"></i>
                <?= $edit_pet ? 'Edit Pet' : 'Add New Pet' ?>
            </div>

            <form method="POST" enctype="multipart/form-data">
                <?php if ($edit_pet): ?>
                <input type="hidden" name="pet_id" value="<?= $edit_pet['pet_id'] ?>">
                <input type="hidden" name="existing_image_path" value="<?= htmlspecialchars($edit_pet['image_path'] ?? '') ?>">
                <?php endif; ?>

                <div class="mb">
                    <label class="lbl">Pet Name *</label>
                    <input type="text" class="ipt" name="name" placeholder="e.g. Buddy"
                           value="<?= htmlspecialchars($edit_pet['name'] ?? $_POST['name'] ?? '') ?>" required>
                </div>

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.7rem" class="mb">
                    <div>
                        <label class="lbl">Species *</label>
                        <select class="ipt" name="species" required>
                            <option value="">Select</option>
                            <?php foreach (['Dog','Cat','Bird','Rabbit','Other'] as $s): ?>
                            <option value="<?= $s ?>" <?= ($edit_pet['species'] ?? '') === $s ? 'selected' : '' ?>><?= $s ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="lbl">Gender *</label>
                        <select class="ipt" name="gender" required>
                            <option value="">Select</option>
                            <option value="male"   <?= ($edit_pet['gender'] ?? '') === 'male'   ? 'selected' : '' ?>>Male</option>
                            <option value="female" <?= ($edit_pet['gender'] ?? '') === 'female' ? 'selected' : '' ?>>Female</option>
                        </select>
                    </div>
                </div>

                <div class="mb">
                    <label class="lbl">Breed</label>
                    <input type="text" class="ipt" name="breed" placeholder="e.g. Aspin"
                           value="<?= htmlspecialchars($edit_pet['breed'] ?? '') ?>">
                </div>

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.7rem" class="mb">
                    <div>
                        <label class="lbl">Age — Years</label>
                        <input type="number" class="ipt" name="age_years" min="0" max="30" placeholder="0"
                               value="<?= (int)($edit_pet['age_years'] ?? 0) ?>">
                    </div>
                    <div>
                        <label class="lbl">Age — Months</label>
                        <input type="number" class="ipt" name="age_months" min="0" max="11" placeholder="0"
                               value="<?= (int)($edit_pet['age_months'] ?? 0) ?>">
                    </div>
                </div>

                <div class="mb">
                    <label class="lbl">Status</label>
                    <select class="ipt" name="status">
                        <option value="available" <?= ($edit_pet['status'] ?? 'available') === 'available' ? 'selected' : '' ?>>Available</option>
                        <option value="adopted"   <?= ($edit_pet['status'] ?? '') === 'adopted'   ? 'selected' : '' ?>>Adopted</option>
                        <option value="pending"   <?= ($edit_pet['status'] ?? '') === 'pending'   ? 'selected' : '' ?>>Pending</option>
                        <option value="archived"  <?= ($edit_pet['status'] ?? '') === 'archived'  ? 'selected' : '' ?>>Archived</option>
                    </select>
                </div>

                <!-- Photo Upload -->
                <div class="mb">
                    <label class="lbl">Pet Photo</label>
                    <?php if (!empty($edit_pet['image_path'])): ?>
                    <div class="current-photo-strip">
                        <img src="../<?= htmlspecialchars($edit_pet['image_path']) ?>" alt="Current photo">
                        <div>
                            <div class="current-photo-label">Current photo</div>
                            <div style="font-size:0.72rem;color:#A1887F">Upload a new one to replace it</div>
                        </div>
                    </div>
                    <?php endif; ?>
                    <div class="upload-area" id="uploadArea">
                        <input type="file" name="photo_file" id="photoFile" accept="image/jpeg,image/png,image/gif,image/webp">
                        <i class="bi bi-cloud-arrow-up upload-icon"></i>
                        <div class="upload-text">Click or drag &amp; drop a photo here</div>
                        <div class="upload-sub">JPG, PNG, GIF, WEBP · Max 5 MB</div>
                    </div>
                    <div class="photo-preview-wrap" id="previewWrap">
                        <img id="previewImg" src="#" alt="Preview">
                        <div class="preview-name" id="previewName"></div>
                    </div>
                </div>

                <div class="mb">
                    <label class="lbl">Description</label>
                    <textarea class="ipt" name="description" rows="3"
                              placeholder="Tell adopters about this pet..."><?= htmlspecialchars($edit_pet['description'] ?? '') ?></textarea>
                </div>

                <button type="submit" class="btn-submit">
                    <i class="bi bi-<?= $edit_pet ? 'check-circle-fill' : 'plus-circle-fill' ?> me-2"></i>
                    <?= $edit_pet ? 'Update Pet' : 'Add Pet' ?>
                </button>
                <?php if ($edit_pet): ?>
                <a href="manage_pets.php" class="btn-cancel"><i class="bi bi-x-circle me-1"></i>Cancel Edit</a>
                <?php endif; ?>
            </form>
        </div>

        <!-- ── Pet List ── -->
        <div>
            <!-- Tabs -->
            <div class="tabs">
                <a href="?tab=active" class="tab-btn <?= $tab === 'active' ? 'active-tab' : '' ?>">
                    <i class="bi bi-heart-fill"></i> Active Pets
                    <span class="tab-count"><?= $cnt_active ?></span>
                </a>
                <a href="?tab=archived" class="tab-btn <?= $tab === 'archived' ? 'active-tab' : '' ?>">
                    <i class="bi bi-archive-fill"></i> Archived
                    <span class="tab-count"><?= $cnt_archived ?></span>
                </a>
            </div>

            <!-- Filters -->
            <form method="GET" class="filter-bar">
                <input type="hidden" name="tab" value="<?= htmlspecialchars($tab) ?>">
                <input type="text" class="ipt-sm" name="search" placeholder="🔍 Search name..."
                       value="<?= htmlspecialchars($search) ?>" style="min-width:160px">
                <select class="ipt-sm" name="species">
                    <option value="">All Species</option>
                    <?php foreach (['Dog','Cat','Bird','Rabbit','Other'] as $s): ?>
                    <option value="<?= $s ?>" <?= $species === $s ? 'selected' : '' ?>><?= $s ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if ($tab === 'active'): ?>
                <select class="ipt-sm" name="status">
                    <option value="">All Status</option>
                    <option value="available" <?= $status_filter === 'available' ? 'selected' : '' ?>>Available</option>
                    <option value="adopted"   <?= $status_filter === 'adopted'   ? 'selected' : '' ?>>Adopted</option>
                    <option value="pending"   <?= $status_filter === 'pending'   ? 'selected' : '' ?>>Pending</option>
                </select>
                <?php endif; ?>
                <button type="submit" class="btn-filter"><i class="bi bi-funnel-fill me-1"></i>Filter</button>
                <a href="?tab=<?= $tab ?>" class="btn-reset">Reset</a>
            </form>

            <?php if (mysqli_num_rows($pets) === 0): ?>
            <div class="card-box">
                <div class="empty-state">
                    <i class="bi bi-<?= $tab === 'archived' ? 'archive' : 'heart' ?>"></i>
                    No <?= $tab === 'archived' ? 'archived' : 'active' ?> pets found.
                </div>
            </div>
            <?php else: ?>
            <div class="pets-grid">
                <?php while ($pet = mysqli_fetch_assoc($pets)): ?>
                <div class="pet-card <?= $pet['status'] === 'archived' ? 'archived-card' : '' ?>">
                    <?php if (!empty($pet['image_path'])): ?>
                        <img src="../<?= htmlspecialchars($pet['image_path']) ?>"
                             alt="<?= htmlspecialchars($pet['name']) ?>"
                             style="width:100%;height:140px;object-fit:cover<?= $pet['status'] === 'archived' ? ';filter:grayscale(60%)' : '' ?>">
                    <?php else: ?>
                        <div class="pet-photo">
                            <?= $pet['species'] === 'Cat' ? '🐱' :
                               ($pet['species'] === 'Bird' ? '🐦' :
                               ($pet['species'] === 'Rabbit' ? '🐰' : '🐶')) ?>
                        </div>
                    <?php endif; ?>
                    <div class="pet-body">
                        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:0.2rem">
                            <div class="pet-name"><?= htmlspecialchars($pet['name']) ?></div>
                            <span class="badge-<?= $pet['status'] ?>"><?= ucfirst($pet['status']) ?></span>
                        </div>
                        <div class="pet-meta">
                            <?= htmlspecialchars($pet['breed'] ?: $pet['species']) ?>
                            · <?= ucfirst(htmlspecialchars($pet['gender'])) ?>
                            <?php
                                $age_parts = [];
                                if (!empty($pet['age_years']))  $age_parts[] = $pet['age_years']  . 'y';
                                if (!empty($pet['age_months'])) $age_parts[] = $pet['age_months'] . 'mo';
                                if ($age_parts) echo '· ' . implode(' ', $age_parts);
                            ?>
                        </div>
                        <div class="pet-actions">
                            <?php if ($pet['status'] !== 'archived'): ?>
                                <a href="?edit=<?= $pet['pet_id'] ?>&tab=<?= $tab ?>" class="btn-edit">
                                    <i class="bi bi-pencil"></i> Edit
                                </a>
                                <a href="?archive=<?= $pet['pet_id'] ?>&tab=<?= $tab ?>"
                                   class="btn-archive"
                                   onclick="return confirm('Archive <?= htmlspecialchars($pet['name']) ?>?\nThey will be hidden from listings but data is preserved.')">
                                    <i class="bi bi-archive"></i> Archive
                                </a>
                            <?php else: ?>
                                <a href="?restore=<?= $pet['pet_id'] ?>&tab=archived"
                                   class="btn-restore"
                                   onclick="return confirm('Restore <?= htmlspecialchars($pet['name']) ?> to available?')">
                                    <i class="bi bi-arrow-counterclockwise"></i> Restore
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
            <?php endif; ?>
        </div>

    </div>
</div>

<script>
document.getElementById('photoFile').addEventListener('change', function () {
    const file = this.files[0];
    if (!file) return;
    const wrap = document.getElementById('previewWrap');
    const img  = document.getElementById('previewImg');
    const name = document.getElementById('previewName');
    const reader = new FileReader();
    reader.onload = function (e) {
        img.src = e.target.result;
        name.textContent = file.name + ' (' + (file.size / 1024).toFixed(1) + ' KB)';
        wrap.style.display = 'block';
    };
    reader.readAsDataURL(file);
    document.querySelector('#uploadArea .upload-text').textContent = '✅ ' + file.name;
});
</script>
</body>
</html>