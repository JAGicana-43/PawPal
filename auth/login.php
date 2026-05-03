<?php
session_start();
require_once '../config/database.php';

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = mysqli_prepare($conn, "SELECT * FROM users WHERE email = ?");
    mysqli_stmt_bind_param($stmt, 's', $email);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $user   = mysqli_fetch_assoc($result);

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id']   = $user['user_id'];
        $_SESSION['role']      = $user['role'];
        $_SESSION['full_name'] = $user['full_name'];

        if ($user['role'] === 'adopter') {
            header('Location: ../dashboard.php');
        } else {
            header('Location: ../superadmin/super_ad_dash.php');
        }
        exit;
    } else {
        $error = 'Invalid email or password.';
    }
}

// ── Activity logger ──────────────────────────────────────────
function log_activity($conn, $user_id, $full_name, $role, $action, $description = '') {
    $ip   = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $stmt = mysqli_prepare($conn,
        "INSERT INTO activity_logs (user_id, full_name, role, action, description, ip_address)
         VALUES (?, ?, ?, ?, ?, ?)"
    );
    mysqli_stmt_bind_param($stmt, 'isssss', $user_id, $full_name, $role, $action, $description, $ip);
    mysqli_stmt_execute($stmt);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = 'Please fill in all fields.';
    } else {
        $stmt = mysqli_prepare($conn, "SELECT user_id, full_name, password, role FROM users WHERE email = ?");
        mysqli_stmt_bind_param($stmt, 's', $email);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $user   = mysqli_fetch_assoc($result);

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id']   = $user['user_id'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['role']      = $user['role'];

            log_activity($conn, $user['user_id'], $user['full_name'], $user['role'], 'login');

            if ($user['role'] === 'superadmin') {
                 header('Location: ../superadmin/super_ad_dash.php');
            } elseif ($user['role'] === 'admin') {
                header('Location: ../admin_dash.php');
            } else {
                header('Location: ../dashboard.php');
            }
            exit;
        } else {
            $error = 'Invalid email or password. Please try again.';
        }
    }
}

