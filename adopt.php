<?php
session_start();
require_once 'config/database.php';

// Auth check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'adopter') {
    header('Location: auth/login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Get pet_id from URL
$pet_id = isset($_GET['pet_id']) && is_numeric($_GET['pet_id']) ? (int)$_GET['pet_id'] : 0;
if (!$pet_id) { header('Location: dashboard.php'); exit; }

// Fetch pet details
$stmt = mysqli_prepare($conn, "SELECT * FROM pets WHERE pet_id = ? AND status = 'available'");
mysqli_stmt_bind_param($stmt, 'i', $pet_id);
mysqli_stmt_execute($stmt);
$pet = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
if (!$pet) { header('Location: dashboard.php'); exit; }

// Check existing application
$stmt2 = mysqli_prepare($conn, "SELECT application_id, status FROM adoption_applications WHERE user_id = ? AND pet_id = ?");
mysqli_stmt_bind_param($stmt2, 'ii', $user_id, $pet_id);
mysqli_stmt_execute($stmt2);
$existing_app = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt2));

// Fetch similar pets (same species, different pet)
$similar_pets_result = mysqli_prepare($conn, "SELECT * FROM pets WHERE status = 'available' AND species = ? AND pet_id != ? ORDER BY created_at DESC LIMIT 3");
mysqli_stmt_bind_param($similar_pets_result, 'si', $pet['species'], $pet_id);
mysqli_stmt_execute($similar_pets_result);
$similar_pets = mysqli_fetch_all(mysqli_stmt_get_result($similar_pets_result), MYSQLI_ASSOC);

// Handle form submission
$form_error   = '';
$form_success = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_application'])) {
    $contact_number   = trim($_POST['contact_number'] ?? '');
    $address          = trim($_POST['address'] ?? '');
    $occupation       = trim($_POST['occupation'] ?? '');
    $monthly_income   = trim($_POST['monthly_income'] ?? '');
    $living_situation = $_POST['living_situation'] ?? '';
    $ownership_status = $_POST['ownership_status'] ?? '';
    $has_existing_pets= isset($_POST['has_existing_pets']) ? 1 : 0;
    $has_children     = isset($_POST['has_children']) ? 1 : 0;
    $reason           = trim($_POST['reason'] ?? '');

    if (empty($contact_number) || empty($address) || empty($occupation) ||
        empty($monthly_income) || empty($living_situation) || empty($ownership_status) || empty($reason)) {
        $form_error = 'Please fill in all required fields.';
    } elseif (!is_numeric($monthly_income) || $monthly_income < 0) {
        $form_error = 'Please enter a valid monthly income.';
    } else {
        $ins = mysqli_prepare($conn,
            "INSERT INTO adoption_applications
             (user_id, pet_id, contact_number, address, occupation, monthly_income, living_situation, ownership_status, has_existing_pets, has_children, reason)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        mysqli_stmt_bind_param($ins, 'iisssdssiis',
            $user_id, $pet_id, $contact_number, $address, $occupation,
            $monthly_income, $living_situation, $ownership_status,
            $has_existing_pets, $has_children, $reason
        );
        $form_success = mysqli_stmt_execute($ins);
        if (!$form_success) $form_error = 'Could not submit application. Please try again.';
    }
}

$form_open = ($form_error !== '') || $form_success;

