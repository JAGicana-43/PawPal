<?php
session_start();
require_once '../config/database.php';

// If already logged in, redirect
if (isset($_SESSION['user_id'])) {
    header('Location: ../dashboard.php');
    exit;
}

$error   = '';
$success = '';
$token   = trim($_GET['token'] ?? '');
$valid   = false;
$email   = '';

// ── Validate the token on page load ──────────────────────────
if (empty($token)) {
    $error = 'No reset token provided. Please request a new reset link.';
} else {
    $stmt = mysqli_prepare($conn,
        "SELECT email FROM password_resets
 WHERE token = ? AND expires_at > UTC_TIMESTAMP() AND used = 0
 LIMIT 1"
    );
    mysqli_stmt_bind_param($stmt, 's', $token);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row    = mysqli_fetch_assoc($result);

    if ($row) {
        $valid = true;
        $email = $row['email'];
    } else {
        $error = 'This reset link is invalid or has expired. Please request a new one.';
    }
}

// ── Handle new password submission ───────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $valid) {
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';

    if (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        $hashed = password_hash($password, PASSWORD_DEFAULT);

        // Update the user's password
        $upd = mysqli_prepare($conn, "UPDATE users SET password = ? WHERE email = ?");
        mysqli_stmt_bind_param($upd, 'ss', $hashed, $email);
        mysqli_stmt_execute($upd);

        // Mark token as used
        $mark = mysqli_prepare($conn, "UPDATE password_resets SET used = 1 WHERE token = ?");
        mysqli_stmt_bind_param($mark, 's', $token);
        mysqli_stmt_execute($mark);

        $success = 'Your password has been reset successfully! Redirecting to login…';
        header('Refresh: 3; URL=login.php');
    }
}

