<?php
require_once 'config/database.php';

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

// If already logged in, skip landing page entirely
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] === 'adopter') {
        header('Location: dashboard.php');
    } else {
        header('Location: admin/dashboard.php'); // adjust if your admin path differs
    }
    exit;
}

// ── Fetch up to 4 available pets (newest first) ──
$pets_sql = "SELECT * FROM pets WHERE status = 'available' ORDER BY created_at DESC LIMIT 4";
$pets_result = mysqli_query($conn, $pets_sql);
$pets = [];
while ($row = mysqli_fetch_assoc($pets_result)) {
    $pets[] = $row;
}

// ── Fetch bonded partner names for display ──
// Build a map of pet_id => name for bonded pairs
$bonded_ids = array_filter(array_column($pets, 'bonded_with'));
$bonded_map = [];
if (!empty($bonded_ids)) {
    $ids_str = implode(',', array_map('intval', $bonded_ids));
    $bonded_sql = "SELECT pet_id, name FROM pets WHERE pet_id IN ($ids_str)";
    $bonded_result = mysqli_query($conn, $bonded_sql);
    while ($b = mysqli_fetch_assoc($bonded_result)) {
        $bonded_map[$b['pet_id']] = $b['name'];
    }
}

// ── Fetch stats for hero section ──
$total_available = mysqli_fetch_row(mysqli_query($conn, "SELECT COUNT(*) FROM pets WHERE status = 'available'"))[0];
$total_adopted   = mysqli_fetch_row(mysqli_query($conn, "SELECT COUNT(*) FROM pets WHERE status = 'adopted'"))[0];
$total_users     = mysqli_fetch_row(mysqli_query($conn, "SELECT COUNT(*) FROM users WHERE role = 'adopter'"))[0];

// ── Pet species to emoji map ──
function petEmoji($species) {
    $map = [
        'dog'    => '🐶',
        'cat'    => '🐱',
        'rabbit' => '🐰',
        'bird'   => '🐦',
        'hamster'=> '🐹',
        'fish'   => '🐠',
        'turtle' => '🐢',
    ];
    return $map[strtolower($species)] ?? '🐾';
}

