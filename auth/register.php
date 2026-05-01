<?php
session_start();
require_once '../config/database.php';

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name     = trim($_POST['first_name'] ?? '');
    $middle_name    = trim($_POST['middle_name'] ?? '');
    $last_name      = trim($_POST['last_name'] ?? '');
    $email          = trim($_POST['email'] ?? '');
    $dial_code      = trim($_POST['dial_code'] ?? '+63');
    $contact_number = trim($_POST['contact_number'] ?? '');
    $password       = $_POST['password'] ?? '';
    $confirm        = $_POST['confirm_password'] ?? '';

    // Build full contact with dial code if number provided
    $full_contact = '';
    if (!empty($contact_number)) {
        $full_contact = $dial_code . $contact_number;
    }

    $full_name = $first_name;
    if (!empty($middle_name)) $full_name .= ' ' . $middle_name;
    $full_name .= ' ' . $last_name;
    $full_name = trim($full_name);

    if (empty($first_name) || empty($last_name) || empty($email) || empty($password) || empty($confirm)) {
        $error = 'Please fill in all required fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } elseif (!isset($_POST['terms'])) {
        $error = 'You must agree to the Terms of Service.';
    } else {
        $chk = mysqli_prepare($conn, "SELECT user_id FROM users WHERE email = ?");
        mysqli_stmt_bind_param($chk, 's', $email);
        mysqli_stmt_execute($chk);
        mysqli_stmt_store_result($chk);

        if (mysqli_stmt_num_rows($chk) > 0) {
            $error = 'An account with this email already exists.';
        } else {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $ins = mysqli_prepare($conn,
                "INSERT INTO users (full_name, email, password, contact_number, role) VALUES (?, ?, ?, ?, 'adopter')"
            );
            mysqli_stmt_bind_param($ins, 'ssss', $full_name, $email, $hashed, $full_contact);

            if (mysqli_stmt_execute($ins)) {
                $success = 'Account created successfully! Redirecting to login…';
                header('Refresh: 2; URL=login.php');
            } else {
                $error = 'Something went wrong. Please try again.';
            }
        }
    }
}

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
    <title>Create Account — PawPal</title>
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

        /* ── CRITICAL FIX: proper scroll layout ── */
        html, body {
            height: 100%;
            overflow: hidden; /* prevent double scrollbars */
        }

        body {
            font-family: 'Nunito', sans-serif;
            display: flex;
            background: var(--cream);
        }

        h1, h2, h3, h4 { font-family: 'Baloo 2', cursive; }

        /* ════════ LEFT PANEL ════════ */
        .panel-left {
            width: 42%;
            flex-shrink: 0;
            position: relative;
            overflow: hidden;
            /* Fixed height = viewport */
            height: 100vh;
        }

        .photo-grid {
            position: absolute;
            inset: 0;
            display: grid;
            grid-template-columns: 1fr 1fr;
            grid-template-rows: 35% 35% 30%;
            gap: 3px;
        }
        .cell { background-size: cover; background-position: center; }
        .cell-1 { background-image: url('https://images.unsplash.com/photo-1548767797-d8c844163c4a?w=800&q=80'); grid-column: span 2; }
        .cell-2 { background-image: url('https://images.unsplash.com/photo-1574158622682-e40e69881006?w=600&q=80'); }
        .cell-3 { background-image: url('https://images.unsplash.com/photo-1425082661705-1834bfd09dca?w=600&q=80'); }
        .cell-4 { background-image: url('https://images.unsplash.com/photo-1552053831-71594a27632d?w=600&q=80'); }
        .cell-5 { background-image: url('https://images.unsplash.com/photo-1587300003388-59208cc962cb?w=600&q=80'); }

        .panel-overlay {
            position: absolute; inset: 0;
            background: linear-gradient(150deg, rgba(25,12,8,0.82) 0%, rgba(90,50,38,0.58) 50%, rgba(255,112,67,0.22) 100%);
        }

        .panel-content {
            position: absolute; inset: 0; z-index: 2;
            display: flex; flex-direction: column;
            justify-content: space-between;
            padding: 2.2rem 2.5rem;
        }

        .brand-link {
            font-family: 'Baloo 2', cursive;
            font-size: 1.8rem; font-weight: 800;
            color: #fff; text-decoration: none;
            display: flex; align-items: center; gap: 0.5rem;
        }
        .brand-link img { height: 40px; filter: brightness(0) invert(1); }

        .panel-quote h2 {
            font-size: clamp(1.35rem, 2vw, 2rem);
            font-weight: 800; line-height: 1.28;
            color: #fff; margin-bottom: 0.8rem;
        }
        .panel-quote h2 em { font-style: normal; color: var(--soft-orange); }
        .panel-quote p { font-size: 0.85rem; color: rgba(255,255,255,0.7); font-weight: 600; }

        .perks { list-style: none; margin-top: 1rem; }
        .perks li {
            display: flex; align-items: center; gap: 0.6rem;
            color: rgba(255,255,255,0.85); font-size: 0.84rem;
            font-weight: 600; margin-bottom: 0.55rem;
        }
        .perk-dot {
            width: 26px; height: 26px; border-radius: 50%;
            background: rgba(255,179,71,0.22);
            border: 1.5px solid rgba(255,179,71,0.45);
            display: flex; align-items: center; justify-content: center;
            font-size: 0.8rem; flex-shrink: 0;
        }

        .panel-pills { display: flex; gap: 0.7rem; flex-wrap: wrap; }
        .pill {
            display: inline-flex; align-items: center; gap: 0.4rem;
            background: rgba(255,255,255,0.12);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.2);
            border-radius: 50px; padding: 0.4rem 1rem;
            color: #fff; font-size: 0.78rem; font-weight: 700;
        }
        .pill strong { color: var(--soft-orange); font-size: 0.95rem; }

        /* ════════ RIGHT PANEL — THE KEY FIX ════════ */
        .panel-right {
            flex: 1;
            height: 100vh;          /* exactly viewport height */
            overflow-y: auto;       /* THIS enables scrolling */
            display: flex;
            flex-direction: column;
            align-items: center;
            background: var(--cream);
            padding: 2rem 2rem 3rem; /* extra bottom padding so last field isn't cut */
        }

        /* subtle background decoration */
        .panel-right::before {
            content: '';
            position: fixed;
            bottom: -160px; right: -160px;
            width: 380px; height: 380px;
            background: radial-gradient(circle, rgba(255,179,71,0.10) 0%, transparent 70%);
            border-radius: 50%;
            pointer-events: none;
            z-index: 0;
        }

        .form-shell {
            width: 100%;
            max-width: 460px;
            position: relative;
            z-index: 1;
            animation: riseIn 0.5s cubic-bezier(.22,.68,0,1.2) both;
        }
        @keyframes riseIn {
            from { opacity: 0; transform: translateY(18px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .form-heading { font-size: 1.85rem; font-weight: 800; color: var(--text-dark); margin-bottom: 0.25rem; }
        .form-sub { color: var(--text-muted); font-size: 0.88rem; margin-bottom: 1.4rem; }

        .section-tag {
            font-size: 0.68rem; font-weight: 800;
            text-transform: uppercase; letter-spacing: 1px;
            color: var(--orange); margin-bottom: 0.65rem; margin-top: 1.1rem;
            display: flex; align-items: center; gap: 0.5rem;
        }
        .section-tag::after { content: ''; flex: 1; height: 1px; background: #E8D8CC; }

        /* inputs */
        .lbl {
            display: block; font-size: 0.73rem; font-weight: 700;
            text-transform: uppercase; letter-spacing: 0.5px;
            color: var(--brown); margin-bottom: 0.28rem;
        }
        .lbl .opt { font-weight: 500; color: var(--brown-light); text-transform: none; font-size: 0.7rem; }

        .ipt {
            width: 100%; border: 2px solid #E8D8CC; border-radius: 12px;
            padding: 0.65rem 1rem; font-family: 'Nunito', sans-serif;
            font-size: 0.9rem; color: var(--text-dark);
            background: #fff; transition: border-color 0.2s, box-shadow 0.2s; outline: none;
        }
        .ipt:focus { border-color: var(--orange); box-shadow: 0 0 0 3.5px rgba(255,112,67,0.12); }

        /* name grid */
        .name-grid { display: grid; grid-template-columns: 1fr 0.7fr 1fr; gap: 0.55rem; }

        /* ════ Phone field with country dial code ════ */
        .phone-row { display: flex; gap: 0; }

        .dial-wrapper {
            position: relative;
            flex-shrink: 0;
            width: 130px;
        }
        .dial-select {
            width: 100%;
            height: 100%;
            border: 2px solid #E8D8CC;
            border-right: none;
            border-radius: 12px 0 0 12px;
            padding: 0.65rem 2rem 0.65rem 0.6rem;
            font-family: 'Nunito', sans-serif;
            font-size: 0.85rem;
            color: var(--text-dark);
            background: #fff;
            appearance: none;
            -webkit-appearance: none;
            cursor: pointer;
            outline: none;
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        .dial-select:focus { border-color: var(--orange); box-shadow: 0 0 0 3.5px rgba(255,112,67,0.12); }
        .dial-arrow {
            position: absolute; right: 0.5rem; top: 50%;
            transform: translateY(-50%); pointer-events: none;
            color: var(--brown-light); font-size: 0.7rem;
        }
        .phone-ipt {
            flex: 1;
            border: 2px solid #E8D8CC;
            border-radius: 0 12px 12px 0;
            padding: 0.65rem 1rem;
            font-family: 'Nunito', sans-serif;
            font-size: 0.9rem; color: var(--text-dark);
            background: #fff; outline: none;
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        .phone-ipt:focus { border-color: var(--orange); box-shadow: 0 0 0 3.5px rgba(255,112,67,0.12); }

        /* make the borders join nicely on focus */
        .phone-row:focus-within .dial-select,
        .phone-row:focus-within .phone-ipt { border-color: var(--orange); }

        /* password toggle group */
        .ipt-group { display: flex; }
        .ipt-group .ipt { border-right: none; border-radius: 12px 0 0 12px; flex: 1; }
        .ipt-toggle {
            background: #fff; border: 2px solid #E8D8CC;
            border-left: none; border-radius: 0 12px 12px 0;
            padding: 0 0.85rem; cursor: pointer;
            color: var(--brown-light); font-size: 1rem;
            transition: color 0.2s, border-color 0.2s;
            display: flex; align-items: center;
        }
        .ipt-group:focus-within .ipt,
        .ipt-group:focus-within .ipt-toggle { border-color: var(--orange); }
        .ipt-group:focus-within .ipt-toggle { box-shadow: 0 0 0 3.5px rgba(255,112,67,0.12); }
        .ipt-toggle:hover { color: var(--orange); }

        /* strength bar */
        .strength-track { height: 4px; border-radius: 4px; background: #E8D8CC; margin-top: 6px; overflow: hidden; }
        .strength-fill { height: 100%; border-radius: 4px; width: 0; transition: width 0.3s, background 0.3s; }
        .strength-lbl { font-size: 0.72rem; font-weight: 700; margin-top: 3px; }
        .match-msg { font-size: 0.72rem; font-weight: 700; margin-top: 4px; }

        /* alerts */
        .err-banner {
            background: #FFF0ED; border: 1.5px solid #FFCCBC;
            border-radius: 10px; color: #BF360C;
            font-size: 0.84rem; font-weight: 600;
            padding: 0.65rem 1rem; display: flex;
            align-items: center; gap: 0.5rem;
            margin-bottom: 1.1rem; animation: shake 0.38s ease;
        }
        .ok-banner {
            background: #F1F8E9; border: 1.5px solid #C5E1A5;
            border-radius: 10px; color: #2E7D32;
            font-size: 0.84rem; font-weight: 600;
            padding: 0.65rem 1rem; display: flex;
            align-items: center; gap: 0.5rem; margin-bottom: 1.1rem;
        }
        @keyframes shake {
            0%,100%{transform:translateX(0)} 25%{transform:translateX(-5px)} 75%{transform:translateX(5px)}
        }

        /* terms */
        .terms-row {
            display: flex; align-items: flex-start; gap: 0.5rem;
            margin-top: 0.9rem; margin-bottom: 0.2rem;
        }
        .terms-row input[type="checkbox"] { accent-color: var(--orange); width: 15px; height: 15px; margin-top: 2px; flex-shrink: 0; }
        .terms-row label { font-size: 0.82rem; color: var(--text-muted); line-height: 1.4; }
        .terms-row label a { color: var(--orange); font-weight: 700; text-decoration: none; }
        .terms-row label a:hover { text-decoration: underline; }

        /* submit */
        .btn-go {
            width: 100%; background: var(--orange); color: #fff;
            border: none; border-radius: 12px; padding: 0.8rem;
            font-weight: 800; font-size: 1rem;
            font-family: 'Baloo 2', cursive; cursor: pointer;
            box-shadow: 0 5px 18px rgba(255,112,67,0.28);
            transition: all 0.22s; margin-top: 0.9rem;
        }
        .btn-go:hover { background: var(--orange-dark); transform: translateY(-2px); box-shadow: 0 9px 24px rgba(255,112,67,0.34); }
        .btn-go:active { transform: translateY(0); }

        .divider {
            display: flex; align-items: center; gap: 0.7rem;
            color: var(--brown-light); font-size: 0.76rem; font-weight: 700;
            margin: 1.1rem 0;
        }
        .divider::before, .divider::after { content: ''; flex: 1; height: 1.5px; background: #E8D8CC; }

        .login-nudge { text-align: center; font-size: 0.87rem; color: var(--text-muted); }
        .login-nudge a { color: var(--orange); font-weight: 700; text-decoration: none; }
        .login-nudge a:hover { text-decoration: underline; }

        .back-link {
            display: inline-flex; align-items: center; gap: 0.4rem;
            font-size: 0.78rem; font-weight: 700;
            color: var(--text-muted); text-decoration: none;
            margin-bottom: 1.4rem; transition: color 0.2s;
        }
        .back-link:hover { color: var(--orange); }

        /* field spacing helper */
        .field { margin-bottom: 0.85rem; }

        /* ════ Responsive ════ */
        @media (max-width: 860px) {
            html, body { overflow: hidden; height: 100%; }
            .panel-left { display: none; }
            .panel-right { width: 100%; height: 100vh; overflow-y: auto; padding: 1.8rem 1.4rem 3rem; }
            .name-grid { grid-template-columns: 1fr; }
            .dial-wrapper { width: 110px; }
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
            <h2>Start your journey<br>toward <em>responsible</em><br>pet ownership.</h2>
            <p>Join thousands of families who found their perfect companion.</p>
            <ul class="perks">
                <li><span class="perk-dot">🐾</span> Browse pets waiting for a home</li>
                <li><span class="perk-dot">📋</span> Submit adoption applications online</li>
                <li><span class="perk-dot">💉</span> Track your pet's care &amp; health records</li>
                <li><span class="perk-dot">✅</span> Get approved and bring home your pet</li>
            </ul>
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

        <div class="form-heading">Create Account 🐶</div>
        <p class="form-sub">Free forever. No hidden fees. Just pets.</p>

        <?php if ($error): ?>
        <div class="err-banner"><i class="bi bi-exclamation-circle-fill"></i> <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
        <div class="ok-banner"><i class="bi bi-check-circle-fill"></i> <?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <form method="POST" action="" novalidate>

            <!-- ── Personal Info ── -->
            <div class="section-tag">Personal Information</div>

            <!-- Name trio -->
            <div class="field">
                <div class="name-grid">
                    <div>
                        <label class="lbl" for="first_name">First Name <span style="color:#e53935">*</span></label>
                        <input type="text" class="ipt" id="first_name" name="first_name"
                            placeholder="Juan"
                            value="<?= htmlspecialchars($_POST['first_name'] ?? '') ?>"
                            required autocomplete="given-name">
                    </div>
                    <div>
                        <label class="lbl" for="middle_name">Middle <span class="opt">(opt.)</span></label>
                        <input type="text" class="ipt" id="middle_name" name="middle_name"
                            placeholder="D."
                            value="<?= htmlspecialchars($_POST['middle_name'] ?? '') ?>"
                            autocomplete="additional-name">
                    </div>
                    <div>
                        <label class="lbl" for="last_name">Last Name <span style="color:#e53935">*</span></label>
                        <input type="text" class="ipt" id="last_name" name="last_name"
                            placeholder="dela Cruz"
                            value="<?= htmlspecialchars($_POST['last_name'] ?? '') ?>"
                            required autocomplete="family-name">
                    </div>
                </div>
            </div>

            <!-- Email -->
            <div class="field">
                <label class="lbl" for="email">Email Address <span style="color:#e53935">*</span></label>
                <input type="email" class="ipt" id="email" name="email"
                    placeholder="juan@email.com"
                    value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                    required autocomplete="email">
            </div>

            <!-- Contact Number with dial code -->
            <div class="field">
                <label class="lbl" for="contact_number">
                    Contact Number <span class="opt">(optional)</span>
                </label>
                <div class="phone-row">
                    <!-- Country dial code selector -->
                    <div class="dial-wrapper">
                        <select class="dial-select" name="dial_code" id="dial_code" aria-label="Country code">
                            <!-- Asia Pacific -->
                            <optgroup label="🌏 Asia Pacific">
                                <option value="+63" <?= (($_POST['dial_code'] ?? '+63') === '+63') ? 'selected' : '' ?>>🇵🇭 +63</option>
                                <option value="+1"  <?= (($_POST['dial_code'] ?? '') === '+1')  ? 'selected' : '' ?>>🇺🇸 +1</option>
                                <option value="+61" <?= (($_POST['dial_code'] ?? '') === '+61') ? 'selected' : '' ?>>🇦🇺 +61</option>
                                <option value="+86" <?= (($_POST['dial_code'] ?? '') === '+86') ? 'selected' : '' ?>>🇨🇳 +86</option>
                                <option value="+91" <?= (($_POST['dial_code'] ?? '') === '+91') ? 'selected' : '' ?>>🇮🇳 +91</option>
                                <option value="+81" <?= (($_POST['dial_code'] ?? '') === '+81') ? 'selected' : '' ?>>🇯🇵 +81</option>
                                <option value="+82" <?= (($_POST['dial_code'] ?? '') === '+82') ? 'selected' : '' ?>>🇰🇷 +82</option>
                                <option value="+65" <?= (($_POST['dial_code'] ?? '') === '+65') ? 'selected' : '' ?>>🇸🇬 +65</option>
                                <option value="+60" <?= (($_POST['dial_code'] ?? '') === '+60') ? 'selected' : '' ?>>🇲🇾 +60</option>
                                <option value="+62" <?= (($_POST['dial_code'] ?? '') === '+62') ? 'selected' : '' ?>>🇮🇩 +62</option>
                                <option value="+66" <?= (($_POST['dial_code'] ?? '') === '+66') ? 'selected' : '' ?>>🇹🇭 +66</option>
                                <option value="+84" <?= (($_POST['dial_code'] ?? '') === '+84') ? 'selected' : '' ?>>🇻🇳 +84</option>
                                <option value="+64" <?= (($_POST['dial_code'] ?? '') === '+64') ? 'selected' : '' ?>>🇳🇿 +64</option>
                                <option value="+92" <?= (($_POST['dial_code'] ?? '') === '+92') ? 'selected' : '' ?>>🇵🇰 +92</option>
                                <option value="+880" <?= (($_POST['dial_code'] ?? '') === '+880') ? 'selected' : '' ?>>🇧🇩 +880</option>
                                <option value="+94" <?= (($_POST['dial_code'] ?? '') === '+94') ? 'selected' : '' ?>>🇱🇰 +94</option>
                            </optgroup>
                            <!-- Americas -->
                            <optgroup label="🌎 Americas">
                                <option value="+1" data-country="CA" <?= (($_POST['dial_code'] ?? '') === '+1CA') ? 'selected' : '' ?>>🇨🇦 +1</option>
                                <option value="+52" <?= (($_POST['dial_code'] ?? '') === '+52') ? 'selected' : '' ?>>🇲🇽 +52</option>
                                <option value="+55" <?= (($_POST['dial_code'] ?? '') === '+55') ? 'selected' : '' ?>>🇧🇷 +55</option>
                                <option value="+54" <?= (($_POST['dial_code'] ?? '') === '+54') ? 'selected' : '' ?>>🇦🇷 +54</option>
                                <option value="+56" <?= (($_POST['dial_code'] ?? '') === '+56') ? 'selected' : '' ?>>🇨🇱 +56</option>
                                <option value="+57" <?= (($_POST['dial_code'] ?? '') === '+57') ? 'selected' : '' ?>>🇨🇴 +57</option>
                            </optgroup>
                            <!-- Europe -->
                            <optgroup label="🌍 Europe">
                                <option value="+44" <?= (($_POST['dial_code'] ?? '') === '+44') ? 'selected' : '' ?>>🇬🇧 +44</option>
                                <option value="+33" <?= (($_POST['dial_code'] ?? '') === '+33') ? 'selected' : '' ?>>🇫🇷 +33</option>
                                <option value="+49" <?= (($_POST['dial_code'] ?? '') === '+49') ? 'selected' : '' ?>>🇩🇪 +49</option>
                                <option value="+39" <?= (($_POST['dial_code'] ?? '') === '+39') ? 'selected' : '' ?>>🇮🇹 +39</option>
                                <option value="+34" <?= (($_POST['dial_code'] ?? '') === '+34') ? 'selected' : '' ?>>🇪🇸 +34</option>
                                <option value="+31" <?= (($_POST['dial_code'] ?? '') === '+31') ? 'selected' : '' ?>>🇳🇱 +31</option>
                                <option value="+32" <?= (($_POST['dial_code'] ?? '') === '+32') ? 'selected' : '' ?>>🇧🇪 +32</option>
                                <option value="+41" <?= (($_POST['dial_code'] ?? '') === '+41') ? 'selected' : '' ?>>🇨🇭 +41</option>
                                <option value="+48" <?= (($_POST['dial_code'] ?? '') === '+48') ? 'selected' : '' ?>>🇵🇱 +48</option>
                                <option value="+7"  <?= (($_POST['dial_code'] ?? '') === '+7')  ? 'selected' : '' ?>>🇷🇺 +7</option>
                                <option value="+380" <?= (($_POST['dial_code'] ?? '') === '+380') ? 'selected' : '' ?>>🇺🇦 +380</option>
                            </optgroup>
                            <!-- Middle East & Africa -->
                            <optgroup label="🌍 Middle East & Africa">
                                <option value="+971" <?= (($_POST['dial_code'] ?? '') === '+971') ? 'selected' : '' ?>>🇦🇪 +971</option>
                                <option value="+966" <?= (($_POST['dial_code'] ?? '') === '+966') ? 'selected' : '' ?>>🇸🇦 +966</option>
                                <option value="+972" <?= (($_POST['dial_code'] ?? '') === '+972') ? 'selected' : '' ?>>🇮🇱 +972</option>
                                <option value="+20"  <?= (($_POST['dial_code'] ?? '') === '+20')  ? 'selected' : '' ?>>🇪🇬 +20</option>
                                <option value="+27"  <?= (($_POST['dial_code'] ?? '') === '+27')  ? 'selected' : '' ?>>🇿🇦 +27</option>
                                <option value="+234" <?= (($_POST['dial_code'] ?? '') === '+234') ? 'selected' : '' ?>>🇳🇬 +234</option>
                                <option value="+254" <?= (($_POST['dial_code'] ?? '') === '+254') ? 'selected' : '' ?>>🇰🇪 +254</option>
                            </optgroup>
                        </select>
                        <span class="dial-arrow"><i class="bi bi-chevron-down"></i></span>
                    </div>

                    <!-- Number input -->
                        <input type="tel" class="phone-ipt" id="contact_number" name="contact_number"
                                placeholder="9123456789"
                                 value="<?= htmlspecialchars($_POST['contact_number'] ?? '') ?>"
                                autocomplete="tel-national"
                                inputmode="numeric"
                                 maxlength="15">
                </div>
                <div style="font-size:0.7rem;color:var(--brown-light);margin-top:4px;font-weight:600;">
                    <i class="bi bi-info-circle"></i> Enter number without leading 0 (e.g. 9123456789)
                </div>
            </div>

            <!-- ── Security ── -->
            <div class="section-tag">Security</div>

            <!-- Password -->
            <div class="field">
                <label class="lbl" for="password">Password <span style="color:#e53935">*</span></label>
                <div class="ipt-group">
                    <input type="password" class="ipt" id="password" name="password"
                        placeholder="Min. 8 characters" required
                        autocomplete="new-password"
                        oninput="checkStrength(this.value)">
                    <button type="button" class="ipt-toggle" onclick="togglePw('password',this)" aria-label="Toggle">
                        <i class="bi bi-eye"></i>
                    </button>
                </div>
                <div class="strength-track"><div class="strength-fill" id="sFill"></div></div>
                <div class="strength-lbl" id="sLbl"></div>
            </div>

            <!-- Confirm password -->
            <div class="field" style="margin-bottom:0.3rem;">
                <label class="lbl" for="confirm_password">Confirm Password <span style="color:#e53935">*</span></label>
                <div class="ipt-group">
                    <input type="password" class="ipt" id="confirm_password" name="confirm_password"
                        placeholder="Re-enter password" required
                        autocomplete="new-password"
                        oninput="checkMatch()">
                    <button type="button" class="ipt-toggle" onclick="togglePw('confirm_password',this)" aria-label="Toggle">
                        <i class="bi bi-eye"></i>
                    </button>
                </div>
                <div class="match-msg" id="matchMsg"></div>
            </div>

            <!-- Terms -->
            <div class="terms-row">
                <input type="checkbox" id="terms" name="terms" required>
                <label for="terms">
                  I agree to the <a href="#" onclick="openModal('terms'); return false;">Terms of Service</a> and <a href="#" onclick="openModal('privacy'); return false;">Privacy Policy</a>                  </label>
            </div>

            <button type="submit" class="btn-go">
                <i class="bi bi-person-plus-fill me-2"></i>Create My Account
            </button>
        </form>

        <div class="divider">or</div>

        <div class="login-nudge">
            Already have an account? <a href="login.php">Log in here</a>
        </div>

    </div>
</div>


<!-- ══ Terms / Privacy Modal ══ -->
<div id="modalOverlay" style="
    display:none; position:fixed; inset:0; z-index:9999;
    background:rgba(30,10,5,0.55); backdrop-filter:blur(4px);
    align-items:center; justify-content:center; padding:1rem;">
    <div style="
        background:#fff; border-radius:18px; max-width:520px; width:100%;
        max-height:82vh; display:flex; flex-direction:column;
        box-shadow:0 20px 60px rgba(0,0,0,0.25); overflow:hidden;">
        <!-- Header -->
        <div style="padding:1.3rem 1.5rem 1rem; border-bottom:1.5px solid #F0E4DC;
            display:flex; align-items:center; justify-content:space-between; flex-shrink:0;">
            <h3 id="modalTitle" style="font-family:'Baloo 2',cursive; color:#3E2723; margin:0; font-size:1.2rem;"></h3>
            <button onclick="closeModal()" style="
                background:none; border:none; cursor:pointer; font-size:1.3rem;
                color:#A1887F; line-height:1; padding:0.2rem 0.4rem; border-radius:8px;
                transition:color 0.2s;" onmouseover="this.style.color='#FF7043'"
                onmouseout="this.style.color='#A1887F'">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>
        <!-- Body -->
        <div id="modalBody" style="padding:1.3rem 1.5rem; overflow-y:auto; font-size:0.875rem;
            color:#5D4037; line-height:1.75; font-family:'Nunito',sans-serif;"></div>
        <!-- Footer -->
        <div style="padding:1rem 1.5rem; border-top:1.5px solid #F0E4DC; flex-shrink:0; text-align:right;">
            <button onclick="closeModal()" style="
                background:#FF7043; color:#fff; border:none; border-radius:10px;
                padding:0.55rem 1.4rem; font-weight:800; font-size:0.9rem;
                font-family:'Baloo 2',cursive; cursor:pointer;
                box-shadow:0 4px 14px rgba(255,112,67,0.3); transition:background 0.2s;"
                onmouseover="this.style.background='#E64A19'"
                onmouseout="this.style.background='#FF7043'">
                Got it 🐾
            </button>
        </div>
    </div>
</div>

<script>
    
const modalContent = {
    terms: {
        title: '📋 Terms of Service',
        body: `
            <p><strong>Effective Date:</strong> January 1, 2025</p>
            <p>Welcome to <strong>PawPal</strong>. By creating an account, you agree to the following terms:</p>
            <h5 style="font-family:'Baloo 2',cursive;color:#FF7043;margin:1rem 0 0.4rem;">1. Account Responsibility</h5>
            <p>You are responsible for maintaining the confidentiality of your account credentials and all activity under your account.</p>
            <h5 style="font-family:'Baloo 2',cursive;color:#FF7043;margin:1rem 0 0.4rem;">2. Adoption Applications</h5>
            <p>All information provided during the adoption process must be accurate and truthful. False information may result in application denial or account suspension.</p>
            <h5 style="font-family:'Baloo 2',cursive;color:#FF7043;margin:1rem 0 0.4rem;">3. Animal Welfare</h5>
            <p>Adopted pets must be treated humanely and in accordance with local animal welfare laws. PawPal reserves the right to conduct follow-up checks.</p>
            <h5 style="font-family:'Baloo 2',cursive;color:#FF7043;margin:1rem 0 0.4rem;">4. Prohibited Use</h5>
            <p>You may not use PawPal to resell, transfer, or misrepresent pets. Accounts found in violation will be permanently banned.</p>
            <h5 style="font-family:'Baloo 2',cursive;color:#FF7043;margin:1rem 0 0.4rem;">5. Termination</h5>
            <p>PawPal reserves the right to suspend or terminate accounts that violate these terms at any time without prior notice.</p>
        `
    },
    privacy: {
        title: '🔒 Privacy Policy',
        body: `
            <p><strong>Effective Date:</strong> January 1, 2025</p>
            <p>Your privacy matters to us. Here's how <strong>PawPal</strong> handles your data:</p>
            <h5 style="font-family:'Baloo 2',cursive;color:#FF7043;margin:1rem 0 0.4rem;">1. Data We Collect</h5>
            <p>We collect your name, email, and optional contact number solely to process adoption applications and communicate with you.</p>
            <h5 style="font-family:'Baloo 2',cursive;color:#FF7043;margin:1rem 0 0.4rem;">2. How We Use It</h5>
            <p>Your data is used to manage your account, process applications, and send relevant notifications. We never sell your data to third parties.</p>
            <h5 style="font-family:'Baloo 2',cursive;color:#FF7043;margin:1rem 0 0.4rem;">3. Data Security</h5>
            <p>Passwords are hashed using industry-standard encryption. We use secure connections (HTTPS) to protect your information in transit.</p>
            <h5 style="font-family:'Baloo 2',cursive;color:#FF7043;margin:1rem 0 0.4rem;">4. Cookies</h5>
            <p>We use session cookies only to keep you logged in. No tracking or advertising cookies are used.</p>
            <h5 style="font-family:'Baloo 2',cursive;color:#FF7043;margin:1rem 0 0.4rem;">5. Your Rights</h5>
            <p>You may request deletion of your account and associated data at any time by contacting our support team.</p>
        `
    }
};

function openModal(type) {
    const overlay = document.getElementById('modalOverlay');
    document.getElementById('modalTitle').textContent = modalContent[type].title;
    document.getElementById('modalBody').innerHTML    = modalContent[type].body;
    overlay.style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function closeModal() {
    document.getElementById('modalOverlay').style.display = 'none';
    document.body.style.overflow = '';
}

// Close when clicking outside the modal box
document.getElementById('modalOverlay').addEventListener('click', function(e) {
    if (e.target === this) closeModal();
});

// Close on Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeModal();
});


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
    if (v.length >= 8)           s++;
    if (/[A-Z]/.test(v))         s++;
    if (/[0-9]/.test(v))         s++;
    if (/[^A-Za-z0-9]/.test(v))  s++;
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

// Strip leading zero from phone input when user types
document.getElementById('contact_number').addEventListener('input', function() {
    // Remove anything that isn't a digit
    this.value = this.value.replace(/\D/g, '');
    // Strip leading zero
    if (this.value.startsWith('0')) {
        this.value = this.value.substring(1);
    }
});


</script>
</body>
</html>