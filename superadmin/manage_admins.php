<?php
session_start();
require_once '../config/database.php';
require_once 'includes/auth_check.php';

$error   = '';
$success = '';

// ── Add admin ──────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_admin'])) {
    $first  = trim($_POST['first_name'] ?? '');
    $last   = trim($_POST['last_name'] ?? '');
    $email  = trim($_POST['email'] ?? '');
    $pass   = $_POST['password'] ?? '';
    $full   = trim("$first $last");

    if (empty($first) || empty($last) || empty($email) || empty($pass)) {
        $error = 'Please fill in all required fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (strlen($pass) < 8) {
        $error = 'Password must be at least 8 characters.';
    } else {
        $chk = mysqli_prepare($conn, "SELECT user_id FROM users WHERE email = ?");
        mysqli_stmt_bind_param($chk, 's', $email);
        mysqli_stmt_execute($chk);
        mysqli_stmt_store_result($chk);

        if (mysqli_stmt_num_rows($chk) > 0) {
            $error = 'An account with this email already exists.';
        } else {
            $hashed = password_hash($pass, PASSWORD_DEFAULT);
            $ins = mysqli_prepare($conn,
                "INSERT INTO users (full_name, email, password, role) VALUES (?, ?, ?, 'admin')"
            );
            mysqli_stmt_bind_param($ins, 'sss', $full, $email, $hashed);
            if (mysqli_stmt_execute($ins)) {
                log_activity($conn, $_SESSION['user_id'], $_SESSION['full_name'], 'superadmin', 'create_admin', "Created admin: $email");
                $success = "Admin account for $full created successfully!";
            } else {
                $error = 'Something went wrong. Please try again.';
            }
        }
    }
}

// ── Remove admin ───────────────────────────────────────────────
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $del_id = (int) $_GET['delete'];
    $chk = mysqli_prepare($conn, "SELECT full_name, email, role FROM users WHERE user_id = ?");
    mysqli_stmt_bind_param($chk, 'i', $del_id);
    mysqli_stmt_execute($chk);
    $res = mysqli_stmt_get_result($chk);
    $target = mysqli_fetch_assoc($res);

    if ($target && $target['role'] === 'admin') {
        $del = mysqli_prepare($conn, "DELETE FROM users WHERE user_id = ? AND role = 'admin'");
        mysqli_stmt_bind_param($del, 'i', $del_id);
        if (mysqli_stmt_execute($del)) {
            log_activity($conn, $_SESSION['user_id'], $_SESSION['full_name'], 'superadmin', 'delete_admin', "Removed admin: {$target['email']}");
            $success = "Admin {$target['full_name']} has been removed.";
        }
    } else {
        $error = 'Invalid action.';
    }
}