function petEmoji($species) {
    $map = ['Dog'=>'🐶','Cat'=>'🐱','Bird'=>'🐦','Rabbit'=>'🐰','Other'=>'🐾'];
    return $map[$species] ?? '🐾';
}
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
    <title><?= htmlspecialchars($pet['name']) ?> — PawPal</title>
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
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Nunito', sans-serif;
            background: var(--cream);
            color: var(--text-dark);
            min-height: 100vh;
            overflow-x: hidden;
            animation: fadeSlideIn 0.4s cubic-bezier(0.25, 0.46, 0.45, 0.94) both;
        }
        @keyframes fadeSlideIn {
            from { opacity: 0; transform: translateY(14px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        h1,h2,h3,h4,h5 { font-family: 'Baloo 2', cursive; }

        /* ── Navbar ── */
        .navbar {
            background: #fff;
            border-bottom: 1.5px solid #F0E6DE;
            padding: 0.75rem 2rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 100;
        }
        .brand { font-family: 'Baloo 2', cursive; font-size: 1.6rem; font-weight: 800; color: var(--orange); text-decoration: none; display: flex; align-items: center; gap: 0.4rem; }
        .brand img { height: 50px; width: auto; }
        .btn-back { display: inline-flex; align-items: center; gap: 0.4rem; font-size: 0.88rem; font-weight: 700; color: var(--text-muted); text-decoration: none; padding: 0.45rem 1rem; border-radius: 8px; border: 1.5px solid #F0E6DE; background: #fff; transition: all 0.18s; }
        .btn-back:hover { border-color: var(--orange); color: var(--orange); background: #FFF0E8; }

        /* ── Split layout ── */
        .page-wrap {
            display: flex;
            align-items: stretch;
            min-height: calc(100vh - 65px);
        }

        /* ── LEFT panel ── */
        .panel-left {
            flex: 1;
            display: flex;
            flex-direction: column;
            transition: flex 0.55s cubic-bezier(0.25, 0.46, 0.45, 0.94);
            min-width: 0;
        }

        /* ── Pet Photo ── */
        .pet-photo-wrap {
            width: 100%;
            height: 240px;
            background: linear-gradient(135deg, #FFF3E0, #FCE4EC);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 9rem;
            position: relative;
            overflow: hidden;
            flex-shrink: 0;
            transition: height 0.55s cubic-bezier(0.25, 0.46, 0.45, 0.94);
        }
        .page-wrap.split .pet-photo-wrap { height: 220px; }
        .pet-photo-wrap img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            object-position: center top;
            transition: transform 0.6s ease;
        }
        .pet-photo-wrap:hover img { transform: scale(1.03); }

        /* Gradient overlay at bottom of photo */
        .pet-photo-wrap::after {
            content: '';
            position: absolute;
            bottom: 0; left: 0; right: 0;
            height: 80px;
            background: linear-gradient(to top, rgba(255,248,240,0.85), transparent);
            pointer-events: none;
        }

        .pet-status-badge {
            position: absolute; top: 16px; right: 16px;
            background: #fff; border-radius: 50px; padding: 0.3rem 1rem;
            font-size: 0.78rem; font-weight: 800; color: #2E7D32;
            box-shadow: 0 2px 10px rgba(0,0,0,0.12);
            z-index: 2;
            display: flex; align-items: center; gap: 0.4rem;
        }
        .status-dot {
            width: 7px; height: 7px; border-radius: 50%; background: #4CAF50;
            animation: pulse-dot 2s infinite;
        }
        @keyframes pulse-dot {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.6; transform: scale(1.3); }
        }

        /* Share button on photo */
        .share-btn {
            position: absolute; top: 16px; left: 16px;
            background: rgba(255,255,255,0.92);
            border: none; border-radius: 50px;
            padding: 0.35rem 0.9rem;
            font-size: 0.78rem; font-weight: 700; color: var(--text-muted);
            cursor: pointer; display: flex; align-items: center; gap: 0.4rem;
            transition: all 0.2s; z-index: 2;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .share-btn:hover { background: #fff; color: var(--orange); }
        .share-btn.copied { color: #2E7D32; }

        .pet-detail-body {
            padding: 1.8rem 2.5rem;
            flex: 1;
            overflow-y: auto;
            transition: padding 0.4s ease;
        }
        .page-wrap.split .pet-detail-body { padding: 1.4rem 2rem; }

        /* ── Pet header ── */
        .pet-header { display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 0.3rem; }
        .pet-detail-name { font-size: 2rem; font-weight: 800; color: var(--text-dark); transition: font-size 0.4s ease; }
        .page-wrap.split .pet-detail-name { font-size: 1.7rem; }
        .pet-detail-breed { font-size: 1rem; color: var(--brown-light); font-weight: 600; margin-bottom: 1.2rem; }

        /* ── Tags ── */
        .pet-tags { display: flex; gap: 0.5rem; flex-wrap: wrap; margin-bottom: 1.4rem; }
        .pet-tag { background: var(--cream); border-radius: 50px; padding: 0.35rem 1rem; font-size: 0.82rem; font-weight: 700; color: var(--text-muted); display: flex; align-items: center; gap: 0.4rem; border: 1.5px solid #F0E6DE; transition: all 0.18s; }
        .pet-tag:hover { border-color: var(--orange); color: var(--orange); background: #FFF0E8; }
        .pet-tag.highlight { background: #FFF0E8; border-color: #FFCCBC; color: var(--orange); }

        /* ── Adoption Steps ── */
        .steps-bar {
            background: #fff;
            border: 1.5px solid #F0E6DE;
            border-radius: 14px;
            padding: 1rem 1.4rem;
            margin-bottom: 1.4rem;
            display: flex;
            align-items: center;
            gap: 0;
        }
        .step-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            flex: 1;
        }
        .step-num {
            width: 28px; height: 28px;
            border-radius: 50%;
            background: #FFF0E8;
            color: var(--orange);
            font-family: 'Baloo 2', cursive;
            font-weight: 800;
            font-size: 0.85rem;
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0;
            border: 1.5px solid #FFCCBC;
        }
        .step-text { font-size: 0.75rem; font-weight: 700; color: var(--text-muted); line-height: 1.2; }
        .step-text span { display: block; font-size: 0.65rem; font-weight: 600; color: var(--brown-light); }
        .step-arrow { color: #E0D0C8; font-size: 0.75rem; margin: 0 0.5rem; flex-shrink: 0; }

        /* ── Description ── */
        .detail-section { margin-bottom: 1.4rem; }
        .detail-label { font-size: 0.72rem; font-weight: 800; text-transform: uppercase; letter-spacing: 1px; color: var(--orange); margin-bottom: 0.5rem; }
        .detail-text { font-size: 0.92rem; color: var(--text-muted); line-height: 1.7; font-weight: 600; }
        .no-description { font-style: italic; color: #C4AFA8; font-size: 0.88rem; }
        .divider { border: none; border-top: 1.5px solid #F0E6DE; margin: 1.4rem 0; }

        /* ── Apply box ── */
        .apply-box {
            background: linear-gradient(135deg, #FFF3E0, #FFF8F0);
            border: 1.5px solid #FFCCBC;
            border-radius: 16px;
            padding: 1.3rem 1.8rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            flex-wrap: wrap;
            transition: opacity 0.25s ease, transform 0.25s ease, height 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        .apply-box::before {
            content: '🐾';
            position: absolute;
            right: 180px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 3.5rem;
            opacity: 0.07;
            pointer-events: none;
        }
        .apply-box.hidden { opacity: 0; pointer-events: none; transform: translateY(-6px); height: 0; padding: 0; margin: 0; overflow: hidden; border: none; }
        .apply-box-text h4 { font-size: 1rem; font-weight: 800; color: var(--text-dark); margin-bottom: 0.2rem; }
        .apply-box-text p  { font-size: 0.82rem; color: var(--text-muted); font-weight: 600; }
        .apply-box-cta { display: flex; flex-direction: column; align-items: flex-end; gap: 0.4rem; flex-shrink: 0; }
        .apply-reassurance { font-size: 0.7rem; color: var(--brown-light); font-weight: 600; text-align: right; }
        .apply-reassurance i { color: #4CAF50; }

        .btn-apply { background: var(--orange); color: #fff; border: none; border-radius: 50px; padding: 0.75rem 2rem; font-weight: 800; font-size: 0.95rem; font-family: 'Baloo 2', cursive; display: inline-flex; align-items: center; gap: 0.5rem; transition: all 0.2s; box-shadow: 0 6px 18px rgba(255,112,67,0.3); white-space: nowrap; cursor: pointer; text-decoration: none; }
        .btn-apply:hover { background: var(--orange-dark); color: #fff; transform: translateY(-2px); box-shadow: 0 8px 22px rgba(255,112,67,0.4); }

        .already-applied { display: inline-flex; align-items: center; gap: 0.5rem; background: #E8F5E9; color: #2E7D32; font-weight: 800; font-size: 0.88rem; padding: 0.7rem 1.4rem; border-radius: 50px; }
        .already-applied.pending  { background: #FFF8E1; color: #F57F17; }
        .already-applied.rejected { background: #FFEBEE; color: #C62828; }

        /* ── Similar pets ── */
        .similar-section { margin-top: 1.8rem; }
        .similar-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 0.8rem; margin-top: 0.8rem; }
        .similar-card {
            background: #fff;
            border: 1.5px solid #F0E6DE;
            border-radius: 12px;
            overflow: hidden;
            text-decoration: none;
            color: inherit;
            transition: all 0.2s;
            display: block;
        }
        .similar-card:hover { box-shadow: 0 6px 20px rgba(255,112,67,0.13); transform: translateY(-2px); }
        .similar-img {
            width: 100%; height: 90px;
            object-fit: cover;
            object-position: center top;
            background: #F5EDE7;
            display: flex; align-items: center; justify-content: center;
            font-size: 2.5rem;
            overflow: hidden;
        }
        .similar-img img { width: 100%; height: 90px; object-fit: cover; object-position: center top; }
        .similar-body { padding: 0.6rem 0.8rem; }
        .similar-name { font-size: 0.88rem; font-weight: 800; color: var(--text-dark); }
        .similar-meta { font-size: 0.72rem; color: var(--brown-light); font-weight: 600; }

        /* ── RIGHT panel (form) ── */
        .panel-right {
            width: 0;
            overflow: hidden;
            transition: width 0.55s cubic-bezier(0.25, 0.46, 0.45, 0.94);
            background: #fff;
            border-left: 0px solid #F0E6DE;
            flex-shrink: 0;
        }
        .page-wrap.split .panel-right {
            width: 50%;
            border-left-width: 1.5px;
        }

        .form-inner {
            width: 50vw;
            padding: 2rem 2.5rem;
            opacity: 0;
            transform: translateX(24px);
            transition: opacity 0.35s ease 0.3s, transform 0.35s ease 0.3s;
            overflow-y: auto;
            height: 100%;
        }
        .page-wrap.split .form-inner {
            opacity: 1;
            transform: translateX(0);
        }

        /* ── Form styles ── */
        .form-title { font-size: 1.3rem; font-weight: 800; color: var(--text-dark); margin-bottom: 0.25rem; display: flex; align-items: center; gap: 0.5rem; }
        .form-subtitle { font-size: 0.82rem; color: var(--text-muted); font-weight: 600; margin-bottom: 1.5rem; }
        .section-divider { font-size: 0.7rem; font-weight: 800; text-transform: uppercase; letter-spacing: 1.2px; color: var(--orange); margin: 1.3rem 0 0.8rem; display: flex; align-items: center; gap: 0.6rem; }
        .section-divider::after { content: ''; flex: 1; height: 1.5px; background: #F0E6DE; }
        .lbl { display: block; font-size: 0.72rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: var(--brown); margin-bottom: 0.3rem; }
        .ipt { width: 100%; border: 2px solid #E8D8CC; border-radius: 10px; padding: 0.6rem 0.85rem; font-family: 'Nunito', sans-serif; font-size: 0.88rem; color: var(--text-dark); background: #fff; outline: none; transition: border-color 0.2s; }
        .ipt:focus { border-color: var(--orange); box-shadow: 0 0 0 3px rgba(255,112,67,0.1); }
        .mb { margin-bottom: 0.9rem; }
        .two-col { display: grid; grid-template-columns: 1fr 1fr; gap: 0.9rem; }
        .check-group { display: flex; gap: 0.75rem; flex-wrap: wrap; }
        .check-option { flex: 1; min-width: 120px; border: 2px solid #E8D8CC; border-radius: 10px; padding: 0.65rem 0.9rem; display: flex; align-items: center; gap: 0.5rem; cursor: pointer; transition: all 0.18s; font-size: 0.85rem; font-weight: 700; color: var(--text-muted); }
        .check-option input { display: none; }
        .check-option:has(input:checked) { border-color: var(--orange); background: #FFF0E8; color: var(--orange); }
        .check-option .check-icon { font-size: 1.1rem; }
        .radio-group { display: flex; gap: 0.6rem; flex-wrap: wrap; }
        .radio-option { flex: 1; min-width: 100px; border: 2px solid #E8D8CC; border-radius: 10px; padding: 0.6rem 0.9rem; display: flex; align-items: center; gap: 0.45rem; cursor: pointer; transition: all 0.18s; font-size: 0.85rem; font-weight: 700; color: var(--text-muted); }
        .radio-option input { display: none; }
        .radio-option:has(input:checked) { border-color: var(--orange); background: #FFF0E8; color: var(--orange); }
        .radio-dot { width: 14px; height: 14px; border-radius: 50%; border: 2px solid #E8D8CC; flex-shrink: 0; transition: all 0.18s; }
        .radio-option:has(input:checked) .radio-dot { border-color: var(--orange); background: var(--orange); }
        .btn-submit { width: 100%; background: var(--orange); color: #fff; border: none; border-radius: 50px; padding: 0.85rem; font-weight: 800; font-size: 0.95rem; font-family: 'Baloo 2', cursive; cursor: pointer; box-shadow: 0 6px 20px rgba(255,112,67,0.3); transition: all 0.2s; margin-top: 0.5rem; }
        .btn-submit:hover { background: var(--orange-dark); transform: translateY(-1px); }
        .alert-error { background: #FFF0ED; border: 1.5px solid #FFCCBC; border-radius: 10px; color: #BF360C; padding: 0.75rem 1rem; font-size: 0.85rem; font-weight: 600; margin-bottom: 1.1rem; display: flex; gap: 0.5rem; align-items: center; }

        /* ── Success ── */
        .success-wrap { text-align: center; padding: 3rem 1.5rem; display: flex; flex-direction: column; align-items: center; justify-content: center; min-height: 100%; }
        .success-icon { font-size: 4.5rem; margin-bottom: 1rem; display: block; animation: popIn 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275); }
        @keyframes popIn { 0% { transform: scale(0); opacity: 0; } 100% { transform: scale(1); opacity: 1; } }
        .success-title { font-size: 1.6rem; font-weight: 800; color: var(--text-dark); margin-bottom: 0.5rem; }
        .success-text  { font-size: 0.9rem; color: var(--text-muted); font-weight: 600; line-height: 1.6; max-width: 320px; margin: 0 auto 1.8rem; }
        .btn-dashboard { display: inline-flex; align-items: center; gap: 0.5rem; background: var(--orange); color: #fff; border-radius: 50px; padding: 0.8rem 2rem; font-weight: 800; font-size: 0.9rem; font-family: 'Baloo 2', cursive; text-decoration: none; box-shadow: 0 6px 18px rgba(255,112,67,0.3); transition: all 0.2s; }
        .btn-dashboard:hover { background: var(--orange-dark); color: #fff; transform: translateY(-1px); }

        /* ── Mobile ── */
        @media (max-width: 768px) {
            .page-wrap { flex-direction: column; }
            .panel-right { width: 100% !important; border-left: none !important; border-top: 1.5px solid #F0E6DE; }
            .form-inner { width: 100%; }
            .pet-photo-wrap, .page-wrap.split .pet-photo-wrap { height: 220px; font-size: 6rem; }
            .pet-detail-body, .page-wrap.split .pet-detail-body { padding: 1.4rem 1.2rem; }
            .form-inner { padding: 1.4rem 1.2rem; }
            .similar-grid { grid-template-columns: repeat(2, 1fr); }
            .steps-bar { gap: 0.2rem; }
            .step-arrow { margin: 0 0.2rem; }
        }
        @media (max-width: 480px) {
            .similar-grid { grid-template-columns: 1fr; }
            .steps-bar { flex-wrap: wrap; gap: 0.5rem; }
        }
    </style>
</head>
<body>

<!-- ── Navbar ── -->
<nav class="navbar">
    <a href="dashboard.php" class="brand">
        <?php if (file_exists('assets/images/logo.png')): ?>
            <img src="assets/images/logo.png" alt="PawPal">
        <?php else: ?>
            🐾 PawPal
        <?php endif; ?>
    </a>
    <a href="javascript:history.back()" class="btn-back"><i class="bi bi-arrow-left"></i> Browse Pets</a>
</nav>

<!-- ── Page ── -->
<div class="page-wrap <?= $form_open ? 'split' : '' ?>" id="page-wrap">

    <!-- ══ LEFT: Pet Details ══ -->
    <div class="panel-left">

        <div class="pet-photo-wrap">
            <?php if (!empty($pet['image_path']) && file_exists($pet['image_path'])): ?>
                <img src="<?= htmlspecialchars($pet['image_path']) ?>" alt="<?= htmlspecialchars($pet['name']) ?>" loading="lazy">
            <?php else: ?>
                <?= petEmoji($pet['species']) ?>
            <?php endif; ?>

            <span class="pet-status-badge">
                <span class="status-dot"></span> Available
            </span>

            <button class="share-btn" id="share-btn" onclick="sharePet()">
                <i class="bi bi-share"></i> Share
            </button>
        </div>

        <div class="pet-detail-body">

            <!-- Pet name -->
            <div class="pet-header">
                <div>
                    <div class="pet-detail-name"><?= htmlspecialchars($pet['name']) ?></div>
                    <div class="pet-detail-breed">
                        <?= htmlspecialchars($pet['species']) ?>
                        <?= !empty($pet['breed']) ? ' · ' . htmlspecialchars($pet['breed']) : '' ?>
                    </div>
                </div>
            </div>

            <!-- Tags -->
            <div class="pet-tags">
                <span class="pet-tag">
                    <i class="bi bi-<?= strtolower($pet['gender']) === 'male' ? 'gender-male' : 'gender-female' ?>"></i>
                    <?= ucfirst($pet['gender']) ?>
                </span>
                <span class="pet-tag">
                    <i class="bi bi-calendar3"></i>
                    <?= formatAge($pet['age_years'], $pet['age_months']) ?>
                </span>
                <span class="pet-tag">
                    <i class="bi bi-tag"></i>
                    <?= htmlspecialchars($pet['species']) ?>
                </span>
                <?php if (!empty($pet['is_vaccinated'])): ?>
                <span class="pet-tag highlight">
                    <i class="bi bi-shield-check"></i> Vaccinated
                </span>
                <?php endif; ?>
                <?php if (!empty($pet['is_neutered'])): ?>
                <span class="pet-tag highlight">
                    <i class="bi bi-scissors"></i> Neutered/Spayed
                </span>
                <?php endif; ?>
                <?php if (!empty($pet['is_microchipped'])): ?>
                <span class="pet-tag highlight">
                    <i class="bi bi-cpu"></i> Microchipped
                </span>
                <?php endif; ?>
                <?php if (!empty($pet['bonded_with'])): ?>
                <span class="pet-tag" style="background:#FCE4EC;border-color:#F8BBD9;color:#C2185B">
                    💕 Bonded Pair
                </span>
                <?php endif; ?>
            </div>

            <!-- Adoption Steps -->
            <div class="steps-bar">
                <div class="step-item">
                    <div class="step-num">①</div>
                    <div class="step-text">Apply <span>Fill form</span></div>
                </div>
                <span class="step-arrow"><i class="bi bi-chevron-right"></i></span>
                <div class="step-item">
                    <div class="step-num">②</div>
                    <div class="step-text">Review <span>We assess</span></div>
                </div>
                <span class="step-arrow"><i class="bi bi-chevron-right"></i></span>
                <div class="step-item">
                    <div class="step-num">③</div>
                    <div class="step-text">Meet & Greet <span>Visit us</span></div>
                </div>
                <span class="step-arrow"><i class="bi bi-chevron-right"></i></span>
                <div class="step-item">
                    <div class="step-num">④</div>
                    <div class="step-text">Adopt! <span>Take home</span></div>
                </div>
            </div>

            <!-- About -->
            <div class="detail-section">
                <div class="detail-label">About <?= htmlspecialchars($pet['name']) ?></div>
                <?php if (!empty($pet['description'])): ?>
                    <div class="detail-text"><?= nl2br(htmlspecialchars($pet['description'])) ?></div>
                <?php else: ?>
                    <div class="detail-text no-description">No description provided yet — come visit us to learn more about <?= htmlspecialchars($pet['name']) ?>!</div>
                <?php endif; ?>
            </div>

            <hr class="divider">

            <!-- Apply box / status -->
            <?php if ($existing_app && in_array($existing_app['status'], ['approved','pending','rejected'])): ?>
            <div class="apply-box">
                <div class="apply-box-text">
                    <h4>Application Status</h4>
                    <p>You've already submitted an application for <?= htmlspecialchars($pet['name']) ?>.</p>
                </div>
                <?php if ($existing_app['status'] === 'approved'): ?>
                    <span class="already-applied"><i class="bi bi-house-heart-fill"></i> Adoption Approved!</span>
                <?php elseif ($existing_app['status'] === 'pending'): ?>
                    <span class="already-applied pending"><i class="bi bi-hourglass-split"></i> Application Pending</span>
                <?php else: ?>
                    <span class="already-applied rejected"><i class="bi bi-x-circle-fill"></i> Application Rejected</span>
                <?php endif; ?>
            </div>

            <?php elseif (!$form_success): ?>
            <div class="apply-box <?= $form_open ? 'hidden' : '' ?>" id="apply-box">
                <div class="apply-box-text">
                    <h4>Ready to adopt <?= htmlspecialchars($pet['name']) ?>?</h4>
                    <p>Fill out a short application and we'll review it soon.</p>
                </div>
                <div class="apply-box-cta">
                    <button type="button" class="btn-apply" onclick="showForm()">
                        <i class="bi bi-heart-fill"></i> Apply Now
                    </button>
                    <div class="apply-reassurance">
                        <i class="bi bi-check-circle-fill"></i> Free to apply &nbsp;·&nbsp;
                        <i class="bi bi-check-circle-fill"></i> No commitment &nbsp;·&nbsp;
                        <i class="bi bi-check-circle-fill"></i> Reply within 48 hrs
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Similar Pets -->
            <?php if (!empty($similar_pets)): ?>
            <div class="similar-section">
                <hr class="divider">
                <div class="detail-label">More <?= htmlspecialchars($pet['species']) ?>s looking for a home</div>
                <div class="similar-grid">
                    <?php foreach ($similar_pets as $sp): ?>
                    <a href="adopt.php?pet_id=<?= $sp['pet_id'] ?>" class="similar-card">
                        <div class="similar-img">
                            <?php if (!empty($sp['image_path'])): ?>
                                <img src="<?= htmlspecialchars($sp['image_path']) ?>" alt="<?= htmlspecialchars($sp['name']) ?>" loading="lazy">
                            <?php else: ?>
                                <?= petEmoji($sp['species']) ?>
                            <?php endif; ?>
                        </div>
                        <div class="similar-body">
                            <div class="similar-name"><?= htmlspecialchars($sp['name']) ?></div>
                            <div class="similar-meta"><?= htmlspecialchars($sp['breed'] ?? 'Mixed') ?> · <?= formatAge($sp['age_years'], $sp['age_months']) ?></div>
                        </div>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

        </div><!-- /pet-detail-body -->
    </div><!-- /panel-left -->

    <!-- ══ RIGHT: Form panel ══ -->
    <div class="panel-right" id="panel-right">
        <div class="form-inner" id="form-inner">

            <?php if ($form_success): ?>
            <!-- ── Success ── -->
            <div class="success-wrap">
                <span class="success-icon">🎉</span>
                <div class="success-title">Application Submitted!</div>
                <div class="success-text">
                    Your application for <strong><?= htmlspecialchars($pet['name']) ?></strong> has been received.
                    Our team will review it and get back to you within 48 hours!
                </div>
                <a href="dashboard.php" class="btn-dashboard">
                    <i class="bi bi-house-heart-fill"></i> Back to Dashboard
                </a>
            </div>

            <?php else: ?>
            <!-- ── Application Form ── -->
            <div class="form-title"><i class="bi bi-heart-fill" style="color:var(--orange)"></i> Adoption Application</div>
            <div class="form-subtitle">Please fill out the form honestly. All fields marked * are required.</div>

            <?php if ($form_error): ?>
            <div class="alert-error"><i class="bi bi-exclamation-circle-fill"></i> <?= htmlspecialchars($form_error) ?></div>
            <?php endif; ?>

            <form method="POST">
                <input type="hidden" name="submit_application" value="1">

                <div class="section-divider">📞 Contact Information</div>
                <div class="mb">
                    <label class="lbl">Contact Number *</label>
                    <input type="text" class="ipt" name="contact_number" placeholder="+63 912 345 6789"
                           value="<?= htmlspecialchars($_POST['contact_number'] ?? '') ?>" required>
                </div>
                <div class="mb">
                    <label class="lbl">Home Address *</label>
                    <textarea class="ipt" name="address" rows="2"
                              placeholder="Street, Barangay, City, Province"
                              required><?= htmlspecialchars($_POST['address'] ?? '') ?></textarea>
                </div>

                <div class="section-divider">💼 Personal Background</div>
                <div class="two-col mb">
                    <div>
                        <label class="lbl">Occupation *</label>
                        <input type="text" class="ipt" name="occupation" placeholder="e.g. Teacher"
                               value="<?= htmlspecialchars($_POST['occupation'] ?? '') ?>" required>
                    </div>
                    <div>
                        <label class="lbl">Monthly Income (₱) *</label>
                        <input type="number" class="ipt" name="monthly_income" min="0" step="0.01"
                               placeholder="e.g. 25000"
                               value="<?= htmlspecialchars($_POST['monthly_income'] ?? '') ?>" required>
                    </div>
                </div>

                <div class="section-divider">🏠 Living Situation</div>
                <div class="mb">
                    <label class="lbl">Type of Home *</label>
                    <div class="radio-group">
                        <label class="radio-option">
                            <input type="radio" name="living_situation" value="house"
                                   <?= ($_POST['living_situation'] ?? '') === 'house' ? 'checked' : '' ?> required>
                            <span class="radio-dot"></span> 🏡 House
                        </label>
                        <label class="radio-option">
                            <input type="radio" name="living_situation" value="apartment"
                                   <?= ($_POST['living_situation'] ?? '') === 'apartment' ? 'checked' : '' ?>>
                            <span class="radio-dot"></span> 🏢 Apartment
                        </label>
                    </div>
                </div>
                <div class="mb">
                    <label class="lbl">Ownership Status *</label>
                    <div class="radio-group">
                        <label class="radio-option">
                            <input type="radio" name="ownership_status" value="owned"
                                   <?= ($_POST['ownership_status'] ?? '') === 'owned' ? 'checked' : '' ?> required>
                            <span class="radio-dot"></span> 🔑 Owned
                        </label>
                        <label class="radio-option">
                            <input type="radio" name="ownership_status" value="rented"
                                   <?= ($_POST['ownership_status'] ?? '') === 'rented' ? 'checked' : '' ?>>
                            <span class="radio-dot"></span> 📄 Rented
                        </label>
                    </div>
                </div>

                <div class="section-divider">👨‍👩‍👧 Household</div>
                <div class="mb">
                    <label class="lbl">Additional Information</label>
                    <div class="check-group">
                        <label class="check-option">
                            <input type="checkbox" name="has_existing_pets" value="1"
                                   <?= !empty($_POST['has_existing_pets']) ? 'checked' : '' ?>>
                            <span class="check-icon">🐾</span> I have existing pets
                        </label>
                        <label class="check-option">
                            <input type="checkbox" name="has_children" value="1"
                                   <?= !empty($_POST['has_children']) ? 'checked' : '' ?>>
                            <span class="check-icon">👶</span> I have children
                        </label>
                    </div>
                </div>

                <div class="section-divider">💬 Your Story</div>
                <div class="mb">
                    <label class="lbl">Why adopt <?= htmlspecialchars($pet['name']) ?>? *</label>
                    <textarea class="ipt" name="reason" rows="3"
                              placeholder="Tell us why you'd be a great match for this pet..."
                              required><?= htmlspecialchars($_POST['reason'] ?? '') ?></textarea>
                </div>

                <button type="submit" class="btn-submit">
                    <i class="bi bi-send-fill me-2"></i> Submit Application
                </button>
            </form>
            <?php endif; ?>

        </div>
    </div>

</div><!-- /page-wrap -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function showForm() {
    const box = document.getElementById('apply-box');
    if (box) box.classList.add('hidden');
    setTimeout(() => {
        document.getElementById('page-wrap').classList.add('split');
    }, 180);
}

// Share / copy link
function sharePet() {
    const btn = document.getElementById('share-btn');
    navigator.clipboard.writeText(window.location.href).then(() => {
        btn.innerHTML = '<i class="bi bi-check-lg"></i> Copied!';
        btn.classList.add('copied');
        setTimeout(() => {
            btn.innerHTML = '<i class="bi bi-share"></i> Share';
            btn.classList.remove('copied');
        }, 2000);
    }).catch(() => {
        // Fallback for older browsers
        const dummy = document.createElement('input');
        document.body.appendChild(dummy);
        dummy.value = window.location.href;
        dummy.select();
        document.execCommand('copy');
        document.body.removeChild(dummy);
        btn.innerHTML = '<i class="bi bi-check-lg"></i> Copied!';
        btn.classList.add('copied');
        setTimeout(() => {
            btn.innerHTML = '<i class="bi bi-share"></i> Share';
            btn.classList.remove('copied');
        }, 2000);
    });
}

// On mobile: scroll to form once it opens
const panelRight = document.getElementById('panel-right');
panelRight.addEventListener('transitionend', function(e) {
    if (e.propertyName === 'width' && window.innerWidth <= 768) {
        panelRight.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
});
</script>
</body>
</html>