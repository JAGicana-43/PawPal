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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');

    if (empty($email)) {
        $error = 'Please enter your email address.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        // Check if email exists
        $stmt = mysqli_prepare($conn, "SELECT user_id, full_name FROM users WHERE email = ?");
        mysqli_stmt_bind_param($stmt, 's', $email);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $user   = mysqli_fetch_assoc($result);

        // Always show a vague success message (prevents email enumeration)
        $success = 'If that email is registered, you\'ll receive a reset link shortly. Check your inbox.';

        if ($user) {
            // Delete any existing unused tokens for this email
            $del = mysqli_prepare($conn, "DELETE FROM password_resets WHERE email = ?");
            mysqli_stmt_bind_param($del, 's', $email);
            mysqli_stmt_execute($del);

            // Generate secure token
            $token      = bin2hex(random_bytes(32));
            $expires_at = gmdate('Y-m-d H:i:s', strtotime('+1 hour'));

            // Save token to DB
            $ins = mysqli_prepare($conn,
                "INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)"
            );
            mysqli_stmt_bind_param($ins, 'sss', $email, $token, $expires_at);
            mysqli_stmt_execute($ins);

            // Send reset email via PHPMailer
            // Uncomment and configure once PHPMailer is installed via Composer:
            //
            // use PHPMailer\PHPMailer\PHPMailer;
            // require '../vendor/autoload.php';
            //
            // $mail = new PHPMailer(true);
            // try {
            //     $mail->isSMTP();
            //     $mail->Host       = 'smtp.gmail.com';          // your SMTP host
            //     $mail->SMTPAuth   = true;
            //     $mail->Username   = 'your@email.com';          // your email
            //     $mail->Password   = 'your_app_password';       // your app password
            //     $mail->SMTPSecure = 'tls';
            //     $mail->Port       = 587;
            //
            //     $mail->setFrom('noreply@pawpal.com', 'PawPal');
            //     $mail->addAddress($email, $user['full_name']);
            //     $mail->isHTML(true);
            //     $mail->Subject = 'Reset your PawPal password';
            //
            //     $reset_link = 'https://yourdomain.com/auth/reset_password.php?token=' . $token;
            //
            //     $mail->Body = "
            //         <p>Hi {$user['full_name']},</p>
            //         <p>Click the button below to reset your password. This link expires in 1 hour.</p>
            //         <p><a href='{$reset_link}' style='background:#FF7043;color:#fff;padding:12px 24px;border-radius:8px;text-decoration:none;font-weight:bold;'>Reset Password</a></p>
            //         <p>If you didn't request this, you can safely ignore this email.</p>
            //         <p>— The PawPal Team 🐾</p>
            //     ";
            //     $mail->AltBody = "Reset your password: {$reset_link}";
            //     $mail->send();
            // } catch (Exception $e) {
            //     // Log silently — don't expose errors to user
            //     error_log('Mailer error: ' . $mail->ErrorInfo);
            // }

            // ── TEMP: For development/testing, show the link directly ──
            // REMOVE THIS IN PRODUCTION
            $reset_link = 'http://localhost/PawPal/auth/reset_password.php?token=' . $token;
            $success .= '<br><small style="opacity:0.7;">[Dev mode] <a href="' . $reset_link . '" style="color:#2E7D32;">Click here to reset</a></small>';
        }
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
    <title>Forgot Password — PawPal</title>
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
            width: 50%;
            flex-shrink: 0;
            position: sticky;
            top: 0;
            height: 100vh;
            align-self: flex-start;
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
            flex: 1;
            display: flex; flex-direction: column;
            justify-content: center; align-items: center;
            padding: 2.5rem 2rem;
            min-height: 100vh;
            overflow-y: auto;
            background: var(--cream);
        }
        .form-shell {
            width: 100%; max-width: 390px;
            padding: 1.5rem 0;
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

        /* Icon circle */
        .icon-circle {
            width: 64px; height: 64px; border-radius: 50%;
            background: rgba(255,112,67,0.10);
            border: 2px solid rgba(255,112,67,0.20);
            display: flex; align-items: center; justify-content: center;
            font-size: 1.7rem; margin-bottom: 1.2rem;
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

        .err-banner {
            background: #FFF0ED; border: 1.5px solid #FFCCBC;
            border-radius: 10px; color: #BF360C;
            font-size: 0.86rem; font-weight: 600;
            padding: 0.7rem 1rem; display: flex; align-items: center; gap: 0.5rem;
            margin-bottom: 1.2rem; animation: shake 0.38s ease;
        }
        .ok-banner {
            background: #F1F8E9; border: 1.5px solid #C5E1A5;
            border-radius: 10px; color: #2E7D32;
            font-size: 0.86rem; font-weight: 600;
            padding: 0.9rem 1rem; margin-bottom: 1.2rem;
            line-height: 1.55;
        }
        .ok-banner .ok-icon { font-size: 1.3rem; margin-bottom: 0.3rem; display: block; }
        @keyframes shake {
            0%,100%{transform:translateX(0)} 25%{transform:translateX(-5px)} 75%{transform:translateX(5px)}
        }

        .btn-go {
            width: 100%; background: var(--orange); color: #fff;
            border: none; border-radius: 12px; padding: 0.82rem;
            font-weight: 800; font-size: 1rem;
            font-family: 'Baloo 2', cursive; cursor: pointer;
            box-shadow: 0 5px 18px rgba(255,112,67,0.32);
            transition: all 0.22s; margin-top: 1rem;
        }
        .btn-go:hover { background: var(--orange-dark); transform: translateY(-2px); box-shadow: 0 9px 24px rgba(255,112,67,0.38); }
        .btn-go:active { transform: translateY(0); }
        .btn-go:disabled { opacity: 0.6; cursor: not-allowed; transform: none; }

        .divider {
            display: flex; align-items: center; gap: 0.75rem;
            color: var(--brown-light); font-size: 0.78rem; font-weight: 700;
            margin: 1.4rem 0;
        }
        .divider::before, .divider::after { content: ''; flex: 1; height: 1.5px; background: #E8D8CC; }

        .login-nudge { text-align: center; font-size: 0.88rem; color: var(--text-muted); }
        .login-nudge a { color: var(--orange); font-weight: 700; text-decoration: none; }
        .login-nudge a:hover { text-decoration: underline; }

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

        <div class="icon-circle">🔑</div>
        <div class="form-heading">Forgot password?</div>
        <p class="form-sub">No worries — enter your email and we'll send you a reset link. It expires in 1 hour.</p>

        <?php if ($error): ?>
        <div class="err-banner">
            <i class="bi bi-exclamation-circle-fill"></i>
            <?= htmlspecialchars($error) ?>
        </div>
        <?php endif; ?>

        <?php if ($success): ?>
        <div class="ok-banner">
            <span class="ok-icon">📬</span>
            <?= $success /* already sanitised above */ ?>
        </div>
        <?php endif; ?>

        <?php if (!$success): ?>
        <form method="POST" action="" id="forgotForm">
            <div style="margin-bottom: 1rem;">
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

            <button type="submit" class="btn-go" id="submitBtn">
                <i class="bi bi-send-fill me-2"></i>Send Reset Link
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
// Prevent double-submit
document.getElementById('forgotForm')?.addEventListener('submit', function() {
    const btn = document.getElementById('submitBtn');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Sending…';
});
</script>
</body>
</html>