// ── Fetch all admins ───────────────────────────────────────────
$admins = mysqli_query($conn,
    "SELECT user_id, full_name, email, contact_number, created_at
     FROM users WHERE role = 'admin' ORDER BY created_at DESC"
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Admins — PawPal Superadmin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Baloo+2:wght@400;600;700;800&family=Nunito:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --cream: #FFF8F0; --orange: #FF7043; --orange-dark: #E64A19;
            --brown: #6D4C41; --brown-light: #A1887F;
            --text-dark: #3E2723; --text-muted: #795548;
            --sidebar-w: 240px;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Nunito', sans-serif; background: var(--cream); color: var(--text-dark); min-height: 100vh; }
        h1,h2,h3,h4 { font-family: 'Baloo 2', cursive; }
        .main-wrap { margin-left: var(--sidebar-w); padding: 2rem 2.2rem; min-height: 100vh; }
        .page-title { font-size: 1.7rem; font-weight: 800; margin-bottom: 0.2rem; }
        .page-sub   { font-size: 0.85rem; color: var(--text-muted); font-weight: 600; margin-bottom: 2rem; }

        .two-col { display: grid; grid-template-columns: 380px 1fr; gap: 1.5rem; align-items: start; }

        /* Form card */
        .card-box {
            background: #fff; border: 1.5px solid #F0E6DE;
            border-radius: 16px; padding: 1.5rem;
        }
        .card-title { font-size: 1.1rem; font-weight: 800; color: var(--text-dark); margin-bottom: 1.2rem; display: flex; align-items: center; gap: 0.5rem; }

        .lbl { display: block; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: var(--brown); margin-bottom: 0.3rem; }
        .ipt {
            width: 100%; border: 2px solid #E8D8CC; border-radius: 10px;
            padding: 0.65rem 0.9rem; font-family: 'Nunito', sans-serif;
            font-size: 0.9rem; color: var(--text-dark); background: #fff;
            transition: border-color 0.2s; outline: none;
        }
        .ipt:focus { border-color: var(--orange); box-shadow: 0 0 0 3px rgba(255,112,67,0.12); }
        .mb { margin-bottom: 1rem; }

        .btn-primary-custom {
            width: 100%; background: var(--orange); color: #fff; border: none;
            border-radius: 10px; padding: 0.75rem; font-weight: 800; font-size: 0.95rem;
            font-family: 'Baloo 2', cursive; cursor: pointer;
            box-shadow: 0 4px 14px rgba(255,112,67,0.28); transition: all 0.2s;
        }
        .btn-primary-custom:hover { background: var(--orange-dark); transform: translateY(-1px); }

        /* Alerts */
        .alert-success { background:#E8F5E9; border:1.5px solid #A5D6A7; border-radius:10px; color:#2E7D32; padding:0.7rem 1rem; font-size:0.85rem; font-weight:600; margin-bottom:1.2rem; display:flex; gap:0.5rem; align-items:center; }
        .alert-error   { background:#FFF0ED; border:1.5px solid #FFCCBC; border-radius:10px; color:#BF360C; padding:0.7rem 1rem; font-size:0.85rem; font-weight:600; margin-bottom:1.2rem; display:flex; gap:0.5rem; align-items:center; }

        /* Table */
        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        thead th { font-size: 0.72rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.8px; color: var(--brown-light); padding: 0.6rem 1rem; background: #FFF8F4; border-bottom: 1.5px solid #F0E6DE; text-align: left; }
        tbody td { padding: 0.85rem 1rem; font-size: 0.88rem; border-bottom: 1px solid #F5EDE7; vertical-align: middle; }
        tbody tr:last-child td { border-bottom: none; }
        tbody tr:hover { background: #FFFAF7; }

        .avatar-sm {
            width: 32px; height: 32px; border-radius: 50%;
            background: var(--orange); color: #fff;
            font-weight: 800; font-size: 0.85rem;
            display: inline-flex; align-items: center; justify-content: center;
            margin-right: 0.5rem;
        }
        .btn-del {
            background: #FFEBEE; color: #C62828; border: none;
            border-radius: 8px; padding: 0.35rem 0.75rem;
            font-size: 0.8rem; font-weight: 700; cursor: pointer;
            transition: all 0.18s; text-decoration: none;
            display: inline-flex; align-items: center; gap: 0.3rem;
        }
        .btn-del:hover { background: #C62828; color: #fff; }

        .empty-state { text-align: center; padding: 2.5rem 1rem; color: var(--text-muted); font-size: 0.9rem; }
        .empty-state i { font-size: 2.5rem; display: block; margin-bottom: 0.5rem; color: #E8D8CC; }
    </style>
</head>
<body>
<?php require_once 'includes/sidebar.php'; ?>
<div class="main-wrap">
    <div class="page-title">Manage Admins</div>
    <div class="page-sub">Add or remove admin accounts. Admins can manage pets and applications.</div>

    <?php if ($success): ?>
    <div class="alert-success"><i class="bi bi-check-circle-fill"></i><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
    <div class="alert-error"><i class="bi bi-exclamation-circle-fill"></i><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="two-col">

        <!-- Add Admin Form -->
        <div class="card-box">
            <div class="card-title"><i class="bi bi-person-plus-fill" style="color:var(--orange)"></i> Add New Admin</div>
            <form method="POST">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.7rem;" class="mb">
                    <div>
                        <label class="lbl">First Name *</label>
                        <input type="text" class="ipt" name="first_name" placeholder="Juan" value="<?= htmlspecialchars($_POST['first_name'] ?? '') ?>" required>
                    </div>
                    <div>
                        <label class="lbl">Last Name *</label>
                        <input type="text" class="ipt" name="last_name" placeholder="dela Cruz" value="<?= htmlspecialchars($_POST['last_name'] ?? '') ?>" required>
                    </div>
                </div>
                <div class="mb">
                    <label class="lbl">Email Address *</label>
                    <input type="email" class="ipt" name="email" placeholder="admin@pawpal.com" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
                </div>
                <div class="mb">
                    <label class="lbl">Password * <span style="font-weight:500;text-transform:none;font-size:0.7rem;color:var(--brown-light)">(min. 8 characters)</span></label>
                    <input type="password" class="ipt" name="password" placeholder="Set a strong password" required>
                </div>
                <button type="submit" name="add_admin" class="btn-primary-custom">
                    <i class="bi bi-person-plus-fill me-2"></i>Create Admin Account
                </button>
            </form>
        </div>

        <!-- Admins Table -->
        <div class="card-box">
            <div class="card-title"><i class="bi bi-shield-lock-fill" style="color:var(--orange)"></i> Current Admins (<?= mysqli_num_rows($admins) ?>)</div>
            <?php if (mysqli_num_rows($admins) === 0): ?>
            <div class="empty-state">
                <i class="bi bi-people"></i>
                No admins yet. Add one using the form.
            </div>
            <?php else: ?>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Admin</th>
                            <th>Email</th>
                            <th>Joined</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($admin = mysqli_fetch_assoc($admins)): ?>
                        <tr>
                            <td>
                                <span class="avatar-sm"><?= strtoupper(substr($admin['full_name'], 0, 1)) ?></span>
                                <?= htmlspecialchars($admin['full_name']) ?>
                            </td>
                            <td><?= htmlspecialchars($admin['email']) ?></td>
                            <td><?= date('M j, Y', strtotime($admin['created_at'])) ?></td>
                            <td>
                                <a href="?delete=<?= $admin['user_id'] ?>"
                                   class="btn-del"
                                   onclick="return confirm('Remove <?= htmlspecialchars($admin['full_name']) ?> as admin?')">
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
</div>
</body>
</html>