// Live stats for left panel
$stat_available = 0;
$stat_adopted   = 0;
if (isset($conn)) {
    $r = mysqli_query($conn, "SELECT COUNT(*) FROM pets WHERE status='available'");
    if ($r) $stat_available = mysqli_fetch_row($r)[0];
    $r = mysqli_query($conn, "SELECT COUNT(*) FROM pets WHERE status='adopted'");
    if ($r) $stat_adopted = mysqli_fetch_row($r)[0];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password — PawPal</title>
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
        }
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        html { height: 100%; }
        body {
            font-family: 'Nunito', sans-serif;
            min-height: 100vh;
            display: flex;
            overflow-x: hidden;
            background: var(--cream);
        }
        h1,h2,h3,h4 { font-family: 'Baloo 2', cursive; }

        /* ── Left panel ── */
        .panel-left {
            width: 50%; flex-shrink: 0;
            position: sticky; top: 0;
            height: 100vh; align-self: flex-start;
            overflow: hidden;
        }
        .photo-grid {
            position: absolute; inset: 0;
            display: grid;
            grid-template-columns: 58% 42%;
            grid-template-rows: 40% 30% 30%;
            gap: 3px;
        }
        .photo-grid .cell { background-size: cover; background-position: center; }
        .cell-1 {
            background-image: url('https://images.unsplash.com/photo-1587300003388-59208cc962cb?w=800&q=80');
            grid-row: 1 / 3;
        }
        .cell-2 { background-image: url('https://images.unsplash.com/photo-1514888286974-6c03e2ca1dba?w=600&q=80'); }
        .cell-3 { background-image: url('https://images.unsplash.com/photo-1552053831-71594a27632d?w=600&q=80'); }
        .cell-4 { background-image: url('https://images.unsplash.com/photo-1444212477490-ca407925329e?w=600&q=80'); }
        .cell-5 { background-image: url('https://images.unsplash.com/photo-1425082661705-1834bfd09dca?w=600&q=80'); }
        .cell-6 { background-image: url('https://images.unsplash.com/photo-1548767797-d8c844163c4a?w=600&q=80'); }
        .panel-overlay {
            position: absolute; inset: 0;
            background: linear-gradient(160deg, rgba(30,15,10,0.78) 0%, rgba(80,45,35,0.60) 45%, rgba(255,112,67,0.28) 100%);
        }
        .panel-content {
            position: absolute; inset: 0; z-index: 2;
            display: flex; flex-direction: column;
            justify-content: space-between;
            padding: 2.5rem 2.8rem;
        }
        .brand-link {
            font-family: 'Baloo 2', cursive;
            font-size: 1.9rem; font-weight: 800;
            color: #fff; text-decoration: none;
            display: flex; align-items: center; gap: 0.5rem;
        }
        .brand-link img { height: 44px; filter: brightness(0) invert(1); }
        .panel-quote { color: #fff; }
        .panel-quote h2 {
            font-size: clamp(1.6rem, 2.4vw, 2.4rem);
            font-weight: 800; line-height: 1.25; margin-bottom: 0.9rem;
        }
        .panel-quote h2 em { font-style: normal; color: var(--soft-orange); }
        .panel-quote p { font-size: 0.9rem; opacity: 0.75; font-weight: 600; }
        .panel-pills { display: flex; gap: 0.8rem; flex-wrap: wrap; }
        .pill {
            display: inline-flex; align-items: center; gap: 0.45rem;
            background: rgba(255,255,255,0.13);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.22);
            border-radius: 50px; padding: 0.45rem 1.1rem;
            color: #fff; font-size: 0.8rem; font-weight: 700;
        }
        .pill strong { color: var(--soft-orange); font-size: 1rem; }

        /* ── Right panel ── */
        .panel-right {
            flex: 1; display: flex; flex-direction: column;
            justify-content: center; align-items: center;
            padding: 2.5rem 2rem; min-height: 100vh;
            overflow-y: auto; background: var(--cream);
        }
        .form-shell {
            width: 100%; max-width: 390px; padding: 1.5rem 0;
            animation: riseIn 0.5s cubic-bezier(.22,.68,0,1.2) both;
        }
        @keyframes riseIn {
            from { opacity: 0; transform: translateY(20px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .back-link {
            display: inline-flex; align-items: center; gap: 0.4rem;
            font-size: 0.8rem; font-weight: 700;
            color: var(--text-muted); text-decoration: none;
            margin-bottom: 2rem; transition: color 0.2s;
        }
        .back-link:hover { color: var(--orange); }

        .icon-circle {
            width: 64px; height: 64px; border-radius: 50%;
            background: rgba(255,112,67,0.10);
            border: 2px solid rgba(255,112,67,0.20);
            display: flex; align-items: center; justify-content: center;
            font-size: 1.7rem; margin-bottom: 1.2rem;
        }
        .icon-circle.error-icon {
            background: rgba(229,57,53,0.08);
            border-color: rgba(229,57,53,0.20);
        }

        .form-heading { font-size: 1.9rem; font-weight: 800; color: var(--text-dark); margin-bottom: 0.3rem; }
        .form-sub { color: var(--text-muted); font-size: 0.9rem; margin-bottom: 1.8rem; line-height: 1.5; }

        .lbl {
            display: block; font-size: 0.78rem; font-weight: 700;
            text-transform: uppercase; letter-spacing: 0.6px;
            color: var(--brown); margin-bottom: 0.35rem;
        }
        .ipt {
            width: 100%; border: 2px solid #E8D8CC; border-radius: 12px;
            padding: 0.72rem 1rem; font-family: 'Nunito', sans-serif;
            font-size: 0.95rem; color: var(--text-dark);
            background: #fff; transition: border-color 0.2s, box-shadow 0.2s; outline: none;
        }
        .ipt:focus { border-color: var(--orange); box-shadow: 0 0 0 3.5px rgba(255,112,67,0.13); }
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
        .ipt-group:focus-within .ipt-toggle { box-shadow: 0 0 0 3.5px rgba(255,112,67,0.13); }
        .ipt-toggle:hover { color: var(--orange); }

        /* Strength bar */
        .strength-track { height: 4px; border-radius: 4px; background: #E8D8CC; margin-top: 6px; overflow: hidden; }
        .strength-fill { height: 100%; border-radius: 4px; width: 0; transition: width 0.3s, background 0.3s; }
        .strength-lbl { font-size: 0.72rem; font-weight: 700; margin-top: 3px; }
        .match-msg { font-size: 0.72rem; font-weight: 700; margin-top: 4px; }

        .err-banner {
            background: #FFF0ED; border: 1.5px solid #FFCCBC;
            border-radius: 10px; color: #BF360C;
            font-size: 0.86rem; font-weight: 600;
            padding: 0.7rem 1rem; display: flex; align-items: flex-start; gap: 0.5rem;
            margin-bottom: 1.2rem; animation: shake 0.38s ease;
        }
        .ok-banner {
            background: #F1F8E9; border: 1.5px solid #C5E1A5;
            border-radius: 10px; color: #2E7D32;
            font-size: 0.86rem; font-weight: 600;
            padding: 0.9rem 1rem; margin-bottom: 1.2rem; line-height: 1.55;
        }
        .ok-banner .ok-icon { font-size: 1.3rem; margin-bottom: 0.3rem; display: block; }
        @keyframes shake {
            0%,100%{transform:translateX(0)} 25%{transform:translateX(-5px)} 75%{transform:translateX(5px)}
        }

        .field { margin-bottom: 1.1rem; }

        .btn-go {
            width: 100%; background: var(--orange); color: #fff;
            border: none; border-radius: 12px; padding: 0.82rem;
            font-weight: 800; font-size: 1rem;
            font-family: 'Baloo 2', cursive; cursor: pointer;
            box-shadow: 0 5px 18px rgba(255,112,67,0.32);
            transition: all 0.22s; margin-top: 0.6rem;
        }
        .btn-go:hover { background: var(--orange-dark); transform: translateY(-2px); box-shadow: 0 9px 24px rgba(255,112,67,0.38); }
        .btn-go:active { transform: translateY(0); }
        .btn-go:disabled { opacity: 0.6; cursor: not-allowed; transform: none; }

        .btn-outline {
            width: 100%; background: transparent; color: var(--orange);
            border: 2px solid var(--orange); border-radius: 12px; padding: 0.75rem;
            font-weight: 800; font-size: 1rem;
            font-family: 'Baloo 2', cursive; cursor: pointer;
            transition: all 0.22s; margin-top: 0.8rem;
            text-decoration: none; display: block; text-align: center;
        }
        .btn-outline:hover { background: var(--orange); color: #fff; }

        .divider {
            display: flex; align-items: center; gap: 0.75rem;
            color: var(--brown-light); font-size: 0.78rem; font-weight: 700;
            margin: 1.4rem 0;
        }
        .divider::before, .divider::after { content: ''; flex: 1; height: 1.5px; background: #E8D8CC; }

        .login-nudge { text-align: center; font-size: 0.88rem; color: var(--text-muted); }
        .login-nudge a { color: var(--orange); font-weight: 700; text-decoration: none; }
        .login-nudge a:hover { text-decoration: underline; }

        /* Requirements checklist */
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

        @media (max-width: 800px) {
            .panel-left { display: none; }
            .panel-right { padding: 3rem 1.5rem 2rem; justify-content: flex-start; }
        }
    </style>
</head>
<body>

<!-- ══ LEFT PANEL ══ -->
<div class="panel-left">
    <div class="photo-grid">
        <div class="cell cell-1"></div>
        <div class="cell cell-2"></div>
        <div class="cell cell-3"></div>
        <div class="cell cell-4"></div>
        <div class="cell cell-5"></div>
        <div class="cell cell-6"></div>
    </div>
    <div class="panel-overlay"></div>
    <div class="panel-content">
        <a href="../landingpage.php" class="brand-link">
            <?php if (file_exists('../assets/images/logo.png')): ?>
                <img src="../assets/images/logo.png" alt="PawPal">
            <?php else: ?>
                🐾 PawPal
            <?php endif; ?>
        </a>
        <div class="panel-quote">
            <h2>Every pet deserves<br>a <em>forever home</em>.<br>Every home deserves<br>a <em>forever pet</em>.</h2>
            <p>🐾 &nbsp;Connecting loving families with animals in need since 2019.</p>
        </div>
        <div class="panel-pills">
            <div class="pill"><strong><?= $stat_available ?>+</strong> Pets Available</div>
            <div class="pill"><strong><?= $stat_adopted ?>+</strong> Happy Adoptions</div>
        </div>
    </div>
</div>

<!-- ══ RIGHT PANEL ══ -->
<div class="panel-right">
    <div class="form-shell">

        <a href="login.php" class="back-link">
            <i class="bi bi-arrow-left"></i> Back to Login
        </a>

        <?php if (!$valid && !$success): ?>
            <!-- ── Invalid / expired token state ── -->
            <div class="icon-circle error-icon">⚠️</div>
            <div class="form-heading">Link expired</div>
            <p class="form-sub"><?= htmlspecialchars($error) ?></p>
            <a href="forgot_password.php" class="btn-go" style="display:block; text-align:center; text-decoration:none; padding:0.82rem;">
                <i class="bi bi-arrow-clockwise me-2"></i>Request a new link
            </a>

        <?php elseif ($success): ?>
            <!-- ── Success state ── -->
            <div class="icon-circle">✅</div>
            <div class="form-heading">Password reset!</div>
            <div class="ok-banner">
                <span class="ok-icon">🎉</span>
                <?= htmlspecialchars($success) ?>
            </div>
            <a href="login.php" class="btn-go" style="display:block; text-align:center; text-decoration:none; padding:0.82rem;">
                <i class="bi bi-box-arrow-in-right me-2"></i>Go to Login
            </a>

        <?php else: ?>
            <!-- ── Reset form ── -->
            <div class="icon-circle">🔒</div>
            <div class="form-heading">Set new password</div>
            <p class="form-sub">Choose a strong password for <strong><?= htmlspecialchars($email) ?></strong>.</p>

            <?php if ($error): ?>
            <div class="err-banner">
                <i class="bi bi-exclamation-circle-fill" style="flex-shrink:0;margin-top:2px;"></i>
                <?= htmlspecialchars($error) ?>
            </div>
            <?php endif; ?>

            <form method="POST" action="reset_password.php?token=<?= urlencode($token) ?>" id="resetForm">

                <!-- New password -->
                <div class="field">
                    <label class="lbl" for="password">New Password</label>
                    <div class="ipt-group">
                        <input type="password" class="ipt" id="password" name="password"
                            placeholder="Min. 8 characters" required autocomplete="new-password"
                            oninput="checkStrength(this.value); checkMatch();">
                        <button type="button" class="ipt-toggle" onclick="togglePw('password',this)" aria-label="Toggle">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                    <div class="strength-track"><div class="strength-fill" id="sFill"></div></div>
                    <div class="strength-lbl" id="sLbl"></div>
                    <!-- Live requirements -->
                    <ul class="req-list" id="reqList" style="margin-top:0.6rem;">
                        <li id="req-len"><span class="dot"></span>At least 8 characters</li>
                        <li id="req-upper"><span class="dot"></span>One uppercase letter</li>
                        <li id="req-num"><span class="dot"></span>One number</li>
                        <li id="req-special"><span class="dot"></span>One special character</li>
                    </ul>
                </div>

                <!-- Confirm password -->
                <div class="field">
                    <label class="lbl" for="confirm_password">Confirm Password</label>
                    <div class="ipt-group">
                        <input type="password" class="ipt" id="confirm_password" name="confirm_password"
                            placeholder="Re-enter your new password" required autocomplete="new-password"
                            oninput="checkMatch()">
                        <button type="button" class="ipt-toggle" onclick="togglePw('confirm_password',this)" aria-label="Toggle">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                    <div class="match-msg" id="matchMsg"></div>
                </div>

                <button type="submit" class="btn-go" id="submitBtn">
                    <i class="bi bi-shield-check me-2"></i>Reset Password
                </button>
            </form>
        <?php endif; ?>

        <div class="divider">or</div>
        <div class="login-nudge">
            Remembered it? <a href="login.php">Back to login</a>
        </div>

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

    // Update requirement dots
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
    const pw  = document.getElementById('password').value;
    const cp  = document.getElementById('confirm_password').value;
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

// Prevent double-submit
document.getElementById('resetForm')?.addEventListener('submit', function() {
    const btn = document.getElementById('submitBtn');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Resetting…';
});
</script>
</body>
</html>
