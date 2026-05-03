<?php
session_start();
require_once '../config/database.php';
require_once 'includes/auth_check.php';

$success_email    = '';
$error_email      = '';
$success_password = '';
$error_password   = '';

$user_id = $_SESSION['user_id'];

// ── Fetch current user data ───────────────────────────────────
$stmt = mysqli_prepare($conn, "SELECT full_name, email FROM users WHERE user_id = ?");
mysqli_stmt_bind_param($stmt, 'i', $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$user   = mysqli_fetch_assoc($result);

// ── Handle Email Update ───────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_email'])) {
    $new_email    = trim($_POST['new_email'] ?? '');
    $confirm_pass = $_POST['confirm_pass_email'] ?? '';

    if (empty($new_email)) {
        $error_email = 'Please enter a new email address.';
    } elseif (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
        $error_email = 'Please enter a valid email address.';
    } elseif (empty($confirm_pass)) {
        $error_email = 'Please enter your current password to confirm.';
    } else {
        // Verify current password
        $chk = mysqli_prepare($conn, "SELECT password FROM users WHERE user_id = ?");
        mysqli_stmt_bind_param($chk, 'i', $user_id);
        mysqli_stmt_execute($chk);
        $chk_result = mysqli_stmt_get_result($chk);
        $chk_user   = mysqli_fetch_assoc($chk_result);

        if (!password_verify($confirm_pass, $chk_user['password'])) {
            $error_email = 'Current password is incorrect.';
        } else {
            // Check email not already taken
            $taken = mysqli_prepare($conn, "SELECT user_id FROM users WHERE email = ? AND user_id != ?");
            mysqli_stmt_bind_param($taken, 'si', $new_email, $user_id);
            mysqli_stmt_execute($taken);
            mysqli_stmt_store_result($taken);

            if (mysqli_stmt_num_rows($taken) > 0) {
                $error_email = 'That email is already in use by another account.';
            } else {
                $upd = mysqli_prepare($conn, "UPDATE users SET email = ? WHERE user_id = ?");
                mysqli_stmt_bind_param($upd, 'si', $new_email, $user_id);
                if (mysqli_stmt_execute($upd)) {
                    $success_email = 'Email updated successfully!';
                    $user['email'] = $new_email;
                } else {
                    $error_email = 'Something went wrong. Please try again.';
                }
            }
        }
    }
}