// ── Format age display ──
function formatAge($years, $months) {
    if ($years > 0 && $months > 0) return "{$years} yr {$months} mo";
    if ($years > 0) return $years == 1 ? "1 yr" : "{$years} yrs";
    if ($months > 0) return $months == 1 ? "1 mo" : "{$months} mos";
    return "Unknown";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PawPal — Find Your Forever Friend</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Baloo+2:wght@400;500;600;700;800&family=Nunito:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --cream: #FFF8F0;
            --warm-orange: #FF7043;
            --soft-orange: #FFB347;
            --warm-brown: #6D4C41;
            --light-brown: #A1887F;
            --soft-green: #81C784;
            --soft-pink: #F48FB1;
            --card-bg: #FFFFFF;
            --text-dark: #3E2723;
            --text-muted: #795548;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Nunito', sans-serif;
            background-color: var(--cream);
            color: var(--text-dark);
            overflow-x: hidden;
        }

        h1, h2, h3, h4, h5 { font-family: 'Baloo 2', cursive; }

        /* ── NAVBAR ── */
        .navbar {
            background-color: #fff;
            box-shadow: 0 2px 16px rgba(255,112,67,0.10);
            padding: 0.7rem 0;
            position: sticky;
            top: 0;
            z-index: 999;
        }
        .navbar-brand {
            font-family: 'Baloo 2', cursive;
            font-size: 1.8rem;
            font-weight: 800;
            color: var(--warm-orange) !important;
        }
        .navbar-brand span { color: var(--warm-brown); }
        .navbar-brand img {
            height: 100px;
            width: auto;
            margin-right: 0.4rem;
        }
        .nav-link {
            font-weight: 600;
            color: var(--text-muted) !important;
            transition: color 0.2s;
            padding: 0.4rem 1rem !important;
        }
        .nav-link:hover { color: var(--warm-orange) !important; }
        .btn-nav-login {
            border: 2px solid var(--warm-orange);
            color: var(--warm-orange) !important;
            border-radius: 50px;
            padding: 0.35rem 1.3rem !important;
            font-weight: 700;
            transition: all 0.2s;
        }
        .btn-nav-login:hover { background: var(--warm-orange); color: #fff !important; }
        .btn-nav-register {
            background: var(--warm-orange);
            color: #fff !important;
            border-radius: 50px;
            padding: 0.35rem 1.3rem !important;
            font-weight: 700;
            transition: all 0.2s;
            border: 2px solid var(--warm-orange);
        }
        .btn-nav-register:hover { background: #e64a19; border-color: #e64a19; }

        /* ── HERO ── */
        .hero {
            background: linear-gradient(135deg, #FFF3E0 0%, #FFF8F0 50%, #FCE4EC 100%);
            padding: 90px 0 70px;
            position: relative;
            overflow: hidden;
        }
        .hero::before {
            content: '';
            position: absolute;
            top: -80px; right: -80px;
            width: 400px; height: 400px;
            background: radial-gradient(circle, rgba(255,179,71,0.18) 0%, transparent 70%);
            border-radius: 50%;
        }
        .hero-badge {
            display: inline-block;
            background: rgba(255,112,67,0.12);
            color: var(--warm-orange);
            font-weight: 700;
            font-size: 0.85rem;
            padding: 0.35rem 1rem;
            border-radius: 50px;
            margin-bottom: 1.2rem;
        }
        .hero h1 {
            font-size: clamp(2.4rem, 5vw, 3.8rem);
            font-weight: 800;
            line-height: 1.15;
            margin-bottom: 1.2rem;
        }
        .hero h1 .highlight { color: var(--warm-orange); }
        .hero p.lead {
            font-size: 1.1rem;
            color: var(--text-muted);
            max-width: 500px;
            line-height: 1.7;
            margin-bottom: 2rem;
        }
        .btn-hero-primary {
            background: var(--warm-orange);
            color: #fff;
            border-radius: 50px;
            padding: 0.75rem 2rem;
            font-weight: 700;
            font-size: 1rem;
            border: none;
            transition: all 0.25s;
            box-shadow: 0 6px 20px rgba(255,112,67,0.35);
            text-decoration: none;
            display: inline-block;
        }
        .btn-hero-primary:hover {
            background: #e64a19;
            transform: translateY(-2px);
            color: #fff;
        }
        .btn-hero-secondary {
            background: transparent;
            color: var(--warm-brown);
            border-radius: 50px;
            padding: 0.75rem 2rem;
            font-weight: 700;
            font-size: 1rem;
            border: 2px solid var(--light-brown);
            transition: all 0.25s;
            text-decoration: none;
            display: inline-block;
        }
        .btn-hero-secondary:hover { border-color: var(--warm-orange); color: var(--warm-orange); }
        .hero-stats { display: flex; gap: 2.5rem; margin-top: 2.5rem; flex-wrap: wrap; }
        .hero-stat { display: flex; flex-direction: column; }
        .hero-stat-number {
            font-family: 'Baloo 2', cursive;
            font-size: 1.9rem;
            font-weight: 800;
            color: var(--warm-orange);
            line-height: 1;
        }
        .hero-stat-label { font-size: 0.82rem; color: var(--text-muted); font-weight: 600; }
        .hero-image-area {
            position: relative;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 380px;
        }
        .hero-blob {
            width: 380px; height: 380px;
            background: linear-gradient(135deg, #FFB347 0%, #FF7043 100%);
            border-radius: 60% 40% 55% 45% / 50% 60% 40% 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 8.5rem;
            box-shadow: 0 20px 60px rgba(255,112,67,0.3);
            animation: blobFloat 6s ease-in-out infinite;
        }
        @keyframes blobFloat {
            0%, 100% { transform: translateY(0); border-radius: 60% 40% 55% 45% / 50% 60% 40% 50%; }
            33% { transform: translateY(-12px); border-radius: 50% 50% 45% 55% / 55% 45% 55% 45%; }
            66% { transform: translateY(-6px); border-radius: 55% 45% 60% 40% / 45% 55% 45% 55%; }
        }
        .hero-floating-card {
            position: absolute;
            background: #fff;
            border-radius: 16px;
            padding: 0.75rem 1rem;
            box-shadow: 0 8px 30px rgba(0,0,0,0.12);
            font-size: 0.85rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            animation: floatCard 4s ease-in-out infinite;
        }
        .hero-floating-card.card-1 { top: 30px; left: 0; animation-delay: 0s; }
        .hero-floating-card.card-2 { bottom: 50px; right: 0; animation-delay: 1.5s; }
        .hero-floating-card.card-3 {
            top: 50%; left: -10px;
            animation: floatCard3 4s ease-in-out infinite;
            animation-delay: 0.8s;
        }
        @keyframes floatCard { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-8px); } }
        @keyframes floatCard3 { 0%, 100% { transform: translateY(-50%); } 50% { transform: translateY(calc(-50% - 8px)); } }
        .fc-icon {
            width: 32px; height: 32px;
            border-radius: 8px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1rem;
        }

        /* ── SECTION COMMONS ── */
        .section-title { font-size: clamp(1.8rem, 3vw, 2.5rem); font-weight: 800; color: var(--text-dark); margin-bottom: 0.5rem; }
        .section-subtitle { color: var(--text-muted); font-size: 1.05rem; max-width: 520px; margin: 0 auto; line-height: 1.6; }
        .section-label { font-size: 0.82rem; font-weight: 700; letter-spacing: 1.5px; text-transform: uppercase; color: var(--warm-orange); margin-bottom: 0.5rem; }

        /* ── PETS SECTION ── */
        .pets-section { padding: 90px 0; background: #fff; }
        .pet-card {
            background: #fff;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0,0,0,0.07);
            transition: all 0.3s ease;
            border: 2px solid transparent;
            height: 100%;
        }
        .pet-card:hover {
            transform: translateY(-6px);
            box-shadow: 0 16px 40px rgba(255,112,67,0.18);
            border-color: rgba(255,112,67,0.3);
        }
        .pet-image-wrap {
            height: 200px;
            background: linear-gradient(135deg, #FFF3E0, #FCE4EC);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 5rem;
            position: relative;
            overflow: hidden;
        }
        .pet-image-wrap img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            position: absolute;
            top: 0; left: 0;
        }
        .pet-status-badge {
            position: absolute;
            top: 12px; right: 12px;
            background: #fff;
            border-radius: 50px;
            padding: 0.2rem 0.75rem;
            font-size: 0.75rem;
            font-weight: 700;
            color: #4CAF50;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            z-index: 1;
        }
        .pet-bonded-badge {
            position: absolute;
            top: 12px; left: 12px;
            background: var(--soft-pink);
            border-radius: 50px;
            padding: 0.2rem 0.75rem;
            font-size: 0.72rem;
            font-weight: 700;
            color: #fff;
            z-index: 1;
        }
        .pet-card-body { padding: 1.2rem 1.4rem 1.4rem; }
        .pet-name { font-family: 'Baloo 2', cursive; font-size: 1.25rem; font-weight: 700; color: var(--text-dark); margin-bottom: 0.2rem; }
        .pet-breed { font-size: 0.85rem; color: var(--light-brown); font-weight: 600; margin-bottom: 0.8rem; }
        .pet-meta { display: flex; gap: 0.5rem; flex-wrap: wrap; margin-bottom: 1rem; }
        .pet-tag {
            background: var(--cream);
            border-radius: 50px;
            padding: 0.2rem 0.75rem;
            font-size: 0.78rem;
            font-weight: 700;
            color: var(--text-muted);
            display: flex;
            align-items: center;
            gap: 0.3rem;
        }
        .btn-adopt {
            width: 100%;
            background: var(--warm-orange);
            color: #fff;
            border: none;
            border-radius: 50px;
            padding: 0.6rem;
            font-weight: 700;
            font-size: 0.92rem;
            transition: all 0.2s;
            text-decoration: none;
            display: block;
            text-align: center;
        }
        .btn-adopt:hover { background: #e64a19; color: #fff; transform: translateY(-1px); }
        .btn-view-all {
            border: 2px solid var(--warm-orange);
            color: var(--warm-orange);
            border-radius: 50px;
            padding: 0.65rem 2rem;
            font-weight: 700;
            text-decoration: none;
            transition: all 0.2s;
            display: inline-block;
        }
        .btn-view-all:hover { background: var(--warm-orange); color: #fff; }

        /* ── NO PETS STATE ── */
        .no-pets {
            text-align: center;
            padding: 3rem;
            color: var(--text-muted);
        }
        .no-pets .no-pets-icon { font-size: 4rem; margin-bottom: 1rem; }
        .no-pets p { font-size: 1rem; }

        /* ── HOW IT WORKS ── */
        .hiw-section { padding: 90px 0; background: linear-gradient(135deg, #FFF3E0 0%, var(--cream) 100%); }
        .hiw-step { text-align: center; padding: 0 0.5rem; }
        .hiw-step-icon {
            width: 90px; height: 90px;
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 2.2rem;
            margin: 0 auto 1.2rem;
            position: relative;
        }
        .hiw-step-number {
            position: absolute;
            top: -4px; right: -4px;
            width: 26px; height: 26px;
            background: var(--warm-orange);
            color: #fff;
            border-radius: 50%;
            font-size: 0.72rem;
            font-weight: 800;
            display: flex; align-items: center; justify-content: center;
            font-family: 'Baloo 2', cursive;
            border: 2px solid #fff;
        }
        .hiw-step h5 { font-size: 1rem; font-weight: 700; color: var(--text-dark); margin-bottom: 0.4rem; }
        .hiw-step p { font-size: 0.85rem; color: var(--text-muted); line-height: 1.5; }

        /* ── ABOUT ── */
        .about-section { padding: 90px 0; background: #fff; }
        .about-image-stack { position: relative; height: 380px; }
        .about-blob-main {
            width: 300px; height: 300px;
            background: linear-gradient(135deg, #FFB347, #FF7043);
            border-radius: 55% 45% 60% 40% / 50% 55% 45% 50%;
            position: absolute; top: 30px; left: 20px;
            display: flex; align-items: center; justify-content: center;
            font-size: 7rem;
            box-shadow: 0 16px 50px rgba(255,112,67,0.3);
        }
        .about-blob-secondary {
            width: 160px; height: 160px;
            background: linear-gradient(135deg, #F48FB1, #FFB347);
            border-radius: 50%;
            position: absolute; bottom: 10px; right: 20px;
            display: flex; align-items: center; justify-content: center;
            font-size: 3.5rem;
            box-shadow: 0 10px 30px rgba(244,143,177,0.35);
        }
        .about-stats-card {
            position: absolute; top: 0; right: 0;
            background: #fff;
            border-radius: 18px;
            padding: 1.1rem 1.4rem;
            box-shadow: 0 8px 30px rgba(0,0,0,0.12);
            min-width: 150px;
        }
        .about-content h2 { font-size: clamp(1.8rem, 3vw, 2.4rem); font-weight: 800; line-height: 1.2; margin-bottom: 1rem; }
        .about-content p { color: var(--text-muted); line-height: 1.75; margin-bottom: 1rem; font-size: 0.98rem; }
        .about-pill {
            display: inline-flex; align-items: center; gap: 0.5rem;
            background: var(--cream); border-radius: 50px;
            padding: 0.5rem 1.1rem; font-size: 0.88rem; font-weight: 700;
            color: var(--warm-brown); margin: 0.3rem;
        }
        .about-pill i { color: var(--warm-orange); }

        /* ── FOOTER ── */
        footer { background: var(--text-dark); color: #EFEBE9; padding: 60px 0 30px; }
        .brand-footer { font-family: 'Baloo 2', cursive; font-size: 1.8rem; font-weight: 800; color: var(--soft-orange); }
        .brand-footer img { height: 36px; width: auto; margin-right: 0.4rem; vertical-align: middle; }
        footer p.tagline { color: #BCAAA4; font-size: 0.92rem; line-height: 1.6; margin-top: 0.5rem; }
        footer h6 { font-family: 'Baloo 2', cursive; font-size: 1rem; font-weight: 700; color: #fff; margin-bottom: 1rem; }
        footer ul { list-style: none; padding: 0; }
        footer ul li { margin-bottom: 0.5rem; }
        footer ul li a { color: #BCAAA4; text-decoration: none; font-size: 0.9rem; transition: color 0.2s; }
        footer ul li a:hover { color: var(--soft-orange); }
        .footer-divider { border-color: rgba(255,255,255,0.1); margin: 2rem 0 1.5rem; }
        .footer-bottom { font-size: 0.85rem; color: #8D6E63; display: flex; justify-content: space-between; flex-wrap: wrap; gap: 0.5rem; }
        .social-link {
            width: 36px; height: 36px; border-radius: 50%;
            background: rgba(255,255,255,0.08);
            display: inline-flex; align-items: center; justify-content: center;
            color: #BCAAA4; text-decoration: none; transition: all 0.2s; margin-right: 0.4rem;
        }
        .social-link:hover { background: var(--warm-orange); color: #fff; }

        /* ── ANIMATIONS ── */
        .fade-in-up { opacity: 0; transform: translateY(30px); transition: opacity 0.6s ease, transform 0.6s ease; }
        .fade-in-up.visible { opacity: 1; transform: translateY(0); }

        /* ── RESPONSIVE ── */
        @media (max-width: 992px) {
            .hero-blob { width: 300px; height: 300px; font-size: 6.5rem; }
        }
        @media (max-width: 768px) {
            .hero { padding: 60px 0 40px; }
            .hero-blob { width: 260px; height: 260px; font-size: 5.5rem; }
            .hero-floating-card { display: none; }
            .about-image-stack { height: 260px; margin-bottom: 2rem; }
            .about-blob-main { width: 210px; height: 210px; font-size: 5rem; }
            .about-blob-secondary { width: 120px; height: 120px; font-size: 2.5rem; }
        }
    </style>
</head>
<body>

<!-- ══════════════════════════════════════
     NAVBAR
══════════════════════════════════════ -->
<nav class="navbar navbar-expand-lg">
    <div class="container">
        <a class="navbar-brand" href="index.php">
            <?php if (file_exists('assets/images/logo.png')): ?>
                <img src="assets/images/logo.png" alt="PawPal Logo">
            <?php else: ?>
                🐾 Paw<span>Pal</span>
            <?php endif; ?>
        </a>
        <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navMenu">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navMenu">
            <ul class="navbar-nav mx-auto gap-1">
                <li class="nav-item"><a class="nav-link" href="#pets">Meet Our Pets</a></li>
                <li class="nav-item"><a class="nav-link" href="#how-it-works">How It Works</a></li>
                <li class="nav-item"><a class="nav-link" href="#about">About Us</a></li>
            </ul>
            <div class="d-flex gap-2 mt-2 mt-lg-0">
                <a href="auth/login.php" class="nav-link btn-nav-login">Log In</a>
                <a href="auth/register.php" class="nav-link btn-nav-register">Register</a>
            </div>
        </div>
    </div>
</nav>

<!-- ══════════════════════════════════════
     HERO
══════════════════════════════════════ -->
<section class="hero">
    <div class="container">
        <div class="row align-items-center g-5">
            <div class="col-lg-6">
                <div class="hero-badge">🏠 &nbsp;Every pet deserves a loving home</div>
                <h1>Find Your <span class="highlight">Forever</span><br>Furry Friend</h1>
                <p class="lead">Browse adorable pets waiting for a family just like yours. Submit an application and start your journey toward responsible pet ownership.</p>
                <div class="d-flex gap-3 flex-wrap">
                    <a href="auth/register.php" class="btn-hero-primary">
                        <i class="bi bi-heart-fill me-2"></i>Start Adopting
                    </a>
                    <a href="#how-it-works" class="btn-hero-secondary">
                        How It Works <i class="bi bi-arrow-right ms-1"></i>
                    </a>
                </div>
                <div class="hero-stats">
                    <div class="hero-stat">
                        <span class="hero-stat-number"><?= $total_available ?>+</span>
                        <span class="hero-stat-label">Pets Available</span>
                    </div>
                    <div class="hero-stat">
                        <span class="hero-stat-number"><?= $total_adopted ?>+</span>
                        <span class="hero-stat-label">Happy Adoptions</span>
                    </div>
                    <div class="hero-stat">
                        <span class="hero-stat-number"><?= $total_users ?>+</span>
                        <span class="hero-stat-label">Registered Adopters</span>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="hero-image-area">
                    <div class="hero-blob">🐾</div>
                    <div class="hero-floating-card card-1">
                        <div class="fc-icon" style="background:#FFF3E0;">🐱</div>
                        <div>
                            <div style="font-size:0.75rem;color:var(--text-muted);font-weight:600;">Just Added</div>
                            <div style="color:var(--text-dark);font-size:0.85rem;">New Arrivals!</div>
                        </div>
                    </div>
                    <div class="hero-floating-card card-2">
                        <div class="fc-icon" style="background:#E8F5E9;">✅</div>
                        <div>
                            <div style="font-size:0.75rem;color:var(--text-muted);font-weight:600;">Application</div>
                            <div style="color:#4CAF50;font-size:0.85rem;">Approved!</div>
                        </div>
                    </div>
                    <div class="hero-floating-card card-3">
                        <div class="fc-icon" style="background:#FCE4EC;">💕</div>
                        <div>
                            <div style="font-size:0.75rem;color:var(--text-muted);font-weight:600;">Bonded Pair</div>
                            <div style="color:var(--soft-pink);font-size:0.85rem;">2 pets, 1 home</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ══════════════════════════════════════
     FEATURED PETS — DYNAMIC
══════════════════════════════════════ -->
<section class="pets-section" id="pets">
    <div class="container">
        <div class="text-center mb-5 fade-in-up">
            <div class="section-label">🐾 Meet Our Pets</div>
            <h2 class="section-title">Waiting For a Home</h2>
            <p class="section-subtitle">Each one of them has a unique story. Could you be the chapter they've been waiting for?</p>
        </div>

        <?php if (!empty($pets)): ?>
        <div class="row g-4">
            <?php foreach ($pets as $pet):
                $is_bonded    = !empty($pet['bonded_with']);
                $partner_name = $is_bonded && isset($bonded_map[$pet['bonded_with']])
                                ? $bonded_map[$pet['bonded_with']] : null;
                $display_name = $is_bonded && $partner_name
                                ? htmlspecialchars($pet['name']) . ' & ' . htmlspecialchars($partner_name)
                                : htmlspecialchars($pet['name']);

                // ── FIXED: use image_path directly (already stored as "uploads/pets/filename.jpg") ──
                $has_image    = !empty($pet['image_path']) && file_exists($pet['image_path']);
            ?>
            <div class="col-sm-6 col-lg-3 fade-in-up">
                <div class="pet-card">
                    <div class="pet-image-wrap">
                        <?php if ($has_image): ?>
                            <img src="<?= htmlspecialchars($pet['image_path']) ?>"
                                 alt="<?= $display_name ?>">
                        <?php else: ?>
                            <?= petEmoji($pet['species']) ?>
                        <?php endif; ?>

                        <span class="pet-status-badge">
                            <i class="bi bi-circle-fill me-1" style="font-size:0.45rem;"></i>Available
                        </span>

                        <?php if ($is_bonded): ?>
                            <span class="pet-bonded-badge">💕 Bonded Pair</span>
                        <?php endif; ?>
                    </div>
                    <div class="pet-card-body">
                        <div class="pet-name"><?= $display_name ?></div>
                        <div class="pet-breed">
                            <?= htmlspecialchars(ucfirst($pet['species'])) ?>
                            <?= !empty($pet['breed']) ? '· ' . htmlspecialchars($pet['breed']) : '' ?>
                        </div>
                        <div class="pet-meta">
                            <span class="pet-tag">
                                <i class="bi bi-<?= strtolower($pet['gender']) === 'male' ? 'gender-male' : 'gender-female' ?>"></i>
                                <?= ucfirst($pet['gender']) ?>
                            </span>
                            <span class="pet-tag">
                                <i class="bi bi-calendar3"></i>
                                <?= formatAge($pet['age_years'], $pet['age_months']) ?>
                            </span>
                            <?php if ($is_bonded): ?>
                            <span class="pet-tag"><i class="bi bi-people"></i> Pair</span>
                            <?php endif; ?>
                        </div>
                        <a href="auth/login.php" class="btn-adopt">
                            <?= $is_bonded ? 'Adopt Us 🐾' : 'Adopt Me 🐾' ?>
                        </a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="text-center mt-5 fade-in-up">
            <a href="auth/login.php" class="btn-view-all">View All Available Pets <i class="bi bi-arrow-right ms-1"></i></a>
        </div>

        <?php else: ?>
        <!-- Empty state — no pets in DB yet -->
        <div class="no-pets fade-in-up">
            <div class="no-pets-icon">🐾</div>
            <h4>No pets available yet</h4>
            <p>Check back soon — our shelter is always welcoming new furry friends!</p>
        </div>
        <?php endif; ?>

    </div>
</section>

<!-- ══════════════════════════════════════
     HOW IT WORKS
══════════════════════════════════════ -->
<section class="hiw-section" id="how-it-works">
    <div class="container">
        <div class="text-center mb-5 fade-in-up">
            <div class="section-label">📋 The Process</div>
            <h2 class="section-title">How It Works</h2>
            <p class="section-subtitle">Adopting through PawPal is simple, transparent, and designed with both you and the animals in mind.</p>
        </div>
        <div class="row g-4 justify-content-center">
            <div class="col-6 col-md-4 col-lg-2 fade-in-up">
                <div class="hiw-step">
                    <div class="hiw-step-icon" style="background:#FFF3E0;">🖊️<span class="hiw-step-number">1</span></div>
                    <h5>Create Account</h5>
                    <p>Register and set up your adopter profile in minutes.</p>
                </div>
            </div>
            <div class="col-6 col-md-4 col-lg-2 fade-in-up">
                <div class="hiw-step">
                    <div class="hiw-step-icon" style="background:#FCE4EC;">🔍<span class="hiw-step-number">2</span></div>
                    <h5>Browse Pets</h5>
                    <p>Explore pets available for adoption and find your match.</p>
                </div>
            </div>
            <div class="col-6 col-md-4 col-lg-2 fade-in-up">
                <div class="hiw-step">
                    <div class="hiw-step-icon" style="background:#E8F5E9;">📝<span class="hiw-step-number">3</span></div>
                    <h5>Apply Online</h5>
                    <p>Submit your adoption application with your details.</p>
                </div>
            </div>
            <div class="col-6 col-md-4 col-lg-2 fade-in-up">
                <div class="hiw-step">
                    <div class="hiw-step-icon" style="background:#E3F2FD;">🏠<span class="hiw-step-number">4</span></div>
                    <h5>Get Screened</h5>
                    <p>Our team reviews your application and meets you in person.</p>
                </div>
            </div>
            <div class="col-6 col-md-4 col-lg-2 fade-in-up">
                <div class="hiw-step">
                    <div class="hiw-step-icon" style="background:#FFF8E1;">✅<span class="hiw-step-number">5</span></div>
                    <h5>Get Approved</h5>
                    <p>Receive approval and prepare to welcome your new pet!</p>
                </div>
            </div>
            <div class="col-6 col-md-4 col-lg-2 fade-in-up">
                <div class="hiw-step">
                    <div class="hiw-step-icon" style="background:#F3E5F5;">💕<span class="hiw-step-number">6</span></div>
                    <h5>Track Care</h5>
                    <p>Monitor your pet's health records and care history online.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ══════════════════════════════════════
     ABOUT
══════════════════════════════════════ -->
<section class="about-section" id="about">
    <div class="container">
        <div class="row align-items-center g-5">
            <div class="col-lg-5 fade-in-up">
                <div class="about-image-stack">
                    <div class="about-blob-main">🐾</div>
                    <div class="about-blob-secondary">🐶</div>
                    <div class="about-stats-card">
                        <div style="font-family:'Baloo 2',cursive;font-size:1.6rem;font-weight:800;color:var(--warm-orange);">5+ yrs</div>
                        <div style="font-size:0.8rem;font-weight:600;color:var(--text-muted);">Serving animals &<br>families since 2019</div>
                    </div>
                </div>
            </div>
            <div class="col-lg-7 fade-in-up about-content">
                <div class="section-label">🐾 About PawPal</div>
                <h2>A Shelter Built on <span style="color:var(--warm-orange);">Love & Trust</span></h2>
                <p>PawPal is more than just an adoption platform — we are a community of animal lovers dedicated to giving every pet a second chance at a happy life. Our shelter has been caring for abandoned, rescued, and surrendered animals since 2019.</p>
                <p>We believe that pet adoption should be a thoughtful, responsible, and joyful experience. That's why we've built a transparent system that supports both adopters and our animals — from the first application all the way through their lifetime of care.</p>
                <p>Every pet in our care receives proper medical attention, socialization, and love while they wait for their forever family. And once adopted, we continue supporting owners through our care tracking system.</p>
                <div class="mt-3">
                    <span class="about-pill"><i class="bi bi-heart-fill"></i> Animal Welfare First</span>
                    <span class="about-pill"><i class="bi bi-shield-check-fill"></i> Responsible Adoption</span>
                    <span class="about-pill"><i class="bi bi-clipboard2-pulse-fill"></i> Lifetime Care Support</span>
                    <span class="about-pill"><i class="bi bi-people-fill"></i> Community Driven</span>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ══════════════════════════════════════
     FOOTER
══════════════════════════════════════ -->
<footer>
    <div class="container">
        <div class="row g-4">
            <div class="col-lg-4">
                <div class="brand-footer">
                    <?php if (file_exists('assets/images/logo.png')): ?>
                        <img src="assets/images/logo.png" alt="PawPal Logo">
                    <?php else: ?>
                        🐾 PawPal
                    <?php endif; ?>
                </div>
                <p class="tagline">Connecting loving homes with animals in need. Every adoption changes two lives — the pet's and yours.</p>
                <div class="mt-3">
                    <a href="#" class="social-link"><i class="bi bi-facebook"></i></a>
                    <a href="#" class="social-link"><i class="bi bi-instagram"></i></a>
                    <a href="#" class="social-link"><i class="bi bi-twitter-x"></i></a>
                    <a href="#" class="social-link"><i class="bi bi-envelope-fill"></i></a>
                </div>
            </div>
            <div class="col-sm-4 col-lg-2">
                <h6>Quick Links</h6>
                <ul>
                    <li><a href="index.php">Home</a></li>
                    <li><a href="#pets">Browse Pets</a></li>
                    <li><a href="#how-it-works">How It Works</a></li>
                    <li><a href="#about">About Us</a></li>
                </ul>
            </div>
            <div class="col-sm-4 col-lg-2">
                <h6>Adopters</h6>
                <ul>
                    <li><a href="auth/register.php">Register</a></li>
                    <li><a href="auth/login.php">Log In</a></li>
                    <li><a href="adopter/applications.php">My Applications</a></li>
                    <li><a href="adopter/care_history.php">Care History</a></li>
                </ul>
            </div>
            <div class="col-sm-4 col-lg-4">
                <h6>Contact Us</h6>
                <ul>
                    <li><a href="#"><i class="bi bi-geo-alt-fill me-2" style="color:var(--soft-orange);"></i>123 Shelter St., Manila, PH</a></li>
                    <li><a href="#"><i class="bi bi-telephone-fill me-2" style="color:var(--soft-orange);"></i>+63 912 345 6789</a></li>
                    <li><a href="#"><i class="bi bi-envelope-fill me-2" style="color:var(--soft-orange);"></i>hello@pawpal.ph</a></li>
                    <li><a href="#"><i class="bi bi-clock-fill me-2" style="color:var(--soft-orange);"></i>Mon–Sat, 8AM – 5PM</a></li>
                </ul>
            </div>
        </div>
        <hr class="footer-divider">
        <div class="footer-bottom">
            <span>© <?= date('Y') ?> PawPal. All rights reserved.</span>
            <span>Made with 🧡 for animals everywhere</span>
        </div>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry, i) => {
            if (entry.isIntersecting) {
                setTimeout(() => entry.target.classList.add('visible'), i * 120);
                observer.unobserve(entry.target);
            }
        });
    }, { threshold: 0.1 });
    document.querySelectorAll('.fade-in-up').forEach(el => observer.observe(el));

    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function(e) {
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                e.preventDefault();
                target.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        });
    });
</script>
</body>
</html>