// Fetch live stats for the left panel
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
    <title>Log In — PawPal</title>
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

        /* FIX 1: html/body must not clip vertical overflow */
        html { height: 100%; }

        body {
            font-family: 'Nunito', sans-serif;
            min-height: 100vh;
            display: flex;
            overflow-x: hidden; /* was: overflow: hidden — this was blocking vertical scroll */
            background: var(--cream);
        }

        h1,h2,h3,h4 { font-family: 'Baloo 2', cursive; }

        /* FIX 2: left panel sticks to viewport so only the right panel scrolls */
        .panel-left {
            width: 50%;
            flex-shrink: 0;
            position: sticky;
            top: 0;
            height: 100vh;
            align-self: flex-start;
            overflow: hidden;
        }

        .photo-grid {
            position: absolute;
            inset: 0;
            display: grid;
            grid-template-columns: 58% 42%;
            grid-template-rows: 40% 30% 30%;
            gap: 3px;
        }
        .photo-grid .cell {
            background-size: cover;
            background-position: center;
        }
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
            position: absolute;
            inset: 0;
            background: linear-gradient(
                160deg,
                rgba(30,15,10,0.78) 0%,
                rgba(80,45,35,0.60) 45%,
                rgba(255,112,67,0.28) 100%
            );
        }

        .panel-content {
            position: absolute;
            inset: 0;
            z-index: 2;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            padding: 2.5rem 2.8rem;
        }

        .brand-link {
            font-family: 'Baloo 2', cursive;
            font-size: 1.9rem;
            font-weight: 800;
            color: #fff;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            width: fit-content;
        }
        .brand-link img {
            height: 100px;
            width: auto;
        
        }

        .panel-quote { color: #fff; }
        .panel-quote h2 {
            font-size: clamp(1.6rem, 2.4vw, 2.4rem);
            font-weight: 800;
            line-height: 1.25;
            margin-bottom: 0.9rem;
        }
        .panel-quote h2 em { font-style: normal; color: var(--soft-orange); }
        .panel-quote p { font-size: 0.9rem; opacity: 0.75; font-weight: 600; letter-spacing: 0.3px; }

        .panel-pills { display: flex; gap: 0.8rem; flex-wrap: wrap; }
        .pill {
            display: inline-flex; align-items: center; gap: 0.45rem;
            background: rgba(255,255,255,0.13);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.22);
            border-radius: 50px;
            padding: 0.45rem 1.1rem;
            color: #fff; font-size: 0.8rem; font-weight: 700;
        }
        .pill strong { color: var(--soft-orange); font-size: 1rem; }

        /* FIX 3: right panel scrolls on its own, min-height ensures it fills viewport */
        .panel-right {
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding: 2.5rem 2rem;
            min-height: 100vh;
            overflow-y: auto;
            background: var(--cream);
            position: relative;
        }
        .panel-right::after {
            content: '';
            position: fixed;
            bottom: -160px; right: -160px;
            width: 420px; height: 420px;
            background: radial-gradient(circle, rgba(255,179,71,0.13) 0%, transparent 70%);
            border-radius: 50%;
            pointer-events: none;
        }

        /* FIX 4: form-shell gets vertical padding so content isn't clipped when scrolling */
        .form-shell {
            width: 100%;
            max-width: 390px;
            padding: 1.5rem 0;
            animation: riseIn 0.5s cubic-bezier(.22,.68,0,1.2) both;
        }
        @keyframes riseIn {
            from { opacity: 0; transform: translateY(20px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .form-heading { font-size: 2rem; font-weight: 800; color: var(--text-dark); margin-bottom: 0.3rem; }
        .form-sub { color: var(--text-muted); font-size: 0.9rem; margin-bottom: 2rem; }

        .lbl {
            display: block;
            font-size: 0.78rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            color: var(--brown);
            margin-bottom: 0.35rem;
        }
        .ipt {
            width: 100%;
            border: 2px solid #E8D8CC;
            border-radius: 12px;
            padding: 0.72rem 1rem;
            font-family: 'Nunito', sans-serif;
            font-size: 0.95rem;
            color: var(--text-dark);
            background: #fff;
            transition: border-color 0.2s, box-shadow 0.2s;
            outline: none;
        }
        .ipt:focus {
            border-color: var(--orange);
            box-shadow: 0 0 0 3.5px rgba(255,112,67,0.13);
        }
        .ipt-group { display: flex; }
        .ipt-group .ipt {
            border-right: none;
            border-radius: 12px 0 0 12px;
            flex: 1;
        }
        .ipt-toggle {
            background: #fff;
            border: 2px solid #E8D8CC;
            border-left: none;
            border-radius: 0 12px 12px 0;
            padding: 0 0.9rem;
            cursor: pointer;
            color: var(--brown-light);
            font-size: 1rem;
            transition: color 0.2s, border-color 0.2s;
            display: flex; align-items: center;
        }
        .ipt-group:focus-within .ipt,
        .ipt-group:focus-within .ipt-toggle { border-color: var(--orange); }
        .ipt-group:focus-within .ipt-toggle { box-shadow: 0 0 0 3.5px rgba(255,112,67,0.13); }
        .ipt-toggle:hover { color: var(--orange); }

        .err-banner {
            background: #FFF0ED;
            border: 1.5px solid #FFCCBC;
            border-radius: 10px;
            color: #BF360C;
            font-size: 0.86rem;
            font-weight: 600;
            padding: 0.7rem 1rem;
            display: flex; align-items: center; gap: 0.5rem;
            margin-bottom: 1.2rem;
            animation: shake 0.38s ease;
        }
        @keyframes shake {
            0%,100%{transform:translateX(0)}
            25%{transform:translateX(-5px)}
            75%{transform:translateX(5px)}
        }

        .remember-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.4rem;
        }
        .check-label {
            display: flex; align-items: center; gap: 0.45rem;
            font-size: 0.86rem; color: var(--text-muted); cursor: pointer;
        }
        input[type="checkbox"] { accent-color: var(--orange); width: 15px; height: 15px; }

        .btn-go {
            width: 100%;
            background: var(--orange);
            color: #fff;
            border: none;
            border-radius: 12px;
            padding: 0.82rem;
            font-weight: 800;
            font-size: 1rem;
            font-family: 'Baloo 2', cursive;
            cursor: pointer;
            box-shadow: 0 5px 18px rgba(255,112,67,0.32);
            transition: all 0.22s;
        }
        .btn-go:hover {
            background: var(--orange-dark);
            transform: translateY(-2px);
            box-shadow: 0 9px 24px rgba(255,112,67,0.38);
        }
        .btn-go:active { transform: translateY(0); }

        .divider {
            display: flex; align-items: center; gap: 0.75rem;
            color: var(--brown-light); font-size: 0.78rem; font-weight: 700;
            margin: 1.3rem 0;
        }
        .divider::before, .divider::after { content: ''; flex: 1; height: 1.5px; background: #E8D8CC; }

        .signup-nudge { text-align: center; font-size: 0.88rem; color: var(--text-muted); }
        .signup-nudge a { color: var(--orange); font-weight: 700; text-decoration: none; }
        .signup-nudge a:hover { text-decoration: underline; }

        .back-link {
            display: inline-flex; align-items: center; gap: 0.4rem;
            font-size: 0.8rem; font-weight: 700;
            color: var(--text-muted); text-decoration: none;
            margin-bottom: 2rem; transition: color 0.2s;
        }
        .back-link:hover { color: var(--orange); }

        /* FIX 5: mobile — allow scroll, start content from top with padding */
        @media (max-width: 800px) {
            .panel-left { display: none; }
            body { overflow-x: hidden; }
            .panel-right {
                padding: 3rem 1.5rem 2rem;
                justify-content: flex-start;
                min-height: 100vh;
            }
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

        <a href="../landingpage.php" class="back-link">
            <i class="bi bi-arrow-left"></i> Back to Home
        </a>

        <div class="form-heading">Welcome back! 👋</div>
        <p class="form-sub">Log in to continue your adoption journey.</p>

        <?php if ($error): ?>
        <div class="err-banner">
            <i class="bi bi-exclamation-circle-fill"></i>
            <?= htmlspecialchars($error) ?>
        </div>
        <?php endif; ?>

        <form method="POST" action="">

            <!-- Email -->
            <div style="margin-bottom:1.1rem;">
                <label class="lbl" for="email">Email Address</label>
                <input
                    type="email"
                    class="ipt"
                    id="email"
                    name="email"
                    placeholder="you@email.com"
                    value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                    required
                    autocomplete="email"
                >
            </div>

            <!-- Password -->
            <div style="margin-bottom:1rem;">
                <label class="lbl" for="password">Password</label>
                <div class="ipt-group">
                    <input
                        type="password"
                        class="ipt"
                        id="password"
                        name="password"
                        placeholder="Enter your password"
                        required
                        autocomplete="current-password"
                    >
                    <button type="button" class="ipt-toggle" onclick="togglePw('password',this)" aria-label="Toggle password">
                        <i class="bi bi-eye"></i>
                    </button>
                </div>
            </div>

            <!-- Remember -->
        <div class="remember-row">
    <label class="check-label">
        <input type="checkbox" name="remember"> Remember me
    </label>
    <a href="forgot_password.php" style="font-size:0.84rem;font-weight:700;color:var(--orange);text-decoration:none;">
        Forgot password?
    </a>
</div>

            <button type="submit" class="btn-go">
                <i class="bi bi-box-arrow-in-right me-2"></i>Log In
            </button>
        </form>

        <div class="divider">or</div>

        <div class="signup-nudge">
            Don't have an account? <a href="register.php">Sign up — it's free!</a>
        </div>

    </div>
</div>

<script>
function togglePw(id, btn) {
    const inp  = document.getElementById(id);
    const icon = btn.querySelector('i');
    if (inp.type === 'password') {
        inp.type = 'text';
        icon.className = 'bi bi-eye-slash';
    } else {
        inp.type = 'password';
        icon.className = 'bi bi-eye';
    }
}
</script>
</body>
</html>