// ── Handle Password Update ────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_password'])) {
    $current_pass = $_POST['current_password'] ?? '';
    $new_pass     = $_POST['new_password'] ?? '';
    $confirm_pass = $_POST['confirm_new_password'] ?? '';

    if (empty($current_pass) || empty($new_pass) || empty($confirm_pass)) {
        $error_password = 'Please fill in all password fields.';
    } elseif (strlen($new_pass) < 8) {
        $error_password = 'New password must be at least 8 characters.';
    } elseif ($new_pass !== $confirm_pass) {
        $error_password = 'New passwords do not match.';
    } else {
        // Verify current password
        $chk = mysqli_prepare($conn, "SELECT password FROM users WHERE user_id = ?");
        mysqli_stmt_bind_param($chk, 'i', $user_id);
        mysqli_stmt_execute($chk);
        $chk_result = mysqli_stmt_get_result($chk);
        $chk_user   = mysqli_fetch_assoc($chk_result);

        if (!password_verify($current_pass, $chk_user['password'])) {
            $error_password = 'Current password is incorrect.';
        } elseif ($current_pass === $new_pass) {
            $error_password = 'New password must be different from your current password.';
        } else {
            $hashed = password_hash($new_pass, PASSWORD_DEFAULT);
            $upd = mysqli_prepare($conn, "UPDATE users SET password = ? WHERE user_id = ?");
            mysqli_stmt_bind_param($upd, 'si', $hashed, $user_id);
            if (mysqli_stmt_execute($upd)) {
                $success_password = 'Password updated successfully!';
            } else {
                $error_password = 'Something went wrong. Please try again.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings — PawPal Superadmin</title>
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
            max-width: 860px;
        }

        /* Topbar */
        .topbar {
            display: flex; align-items: center;
            justify-content: space-between;
            margin-bottom: 2rem;
        }
        .page-title { font-size: 1.7rem; font-weight: 800; color: var(--text-dark); }
        .page-sub   { font-size: 0.85rem; color: var(--text-muted); font-weight: 600; }

        /* Settings card */
        .settings-card {
            background: #fff;
            border: 1.5px solid #F0E6DE;
            border-radius: 18px;
            padding: 1.8rem 2rem;
            margin-bottom: 1.4rem;
        }
        .card-title {
            font-size: 1.1rem; font-weight: 800;
            color: var(--text-dark);
            display: flex; align-items: center; gap: 0.5rem;
            margin-bottom: 0.3rem;
        }
        .card-title i { color: var(--orange); }
        .card-sub {
            font-size: 0.83rem; color: var(--text-muted);
            font-weight: 600; margin-bottom: 1.4rem;
            padding-bottom: 1.1rem;
            border-bottom: 1.5px solid #F0E6DE;
        }

        /* Form elements */
        .lbl {
            display: block; font-size: 0.75rem; font-weight: 700;
            text-transform: uppercase; letter-spacing: 0.5px;
            color: var(--brown); margin-bottom: 0.3rem;
        }
        .ipt {
            width: 100%; border: 2px solid #E8D8CC; border-radius: 12px;
            padding: 0.68rem 1rem; font-family: 'Nunito', sans-serif;
            font-size: 0.92rem; color: var(--text-dark);
            background: #fff; transition: border-color 0.2s, box-shadow 0.2s; outline: none;
        }
        .ipt:focus { border-color: var(--orange); box-shadow: 0 0 0 3.5px rgba(255,112,67,0.13); }
        .ipt:disabled { background: #FAF4EF; color: var(--brown-light); cursor: not-allowed; }

        .ipt-group { display: flex; }
        .ipt-group .ipt { border-right: none; border-radius: 12px 0 0 12px; flex: 1; }
        .ipt-toggle {
            background: #fff; border: 2px solid #E8D8CC;
            border-left: none; border-radius: 0 12px 12px 0;
            padding: 0 0.9rem; cursor: pointer;
            color: var(--brown-light); font-size: 1rem;
            transition: color 0.2s, border-color 0.2s;
            display: flex; align-items: center;
        }
        .ipt-group:focus-within .ipt,
        .ipt-group:focus-within .ipt-toggle { border-color: var(--orange); }
        .ipt-toggle:hover { color: var(--orange); }

        .field { margin-bottom: 1rem; }

        /* Strength bar */
        .strength-track { height: 4px; border-radius: 4px; background: #E8D8CC; margin-top: 6px; overflow: hidden; }
        .strength-fill { height: 100%; border-radius: 4px; width: 0; transition: width 0.3s, background 0.3s; }
        .strength-lbl { font-size: 0.72rem; font-weight: 700; margin-top: 3px; }
        .match-msg { font-size: 0.72rem; font-weight: 700; margin-top: 4px; }

        /* Alerts */
        .err-banner {
            background: #FFF0ED; border: 1.5px solid #FFCCBC;
            border-radius: 10px; color: #BF360C;
            font-size: 0.85rem; font-weight: 600;
            padding: 0.7rem 1rem; display: flex; align-items: center; gap: 0.5rem;
            margin-bottom: 1.1rem; animation: shake 0.38s ease;
        }
        .ok-banner {
            background: #F1F8E9; border: 1.5px solid #C5E1A5;
            border-radius: 10px; color: #2E7D32;
            font-size: 0.85rem; font-weight: 600;
            padding: 0.7rem 1rem; display: flex; align-items: center; gap: 0.5rem;
            margin-bottom: 1.1rem;
        }
        @keyframes shake {
            0%,100%{transform:translateX(0)} 25%{transform:translateX(-5px)} 75%{transform:translateX(5px)}
        }

        /* Button */
        .btn-save {
            background: var(--orange); color: #fff;
            border: none; border-radius: 12px;
            padding: 0.72rem 1.8rem;
            font-weight: 800; font-size: 0.95rem;
            font-family: 'Baloo 2', cursive; cursor: pointer;
            box-shadow: 0 4px 14px rgba(255,112,67,0.28);
            transition: all 0.2s;
        }
        .btn-save:hover { background: var(--orange-dark); transform: translateY(-2px); }
        .btn-save:active { transform: translateY(0); }

        /* Profile info strip */
        .profile-strip {
            display: flex; align-items: center; gap: 1rem;
            background: #FFF8F0; border: 1.5px solid #F0E6DE;
            border-radius: 14px; padding: 1rem 1.2rem;
            margin-bottom: 1.4rem;
        }
        .profile-avatar {
            width: 52px; height: 52px; border-radius: 50%;
            background: var(--orange); color: #fff;
            font-weight: 800; font-size: 1.4rem;
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0;
        }
        .profile-name { font-size: 1rem; font-weight: 800; color: var(--text-dark); }
        .profile-email { font-size: 0.82rem; color: var(--text-muted); font-weight: 600; }
        .profile-badge {
            margin-left: auto;
            background: #FFF0E8; color: var(--orange);
            border: 1.5px solid #FFCCBC;
            border-radius: 50px; padding: 0.3rem 0.9rem;
            font-size: 0.72rem; font-weight: 800;
            text-transform: uppercase; letter-spacing: 0.5px;
        }

        /* Requirements list */
        .req-list { list-style: none; margin-top: 0.5rem; }
        .req-list li {
            font-size: 0.75rem; font-weight: 600;
            color: var(--brown-light); display: flex;
            align-items: center; gap: 0.4rem; margin-bottom: 0.2rem;
            transition: color 0.2s;
        }
        .req-list li.met { color: #43A047; }
        .req-list li .dot {
            width: 6px; height: 6px; border-radius: 50%;
            background: #E8D8CC; flex-shrink: 0; transition: background 0.2s;
        }
        .req-list li.met .dot { background: #43A047; }
    </style>
</head>
<body>

<?php require_once 'includes/sidebar.php'; ?>

<div class="main-wrap">

    <!-- Topbar -->
    <div class="topbar">
        <div>
            <div class="page-title">⚙️ Settings</div>
            <div class="page-sub">Manage your superadmin account settings.</div>
        </div>
        <div style="font-size:0.82rem;font-weight:700;color:var(--brown);background:#fff;border:1.5px solid #F0E6DE;border-radius:10px;padding:0.45rem 1rem;">
            <i class="bi bi-calendar3 me-1"></i><?= date('F j, Y') ?>
        </div>
    </div>

    <!-- Profile strip -->
    <div class="profile-strip">
        <div class="profile-avatar"><?= strtoupper(substr($user['full_name'], 0, 1)) ?></div>
        <div>
            <div class="profile-name"><?= htmlspecialchars($user['full_name']) ?></div>
            <div class="profile-email"><?= htmlspecialchars($user['email']) ?></div>
        </div>
        <div class="profile-badge">Superadmin</div>
    </div>

    <!-- ── Email Settings ── -->
    <div class="settings-card">
        <div class="card-title"><i class="bi bi-envelope-fill"></i> Email Address</div>
        <div class="card-sub">Update your login email. You'll need your current password to confirm.</div>

        <?php if ($error_email): ?>
        <div class="err-banner"><i class="bi bi-exclamation-circle-fill"></i> <?= htmlspecialchars($error_email) ?></div>
        <?php endif; ?>
        <?php if ($success_email): ?>
        <div class="ok-banner"><i class="bi bi-check-circle-fill"></i> <?= htmlspecialchars($success_email) ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="field">
                <label class="lbl">Current Email</label>
                <input type="email" class="ipt" value="<?= htmlspecialchars($user['email']) ?>" disabled>
            </div>
            <div class="field">
                <label class="lbl" for="new_email">New Email Address</label>
                <input type="email" class="ipt" id="new_email" name="new_email"
                    placeholder="newemail@example.com"
                    value="<?= htmlspecialchars($_POST['new_email'] ?? '') ?>"
                    required autocomplete="email">
            </div>
            <div class="field">
                <label class="lbl" for="confirm_pass_email">Current Password <span style="color:#e53935">*</span></label>
                <div class="ipt-group">
                    <input type="password" class="ipt" id="confirm_pass_email" name="confirm_pass_email"
                        placeholder="Enter your current password to confirm" required>
                    <button type="button" class="ipt-toggle" onclick="togglePw('confirm_pass_email', this)">
                        <i class="bi bi-eye"></i>
                    </button>
                </div>
            </div>
            <button type="submit" name="update_email" class="btn-save">
                <i class="bi bi-envelope-check me-1"></i> Update Email
            </button>
        </form>
    </div>

    <!-- ── Password Settings ── -->
    <div class="settings-card">
        <div class="card-title"><i class="bi bi-shield-lock-fill"></i> Change Password</div>
        <div class="card-sub">Choose a strong password. Must be at least 8 characters.</div>

        <?php if ($error_password): ?>
        <div class="err-banner"><i class="bi bi-exclamation-circle-fill"></i> <?= htmlspecialchars($error_password) ?></div>
        <?php endif; ?>
        <?php if ($success_password): ?>
        <div class="ok-banner"><i class="bi bi-check-circle-fill"></i> <?= htmlspecialchars($success_password) ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="field">
                <label class="lbl" for="current_password">Current Password <span style="color:#e53935">*</span></label>
                <div class="ipt-group">
                    <input type="password" class="ipt" id="current_password" name="current_password"
                        placeholder="Enter your current password" required>
                    <button type="button" class="ipt-toggle" onclick="togglePw('current_password', this)">
                        <i class="bi bi-eye"></i>
                    </button>
                </div>
            </div>
            <div class="field">
                <label class="lbl" for="new_password">New Password <span style="color:#e53935">*</span></label>
                <div class="ipt-group">
                    <input type="password" class="ipt" id="new_password" name="new_password"
                        placeholder="Min. 8 characters" required
                        oninput="checkStrength(this.value); checkMatch();">
                    <button type="button" class="ipt-toggle" onclick="togglePw('new_password', this)">
                        <i class="bi bi-eye"></i>
                    </button>
                </div>
                <div class="strength-track"><div class="strength-fill" id="sFill"></div></div>
                <div class="strength-lbl" id="sLbl"></div>
                <ul class="req-list" style="margin-top:0.6rem;">
                    <li id="req-len"><span class="dot"></span>At least 8 characters</li>
                    <li id="req-upper"><span class="dot"></span>One uppercase letter</li>
                    <li id="req-num"><span class="dot"></span>One number</li>
                    <li id="req-special"><span class="dot"></span>One special character</li>
                </ul>
            </div>
            <div class="field">
                <label class="lbl" for="confirm_new_password">Confirm New Password <span style="color:#e53935">*</span></label>
                <div class="ipt-group">
                    <input type="password" class="ipt" id="confirm_new_password" name="confirm_new_password"
                        placeholder="Re-enter new password" required
                        oninput="checkMatch()">
                    <button type="button" class="ipt-toggle" onclick="togglePw('confirm_new_password', this)">
                        <i class="bi bi-eye"></i>
                    </button>
                </div>
                <div class="match-msg" id="matchMsg"></div>
            </div>
            <button type="submit" name="update_password" class="btn-save">
                <i class="bi bi-shield-check me-1"></i> Update Password
            </button>
        </form>
    </div>

</div>

<script>
function togglePw(id, btn) {
    const inp  = document.getElementById(id);
    const icon = btn.querySelector('i');
    inp.type = (inp.type === 'password') ? 'text' : 'password';
    icon.className = (inp.type === 'text') ? 'bi bi-eye-slash' : 'bi bi-eye';
}

function checkStrength(v) {
    const fill = document.getElementById('sFill');
    const lbl  = document.getElementById('sLbl');
    let s = 0;
    const hasLen     = v.length >= 8;
    const hasUpper   = /[A-Z]/.test(v);
    const hasNum     = /[0-9]/.test(v);
    const hasSpecial = /[^A-Za-z0-9]/.test(v);
    if (hasLen)     s++;
    if (hasUpper)   s++;
    if (hasNum)     s++;
    if (hasSpecial) s++;
    toggle('req-len',     hasLen);
    toggle('req-upper',   hasUpper);
    toggle('req-num',     hasNum);
    toggle('req-special', hasSpecial);
    const levels = [
        { w:'0%',   c:'#E8D8CC', t:'' },
        { w:'25%',  c:'#e53935', t:'Weak' },
        { w:'50%',  c:'#FF7043', t:'Fair' },
        { w:'75%',  c:'#FFB347', t:'Good' },
        { w:'100%', c:'#66BB6A', t:'Strong 💪' },
    ];
    fill.style.width      = levels[s].w;
    fill.style.background = levels[s].c;
    lbl.textContent       = levels[s].t;
    lbl.style.color       = levels[s].c;
}
function toggle(id, met) {
    document.getElementById(id)?.classList.toggle('met', met);
}
function checkMatch() {
    const pw  = document.getElementById('new_password').value;
    const cp  = document.getElementById('confirm_new_password').value;
    const msg = document.getElementById('matchMsg');
    if (!cp) { msg.textContent = ''; return; }
    if (cp === pw) {
        msg.textContent = '✓ Passwords match';
        msg.style.color = '#66BB6A';
    } else {
        msg.textContent = '✗ Passwords do not match';
        msg.style.color = '#e53935';
    }
}
</script>
</body>
</html>
