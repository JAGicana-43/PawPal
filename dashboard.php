<?php
session_start();
require_once 'config/database.php';
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

// Auth check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'adopter') {
    header('Location: auth/login.php');
    exit;
}

$user_id   = $_SESSION['user_id'];
$full_name = $_SESSION['full_name'];
$first_name = explode(' ', $full_name)[0];

// ── AJAX handlers ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    if ($_POST['action'] === 'toggle_favorite') {
        $pet_id = (int)$_POST['pet_id'];
        $check = mysqli_query($conn, "SELECT favorite_id FROM favorites WHERE user_id=$user_id AND pet_id=$pet_id");
        if (mysqli_num_rows($check) > 0) {
            mysqli_query($conn, "DELETE FROM favorites WHERE user_id=$user_id AND pet_id=$pet_id");
            echo json_encode(['status' => 'removed']);
        } else {
            mysqli_query($conn, "INSERT INTO favorites (user_id, pet_id) VALUES ($user_id, $pet_id)");
            echo json_encode(['status' => 'added']);
        }
        exit;
    }
    if ($_POST['action'] === 'cancel_application') {
        $app_id = (int)$_POST['application_id'];
        $check = mysqli_query($conn,
            "SELECT application_id FROM adoption_applications
             WHERE application_id=$app_id AND user_id=$user_id AND status='pending'"
        );
        if (mysqli_num_rows($check) > 0) {
            mysqli_query($conn, "UPDATE adoption_applications SET status='cancelled' WHERE application_id=$app_id");
            echo json_encode(['status' => 'cancelled']);
        } else {
            echo json_encode(['status' => 'error']);
        }
        exit;
    }
    exit;
}

// ── Fetch favorites ────────────────────────────────────────────
$fav_result = mysqli_query($conn, "SELECT pet_id FROM favorites WHERE user_id=$user_id");
$my_favorites = [];
while ($f = mysqli_fetch_assoc($fav_result)) $my_favorites[] = $f['pet_id'];

// ── Fetch available pets ───────────────────────────────────────
$pets = mysqli_query($conn,
    "SELECT * FROM pets WHERE status = 'available' ORDER BY created_at DESC LIMIT 6"
);

// ── Fetch my applications ──────────────────────────────────────
$my_apps = mysqli_query($conn,
    "SELECT aa.*, p.name AS pet_name, p.species, p.image_path
     FROM adoption_applications aa
     JOIN pets p ON aa.pet_id = p.pet_id
     WHERE aa.user_id = $user_id
     ORDER BY aa.applied_at DESC"
);

// ── Fetch my profile ───────────────────────────────────────────
$stmt = mysqli_prepare($conn, "SELECT * FROM users WHERE user_id = ?");
mysqli_stmt_bind_param($stmt, 'i', $user_id);
mysqli_stmt_execute($stmt);
$profile = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

// ── Stats ──────────────────────────────────────────────────────
$r = mysqli_query($conn, "SELECT COUNT(*) FROM pets WHERE status = 'available'");
$total_available = mysqli_fetch_row($r)[0];

$r = mysqli_query($conn, "SELECT COUNT(*) FROM adoption_applications WHERE user_id = $user_id");
$total_my_apps = mysqli_fetch_row($r)[0];

$r = mysqli_query($conn, "SELECT COUNT(*) FROM adoption_applications WHERE user_id = $user_id AND status = 'approved'");
$total_approved = mysqli_fetch_row($r)[0];

$r = mysqli_query($conn, "SELECT COUNT(*) FROM adoption_applications WHERE user_id = $user_id AND status = 'pending'");
$total_pending = mysqli_fetch_row($r)[0];

function petBadges($pet) {
    $b = '<span class="pet-badge badge-available">✅ Available</span>';
    if (!empty($pet['is_vaccinated']))  $b .= '<span class="pet-badge badge-vaccinated">💉 Vaccinated</span>';
    if (!empty($pet['is_neutered']))    $b .= '<span class="pet-badge badge-neutered">✂️ Neutered</span>';
    if (!empty($pet['good_with_kids'])) $b .= '<span class="pet-badge badge-kids">👶 Good with Kids</span>';
    return $b;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard — PawPal</title>
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
        .brand {
            font-family: 'Baloo 2', cursive;
            font-size: 1.6rem;
            font-weight: 800;
            color: var(--orange);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.4rem;
        }
        .brand img { height: 100px; width: auto; }

        .nav-links {
            display: flex;
            align-items: center;
            gap: 0.3rem;
            list-style: none;
        }
        .nav-links a {
            font-size: 0.88rem;
            font-weight: 700;
            color: var(--text-muted);
            text-decoration: none;
            padding: 0.45rem 0.9rem;
            border-radius: 8px;
            transition: all 0.18s;
            display: flex;
            align-items: center;
            gap: 0.4rem;
        }
        .nav-links a:hover,
        .nav-links a.active {
            background: #FFF0E8;
            color: var(--orange);
        }

        .nav-right {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        .avatar {
            width: 38px; height: 38px;
            border-radius: 50%;
            background: var(--orange);
            color: #fff;
            font-family: 'Baloo 2', cursive;
            font-weight: 800;
            font-size: 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
        }
        .dropdown-menu {
            border: 1.5px solid #F0E6DE;
            border-radius: 12px;
            padding: 0.5rem;
            box-shadow: 0 8px 24px rgba(0,0,0,0.08);
        }
        .dropdown-item {
            border-radius: 8px;
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--text-dark);
            padding: 0.5rem 0.9rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .dropdown-item:hover { background: #FFF0E8; color: var(--orange); }
        .dropdown-item.text-danger:hover { background: #FFEBEE; color: #C62828; }

        /* ── Main ── */
        .main-wrap { padding: 2rem 2.2rem; max-width: 1200px; margin: 0 auto; overflow: visible;}

        /* ── Hero ── */
        .hero {
            background: linear-gradient(135deg, var(--orange) 0%, var(--orange-dark) 100%);
            border-radius: 20px;
            padding: 2rem 2.5rem;
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1.8rem;
            overflow: hidden;
            position: relative;
        }
        .hero::after {
            content: '🐾';
            position: absolute;
            right: 2rem;
            font-size: 8rem;
            opacity: 0.15;
            pointer-events: none;
        }
        .hero h2 { font-size: 1.8rem; font-weight: 800; margin-bottom: 0.3rem; }
        .hero p  { font-size: 0.9rem; opacity: 0.85; font-weight: 600; }

        /* ── Stat cards ── */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1rem;
            margin-bottom: 1.8rem;
        }
        .stat-card {
            background: #fff;
            border: 1.5px solid #F0E6DE;
            border-radius: 16px;
            padding: 1.2rem 1.4rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            transition: box-shadow 0.2s, transform 0.2s;
        }
        .stat-card:hover {
            box-shadow: 0 6px 20px rgba(255,112,67,0.10);
            transform: translateY(-2px);
        }
        .stat-icon {
            width: 48px; height: 48px;
            border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.3rem;
            flex-shrink: 0;
        }
        .stat-icon.orange { background: #FFF0E8; }
        .stat-icon.green  { background: #E8F5E9; }
        .stat-icon.blue   { background: #E3F2FD; }
        .stat-icon.yellow { background: #FFF8E1; }
        .stat-num { font-family: 'Baloo 2', cursive; font-size: 1.7rem; font-weight: 800; color: var(--text-dark); line-height: 1; }
        .stat-lbl { font-size: 0.78rem; font-weight: 700; color: var(--text-muted); margin-top: 2px; }

        /* ── Section ── */
        .section-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1rem;
        }
        .section-title {
            font-size: 1.1rem;
            font-weight: 800;
            color: var(--text-dark);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .view-all {
            font-size: 0.8rem;
            font-weight: 700;
            color: var(--orange);
            text-decoration: none;
        }
        .view-all:hover { text-decoration: underline; }

        /* ── Pet cards ── */
        .pets-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1.1rem;
            margin-bottom: 1.8rem;
        }
        .pet-card {
            background: #fff;
            border: 1.5px solid #F0E6DE;
            border-radius: 16px;
            overflow: hidden;
            transition: box-shadow 0.2s, transform 0.2s;
            text-decoration: none;
            color: inherit;
            display: block;
        }
        .pet-card:hover {
            box-shadow: 0 8px 24px rgba(255,112,67,0.13);
            transform: translateY(-3px);
        }
        .pet-img {
            width: 100%;
            height: 160px;
            object-fit: cover;
            background: #F5EDE7;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3.5rem;
        }
        .pet-img img { width: 100%; height: 160px; object-fit: cover; }
        .pet-body { padding: 1rem 1.1rem; }
        .pet-name { font-size: 1rem; font-weight: 800; color: var(--text-dark); margin-bottom: 0.2rem; }
        .pet-meta { font-size: 0.78rem; color: var(--text-muted); font-weight: 600; }
        .badges-wrap{display:flex;flex-wrap:wrap;gap:.3rem;margin-top:.5rem;}
        .pet-badge{display:inline-block;font-size:.68rem;font-weight:800;padding:.18rem .55rem;border-radius:50px;white-space:nowrap;}
        .badge-available{background:#E8F5E9;color:#2E7D32;}
        .badge-vaccinated{background:#E3F2FD;color:#1565C0;}
        .badge-neutered{background:#F3E5F5;color:#6A1B9A;}
        .badge-kids{background:#FFF8E1;color:#E65100;}
        .btn-adopt {
            display: block;
            width: 100%;
            background: var(--orange);
            color: #fff;
            border: none;
            border-radius: 0 0 14px 14px;
            padding: 0.6rem;
            font-weight: 800;
            font-size: 0.85rem;
            font-family: 'Baloo 2', cursive;
            cursor: pointer;
            text-align: center;
            text-decoration: none;
            transition: background 0.2s;
        }
        .btn-adopt:hover { background: var(--orange-dark); color: #fff; }

        /* ── Applications table ── */
        .card-box {
            background: #fff;
            border: 1.5px solid #F0E6DE;
            border-radius: 16px;
            padding: 1.3rem 1.5rem;
            margin-bottom: 1.8rem;
        }
        table { width: 100%; border-collapse: collapse; }
        thead th {
            font-size: 0.72rem; font-weight: 800; text-transform: uppercase;
            letter-spacing: 0.8px; color: var(--brown-light);
            padding: 0.6rem 1rem; background: #FFF8F4;
            border-bottom: 1.5px solid #F0E6DE; text-align: left; white-space: nowrap;
        }
        tbody td {
            padding: 0.85rem 1rem; font-size: 0.86rem;
            border-bottom: 1px solid #F5EDE7; vertical-align: middle;
        }
        tbody tr:last-child td { border-bottom: none; }
        tbody tr:hover { background: #FFFAF7; }

        .badge-pending  { background:#FFF8E1; color:#F57F17; font-size:0.72rem; font-weight:800; padding:0.25rem 0.7rem; border-radius:50px; text-transform:uppercase; }
        .badge-approved { background:#E8F5E9; color:#2E7D32; font-size:0.72rem; font-weight:800; padding:0.25rem 0.7rem; border-radius:50px; text-transform:uppercase; }
        .badge-rejected { background:#FFEBEE; color:#C62828; font-size:0.72rem; font-weight:800; padding:0.25rem 0.7rem; border-radius:50px; text-transform:uppercase; }
        .badge-cancelled{ background:#F5F5F5; color:#757575; font-size:0.72rem; font-weight:800; padding:0.25rem 0.7rem; border-radius:50px; text-transform:uppercase; }

        /* ── Care Tips ── */
        .tips-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1.1rem;
            margin-bottom: 1.8rem;
        }
        .tip-card {
            background: #fff;
            border: 1.5px solid #F0E6DE;
            border-radius: 16px;
            padding: 1.3rem 1.4rem;
            transition: box-shadow 0.2s, transform 0.2s;
        }
        .tip-card:hover {
            box-shadow: 0 6px 20px rgba(255,112,67,0.10);
            transform: translateY(-2px);
        }
        .tip-icon {
            font-size: 2rem;
            margin-bottom: 0.6rem;
            display: block;
        }
        .tip-title { font-size: 0.95rem; font-weight: 800; color: var(--text-dark); margin-bottom: 0.3rem; }
        .tip-text  { font-size: 0.82rem; color: var(--text-muted); font-weight: 600; line-height: 1.5; }

        /* ── Profile section ── */
        .profile-card {
            background: #fff;
            border: 1.5px solid #F0E6DE;
            border-radius: 16px;
            padding: 1.5rem;
            margin-bottom: 1.8rem;
            display: flex;
            align-items: center;
            gap: 1.5rem;
        }
        .profile-avatar {
            width: 72px; height: 72px;
            border-radius: 50%;
            background: var(--orange);
            color: #fff;
            font-family: 'Baloo 2', cursive;
            font-weight: 800;
            font-size: 1.8rem;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .profile-name  { font-size: 1.2rem; font-weight: 800; color: var(--text-dark); }
        .profile-email { font-size: 0.85rem; color: var(--text-muted); font-weight: 600; }
        .profile-role  {
            display: inline-block;
            background: #FFF0E8;
            color: var(--orange);
            font-size: 0.72rem;
            font-weight: 800;
            padding: 0.2rem 0.7rem;
            border-radius: 50px;
            margin-top: 0.3rem;
            text-transform: uppercase;
        }

        .empty-state { text-align:center; padding:2rem; color:var(--text-muted); }
        .empty-state i { font-size:2.5rem; display:block; margin-bottom:0.5rem; color:#E8D8CC; }

        /* ── Tabs ── */
        .dash-tabs {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1.8rem;
            border-bottom: 2px solid #F0E6DE;
            padding-bottom: 0;
        }
        .dash-tab {
            font-size: 0.88rem;
            font-weight: 700;
            color: var(--text-muted);
            padding: 0.6rem 1.1rem;
            border: none;
            background: none;
            cursor: pointer;
            border-bottom: 2.5px solid transparent;
            margin-bottom: -2px;
            transition: all 0.18s;
            display: flex;
            align-items: center;
            gap: 0.4rem;
            font-family: 'Nunito', sans-serif;
        }
        .dash-tab:hover { color: var(--orange); }
        .dash-tab.active { color: var(--orange); border-bottom-color: var(--orange); }

        .tab-panel { display: none; }
        .tab-panel.active { display: block; }

        @media (max-width: 900px) {
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .pets-grid  { grid-template-columns: repeat(2, 1fr); }
            .tips-grid  { grid-template-columns: 1fr; }
        }
        @media (max-width: 600px) {
            .stats-grid { grid-template-columns: 1fr 1fr; }
            .pets-grid  { grid-template-columns: 1fr; }
            .main-wrap  { padding: 1.2rem 1rem; }
            .nav-links  { display: none; }
        }


/* ── Animations ── */
@keyframes fadeInUp {
    from { opacity: 0; transform: translateY(24px); }
    to   { opacity: 1; transform: translateY(0); }
}
@keyframes fadeInDown {
    from { opacity: 0; transform: translateY(-16px); }
    to   { opacity: 1; transform: translateY(0); }
}
@keyframes popIn {
    0%   { opacity: 0; transform: scale(0.85); }
    70%  { transform: scale(1.04); }
    100% { opacity: 1; transform: scale(1); }
}
@keyframes shimmer {
    0%   { background-position: -400px 0; }
    100% { background-position: 400px 0; }
}
@keyframes pawBounce {
    0%, 100% { transform: translateY(0) rotate(0deg); }
    30%       { transform: translateY(-8px) rotate(-5deg); }
    60%       { transform: translateY(-4px) rotate(3deg); }
}

/* Navbar slides down */
.navbar {
    animation: fadeInDown 0.45s ease both;
}

/* Hero pops in */
.hero {
    animation: fadeInUp 0.5s ease 0.1s both;
}
/* Paw emoji in hero bounces */
.hero::after {
    animation: pawBounce 3s ease-in-out infinite;
}

/* Stat cards stagger in */
.stat-card:nth-child(1) { animation: fadeInUp 0.45s ease 0.15s both; }
.stat-card:nth-child(2) { animation: fadeInUp 0.45s ease 0.25s both; }
.stat-card:nth-child(3) { animation: fadeInUp 0.45s ease 0.35s both; }
.stat-card:nth-child(4) { animation: fadeInUp 0.45s ease 0.45s both; }

/* Stat numbers count-up feel via scale pop */
.stat-num {
    display: inline-block;
    animation: popIn 0.4s ease 0.5s both;
}

/* Pet cards stagger */
.pet-card:nth-child(1) { animation: fadeInUp 0.45s ease 0.2s both; }
.pet-card:nth-child(2) { animation: fadeInUp 0.45s ease 0.3s both; }
.pet-card:nth-child(3) { animation: fadeInUp 0.45s ease 0.4s both; }
.pet-card:nth-child(4) { animation: fadeInUp 0.45s ease 0.5s both; }
.pet-card:nth-child(5) { animation: fadeInUp 0.45s ease 0.6s both; }
.pet-card:nth-child(6) { animation: fadeInUp 0.45s ease 0.7s both; }

/* Pet card image zoom on hover */
.pet-img img {
    transition: transform 0.35s ease;
}
.pet-card:hover .pet-img img {
    transform: scale(1.07);
}
.pet-img {
    overflow: hidden;
}

/* Adopt button pulse on hover */
.btn-adopt {
    transition: background 0.2s, transform 0.15s, box-shadow 0.2s;
}
.btn-adopt:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 14px rgba(255,112,67,0.35);
}

/* Tip cards stagger */
.tip-card:nth-child(1) { animation: fadeInUp 0.4s ease 0.1s both; }
.tip-card:nth-child(2) { animation: fadeInUp 0.4s ease 0.2s both; }
.tip-card:nth-child(3) { animation: fadeInUp 0.4s ease 0.3s both; }
.tip-card:nth-child(4) { animation: fadeInUp 0.4s ease 0.4s both; }
.tip-card:nth-child(5) { animation: fadeInUp 0.4s ease 0.5s both; }
.tip-card:nth-child(6) { animation: fadeInUp 0.4s ease 0.6s both; }

/* Tip icon wiggles on hover */
.tip-card:hover .tip-icon {
    display: inline-block;
    animation: pawBounce 0.6s ease;
}

/* Nav link underline slide */
.nav-links a::after {
    content: '';
    display: block;
    height: 2px;
    background: var(--orange);
    width: 0;
    transition: width 0.2s ease;
    border-radius: 2px;
}
.nav-links a:hover::after,
.nav-links a.active::after {
    width: 100%;
}

/* Avatar spin-in */
.avatar {
    animation: popIn 0.4s ease 0.3s both;
    transition: transform 0.2s, box-shadow 0.2s;
}
.avatar:hover {
    transform: scale(1.1);
    box-shadow: 0 4px 12px rgba(255,112,67,0.3);
}

/* Tab panel fade on switch */
.tab-panel.active {
    animation: fadeInUp 0.35s ease both;
}

/* Profile card */
.profile-card {
    animation: fadeInUp 0.4s ease 0.1s both;
}
.profile-avatar {
    transition: transform 0.2s;
}
.profile-avatar:hover {
    transform: scale(1.08) rotate(-3deg);
}

/* Table rows slide in */
tbody tr {
    animation: fadeInUp 0.3s ease both;
}
tbody tr:nth-child(1) { animation-delay: 0.05s; }
tbody tr:nth-child(2) { animation-delay: 0.10s; }
tbody tr:nth-child(3) { animation-delay: 0.15s; }
tbody tr:nth-child(4) { animation-delay: 0.20s; }
tbody tr:nth-child(5) { animation-delay: 0.25s; }

/* ── Filter bar ── */
.filter-bar {
    background: #fff;
    border: 1.5px solid #F0E6DE;
    border-radius: 18px;
    padding: 1.2rem 1.4rem;
    margin-bottom: 1.4rem;
}
 
/* Search */
.search-wrap {
    position: relative;
    margin-bottom: 1rem;
}
.search-icon {
    position: absolute;
    left: 1rem;
    top: 50%;
    transform: translateY(-50%);
    color: var(--brown-light);
    font-size: 0.95rem;
    pointer-events: none;
}
.search-input {
    width: 100%;
    padding: 0.65rem 2.8rem 0.65rem 2.6rem;
    border: 1.5px solid #EDE0D8;
    border-radius: 10px;
    font-family: 'Nunito', sans-serif;
    font-size: 0.88rem;
    font-weight: 600;
    color: var(--text-dark);
    background: #FFFAF7;
    transition: border-color 0.18s, box-shadow 0.18s;
    outline: none;
}
.search-input:focus {
    border-color: var(--orange);
    box-shadow: 0 0 0 3px rgba(255,112,67,0.12);
    background: #fff;
}
.search-input::placeholder { color: #C4AFA8; }
.search-clear {
    position: absolute;
    right: 0.8rem;
    top: 50%;
    transform: translateY(-50%);
    background: none;
    border: none;
    color: var(--brown-light);
    cursor: pointer;
    font-size: 0.8rem;
    padding: 0.2rem 0.3rem;
    border-radius: 6px;
    transition: color 0.15s, background 0.15s;
}
.search-clear:hover { color: var(--orange); background: #FFF0E8; }
 
/* Filter row */
.filter-row {
    display: flex;
    flex-wrap: wrap;
    gap: 1rem 1.4rem;
    align-items: flex-start;
}
.filter-group {
    display: flex;
    flex-direction: column;
    gap: 0.4rem;
}
.filter-label {
    font-size: 0.7rem;
    font-weight: 800;
    color: var(--brown-light);
    text-transform: uppercase;
    letter-spacing: 0.6px;
}
.chip-group {
    display: flex;
    flex-wrap: wrap;
    gap: 0.3rem;
}
.chip {
    background: #FFF8F4;
    border: 1.5px solid #EDE0D8;
    border-radius: 50px;
    padding: 0.3rem 0.85rem;
    font-size: 0.78rem;
    font-weight: 700;
    color: var(--text-muted);
    cursor: pointer;
    font-family: 'Nunito', sans-serif;
    transition: all 0.15s;
    white-space: nowrap;
}
.chip:hover { border-color: var(--orange); color: var(--orange); }
.chip.active {
    background: var(--orange);
    border-color: var(--orange);
    color: #fff;
}
 
/* Sort select */
.sort-select {
    background: #FFF8F4;
    border: 1.5px solid #EDE0D8;
    border-radius: 10px;
    padding: 0.38rem 2rem 0.38rem 0.75rem;
    font-family: 'Nunito', sans-serif;
    font-size: 0.8rem;
    font-weight: 700;
    color: var(--text-dark);
    cursor: pointer;
    outline: none;
    appearance: none;
    -webkit-appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 16 16'%3E%3Cpath fill='%23A1887F' d='M7.247 11.14 2.451 5.658C1.885 5.013 2.345 4 3.204 4h9.592a1 1 0 0 1 .753 1.659l-4.796 5.48a1 1 0 0 1-1.506 0z'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 0.6rem center;
    transition: border-color 0.15s;
}
.sort-select:focus { border-color: var(--orange); }
 
/* Results meta */
.filter-meta {
    margin-top: 0.9rem;
    display: flex;
    align-items: center;
    gap: 0.8rem;
    flex-wrap: wrap;
}
.results-count {
    font-size: 0.78rem;
    font-weight: 700;
    color: var(--brown-light);
}
.clear-all-btn {
    font-size: 0.78rem;
    font-weight: 700;
    color: var(--orange);
    background: none;
    border: none;
    cursor: pointer;
    font-family: 'Nunito', sans-serif;
    display: flex;
    align-items: center;
    gap: 0.3rem;
    padding: 0;
    transition: opacity 0.15s;
}
.clear-all-btn:hover { opacity: 0.75; }
 
/* Pet card hidden state */
.pet-card.hidden { display: none; }
 
/* Responsive filters */
@media (max-width: 700px) {
    .filter-row { flex-direction: column; gap: 0.8rem; }
    .filter-group { width: 100%; }
    .sort-select { width: 100%; }
}

footer {
    opacity: 0;
    transform: translateY(30px);
    transition: opacity 0.5s ease, transform 0.5s ease;
}
footer.footer-visible {
    opacity: 1;
    transform: translateY(0);
}

/* ── Pet card position fix for overlays ── */
.pet-card { position: relative; }
.pet-img { position: relative; overflow: hidden; }

/* ── Favorite button ── */
.fav-btn{position:absolute;top:.55rem;right:.55rem;width:32px;height:32px;border-radius:50%;background:rgba(255,255,255,.92);border:none;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:1rem;transition:all .2s;z-index:2;box-shadow:0 2px 8px rgba(0,0,0,.12);}
.fav-btn:hover{transform:scale(1.15);background:#fff;}
.fav-btn i{color:#BCAAA4;transition:color .2s;}
.fav-btn.active i{color:#E53935;}

/* ── Quick-view hover button ── */
.quickview-btn{position:absolute;top:.55rem;left:.55rem;height:28px;padding:0 .65rem;border-radius:50px;background:rgba(255,255,255,.92);border:none;cursor:pointer;display:flex;align-items:center;gap:.3rem;font-size:.72rem;font-weight:800;color:var(--orange);transition:all .2s;z-index:2;font-family:'Nunito',sans-serif;box-shadow:0 2px 8px rgba(0,0,0,.12);opacity:0;}
.pet-card:hover .quickview-btn{opacity:1;}
.quickview-btn:hover{background:var(--orange);color:#fff;}

/* ── Quick-view modal ── */
.qv-overlay{display:none;position:fixed;inset:0;background:rgba(62,39,35,.5);z-index:1000;align-items:center;justify-content:center;backdrop-filter:blur(4px);}
.qv-overlay.open{display:flex;}
.qv-modal{background:#fff;border-radius:20px;max-width:680px;width:calc(100% - 2rem);max-height:90vh;overflow-y:auto;box-shadow:0 24px 64px rgba(0,0,0,.2);animation:modalPop .28s ease;}
@keyframes modalPop{from{opacity:0;transform:scale(.92) translateY(16px);}to{opacity:1;transform:scale(1) translateY(0);}}
.qv-modal-inner{position:relative;}
.qv-img-wrap{width:100%;height:260px;background:#F5EDE7;display:flex;align-items:center;justify-content:center;font-size:5rem;border-radius:20px 20px 0 0;overflow:hidden;}
.qv-img-wrap img{width:100%;height:260px;object-fit:cover;}
.qv-body{padding:1.5rem 1.8rem 1.8rem;}
.qv-name{font-size:1.5rem;font-weight:800;color:var(--text-dark);font-family:'Baloo 2',cursive;}
.qv-meta{font-size:.88rem;color:var(--text-muted);font-weight:600;margin-bottom:.8rem;}
.qv-desc{font-size:.88rem;color:var(--text-dark);line-height:1.65;margin-bottom:1rem;}
.qv-close{position:absolute;top:1rem;right:1rem;width:36px;height:36px;border-radius:50%;background:rgba(255,255,255,.9);border:none;font-size:1.1rem;cursor:pointer;display:flex;align-items:center;justify-content:center;color:var(--text-dark);transition:all .18s;z-index:5;}
.qv-close:hover{background:#fff;transform:scale(1.1);}
.qv-info-grid{display:grid;grid-template-columns:1fr 1fr;gap:.6rem;margin-bottom:1rem;}
.qv-info-item{background:#FFF8F4;border-radius:10px;padding:.6rem .8rem;}
.qv-info-label{font-size:.68rem;font-weight:800;color:var(--brown-light);text-transform:uppercase;letter-spacing:.5px;}
.qv-info-value{font-size:.9rem;font-weight:700;color:var(--text-dark);margin-top:2px;}
.qv-actions{display:flex;gap:.8rem;margin-top:1.2rem;}
.qv-btn-adopt{flex:1;background:var(--orange);color:#fff;border:none;border-radius:12px;padding:.75rem 1rem;font-weight:800;font-size:.95rem;font-family:'Baloo 2',cursive;cursor:pointer;text-align:center;text-decoration:none;display:flex;align-items:center;justify-content:center;gap:.4rem;transition:background .2s;}
.qv-btn-adopt:hover{background:var(--orange-dark);color:#fff;}
.qv-btn-fav{width:48px;height:48px;border-radius:12px;border:1.5px solid #F0E6DE;background:#fff;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:1.2rem;transition:all .2s;flex-shrink:0;}
.qv-btn-fav:hover{border-color:#E53935;background:#FFEBEE;}
.qv-btn-fav i{color:#BCAAA4;}
.qv-btn-fav.active{border-color:#E53935;background:#FFEBEE;}
.qv-btn-fav.active i{color:#E53935;}

/* ── Cancel button ── */
.btn-cancel-app{background:none;border:1.5px solid #FFCDD2;color:#C62828;border-radius:8px;font-size:.75rem;font-weight:800;padding:.25rem .65rem;cursor:pointer;font-family:'Nunito',sans-serif;transition:all .18s;display:inline-flex;align-items:center;gap:.3rem;}
.btn-cancel-app:hover{background:#FFEBEE;border-color:#C62828;}

/* ── Rejection note ── */
.rejection-note{background:#FFF3E0;border-left:3px solid #FF7043;border-radius:0 8px 8px 0;padding:.4rem .7rem;font-size:.78rem;color:var(--text-muted);font-weight:600;max-width:220px;}
.rejection-note strong{color:var(--orange);display:block;font-size:.7rem;margin-bottom:.15rem;}

/* ── Timeline ── */
.timeline-wrap{display:flex;align-items:flex-start;}
.tl-step{display:flex;flex-direction:column;align-items:center;position:relative;flex:1;}
.tl-step:not(:last-child)::after{content:'';position:absolute;top:10px;left:50%;width:100%;height:2px;background:#EDE0D8;z-index:0;}
.tl-step.done:not(:last-child)::after{background:#4CAF50;}
.tl-step.active:not(:last-child)::after{background:#FFE0B2;}
.tl-dot{width:20px;height:20px;border-radius:50%;z-index:1;border:2.5px solid #EDE0D8;background:#fff;display:flex;align-items:center;justify-content:center;font-size:.55rem;transition:all .2s;}
.tl-step.done .tl-dot{background:#4CAF50;border-color:#4CAF50;color:#fff;}
.tl-step.active .tl-dot{background:var(--orange);border-color:var(--orange);color:#fff;}
.tl-step.rejected .tl-dot{background:#E53935;border-color:#E53935;color:#fff;}
.tl-step.cancelled .tl-dot{background:#9E9E9E;border-color:#9E9E9E;color:#fff;}
.tl-label{font-size:.6rem;font-weight:800;color:var(--brown-light);margin-top:4px;white-space:nowrap;text-align:center;}
.tl-step.done .tl-label{color:#2E7D32;}
.tl-step.active .tl-label{color:var(--orange);}
.tl-step.rejected .tl-label{color:#C62828;}
.tl-step.cancelled .tl-label{color:#757575;}

/* ── Toast ── */
.toast-wrap{position:fixed;bottom:1.5rem;right:1.5rem;z-index:9999;display:flex;flex-direction:column;gap:.5rem;}
.toast-msg{background:#3E2723;color:#fff;border-radius:12px;padding:.75rem 1.1rem;font-size:.85rem;font-weight:700;display:flex;align-items:center;gap:.5rem;box-shadow:0 8px 24px rgba(0,0,0,.15);animation:fadeInUp .3s ease;}
.toast-msg.error{background:#C62828;}
.toast-msg.success{background:#2E7D32;}

</style>
</head>
<body>

<!-- Toast container -->
<div class="toast-wrap" id="toast-wrap"></div>

<!-- Quick-view modal -->
<div class="qv-overlay" id="qv-overlay" onclick="closeQuickView(event)">
  <div class="qv-modal">
    <div class="qv-modal-inner" id="qv-content"></div>
  </div>
</div>

<!-- ══ NAVBAR ══ -->
<nav class="navbar">
    <a href="dashboard.php" class="brand">
        <?php if (file_exists('assets/images/logo.png')): ?>
            <img src="assets/images/logo.png" alt="PawPal">
        <?php endif; ?>
    </a>

    <ul class="nav-links">
        <li><a href="#" class="active" onclick="switchTab('home', this)"><i class="bi bi-house-heart-fill"></i> Home</a></li>
        <li><a href="#" onclick="switchTab('pets', this)"><i class="bi bi-search-heart"></i> Browse Pets</a></li>
        <li><a href="#" onclick="switchTab('applications', this)"><i class="bi bi-file-earmark-check"></i> My Applications</a></li>
        <li><a href="#" onclick="switchTab('tips', this)"><i class="bi bi-lightbulb"></i> Care Tips</a></li>
        <li><a href="#" onclick="switchTab('profile', this)"><i class="bi bi-person-circle"></i> Profile</a></li>
    </ul>

    <div class="nav-right">
        <div class="dropdown">
            <div class="avatar dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                <?= strtoupper(substr($first_name, 0, 1)) ?>
            </div>
            <ul class="dropdown-menu dropdown-menu-end">
                <li><a class="dropdown-item" href="#" onclick="switchTab('profile')"><i class="bi bi-person-circle"></i> My Profile</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item text-danger" href="auth/logout.php"><i class="bi bi-box-arrow-right"></i> Log Out</a></li>
            </ul>
        </div>
    </div>
</nav>

<!-- ══ MAIN ══ -->
<div class="main-wrap">

    <!-- ══ HOME TAB ══ -->
    <div id="tab-home" class="tab-panel active">

        <!-- Hero -->
        <div class="hero">
            <div>
                <h2>Good <?= (date('H') < 12) ? 'morning' : ((date('H') < 18) ? 'afternoon' : 'evening') ?>, <?= htmlspecialchars($first_name) ?>! 👋</h2>
                <p>Find your perfect furry companion today. <?= $total_available ?> pets are waiting for a forever home.</p>
            </div>
        </div>

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon orange"><i class="bi bi-heart-fill" style="color:#FF7043"></i></div>
                <div>
                    <div class="stat-num"><?= $total_available ?></div>
                    <div class="stat-lbl">Pets Available</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon blue"><i class="bi bi-file-earmark-check-fill" style="color:#1565C0"></i></div>
                <div>
                    <div class="stat-num"><?= $total_my_apps ?></div>
                    <div class="stat-lbl">My Applications</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon green"><i class="bi bi-house-heart-fill" style="color:#2E7D32"></i></div>
                <div>
                    <div class="stat-num"><?= $total_approved ?></div>
                    <div class="stat-lbl">Approved</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon yellow"><i class="bi bi-hourglass-split" style="color:#F57F17"></i></div>
                <div>
                    <div class="stat-num"><?= $total_pending ?></div>
                    <div class="stat-lbl">Pending</div>
                </div>
            </div>
        </div>

        <!-- Featured Pets -->
        <div class="section-head">
            <div class="section-title"><i class="bi bi-search-heart" style="color:var(--orange)"></i> Featured Pets</div>
            <a href="#" class="view-all" onclick="switchTab('pets')">View all →</a>
        </div>
        <div class="pets-grid">
            <?php
            // Reset pointer
            mysqli_data_seek($pets, 0);
         while ($pet = mysqli_fetch_assoc($pets)):
    $is_fav = in_array($pet['pet_id'], $my_favorites);
?>
<div class="pet-card" data-pet='<?= htmlspecialchars(json_encode($pet), ENT_QUOTES) ?>'>
    <div class="pet-img">
        <button class="quickview-btn" onclick="openQuickView(this)"><i class="bi bi-eye-fill"></i> Quick View</button>
        <button class="fav-btn <?= $is_fav ? 'active' : '' ?>" data-pet-id="<?= $pet['pet_id'] ?>" onclick="toggleFav(event,this,<?= $pet['pet_id'] ?>)"><i class="bi bi-heart-fill"></i></button>
        <?php if (!empty($pet['image_path'])): ?>
            <img src="<?= htmlspecialchars($pet['image_path']) ?>" alt="<?= htmlspecialchars($pet['name']) ?>">
        <?php else: ?>
            <?= $pet['species'] === 'Cat' ? '🐱' : '🐶' ?>
        <?php endif; ?>
    </div>
    <div class="pet-body">
        <div class="pet-name"><?= htmlspecialchars($pet['name']) ?></div>
        <div class="pet-meta"><?= htmlspecialchars($pet['species']) ?> · <?= htmlspecialchars($pet['breed'] ?? 'Mixed') ?> · <?= $pet['gender'] === 'male' ? '♂' : '♀' ?></div>
        <div class="pet-meta"><?= $pet['age_years'] ?>y <?= $pet['age_months'] ?>m old</div>
        <div class="badges-wrap"><?= petBadges($pet) ?></div>
    </div>
    <a href="adopt.php?pet_id=<?= $pet['pet_id'] ?>" class="btn-adopt"><i class="bi bi-heart-fill me-1"></i> Adopt Me</a>
</div>
<?php endwhile; ?>
        </div>

    </div>

    <!-- ══ BROWSE PETS TAB ══ -->
  <div id="tab-pets" class="tab-panel">
 
    <!-- Search & Filter Bar -->
    <div class="filter-bar">
 
        <!-- Search -->
        <div class="search-wrap">
            <i class="bi bi-search search-icon"></i>
            <input
                type="text"
                id="pet-search"
                class="search-input"
                placeholder="Search by name, breed, or species…"
                oninput="applyFilters()"
            >
            <button class="search-clear" id="search-clear" onclick="clearSearch()" style="display:none">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>
 
        <!-- Filter Row -->
        <div class="filter-row">
 
            <!-- Species chips -->
            <div class="filter-group">
                <span class="filter-label">Species</span>
                <div class="chip-group" id="species-chips">
                    <button class="chip active" data-value="" onclick="setChip('species','',this)">All</button>
                    <button class="chip" data-value="Dog" onclick="setChip('species','Dog',this)">🐶 Dog</button>
                    <button class="chip" data-value="Cat" onclick="setChip('species','Cat',this)">🐱 Cat</button>
                    <button class="chip" data-value="Other" onclick="setChip('species','Other',this)">🐾 Other</button>
                </div>
            </div>
 
            <!-- Gender chips -->
            <div class="filter-group">
                <span class="filter-label">Gender</span>
                <div class="chip-group" id="gender-chips">
                    <button class="chip active" data-value="" onclick="setChip('gender','',this)">All</button>
                    <button class="chip" data-value="male" onclick="setChip('gender','male',this)">♂ Male</button>
                    <button class="chip" data-value="female" onclick="setChip('gender','female',this)">♀ Female</button>
                </div>
            </div>
 
            <!-- Age range -->
            <div class="filter-group">
                <span class="filter-label">Age</span>
                <div class="chip-group" id="age-chips">
                    <button class="chip active" data-value="" onclick="setChip('age','',this)">All</button>
                    <button class="chip" data-value="baby" onclick="setChip('age','baby',this)">Baby (&lt;6m)</button>
                    <button class="chip" data-value="young" onclick="setChip('age','young',this)">Young (6m–2y)</button>
                    <button class="chip" data-value="adult" onclick="setChip('age','adult',this)">Adult (2–7y)</button>
                    <button class="chip" data-value="senior" onclick="setChip('age','senior',this)">Senior (7y+)</button>
                </div>
            </div>
 
            <!-- Sort -->
            <div class="filter-group" style="margin-left:auto">
                <span class="filter-label">Sort by</span>
                <select class="sort-select" id="sort-select" onchange="applyFilters()">
                    <option value="newest">Newest First</option>
                    <option value="oldest">Oldest First</option>
                    <option value="name_az">Name A → Z</option>
                    <option value="name_za">Name Z → A</option>
                    <option value="age_asc">Youngest First</option>
                    <option value="age_desc">Oldest First (Age)</option>
                </select>
            </div>
 
        </div>
 
        <!-- Active filters + results count -->
        <div class="filter-meta" id="filter-meta">
            <span id="results-count" class="results-count"></span>
            <button class="clear-all-btn" id="clear-all-btn" onclick="clearAllFilters()" style="display:none">
                <i class="bi bi-x-circle-fill"></i> Clear all filters
            </button>
        </div>
    </div>
 
    <!-- Pet Grid -->
    <div class="pets-grid" id="pet-grid">
        <?php
        $all_pets = mysqli_query($conn, "SELECT * FROM pets WHERE status = 'available' ORDER BY created_at DESC");
        $all_pets_data = [];
        while ($pet = mysqli_fetch_assoc($all_pets)) {
            $all_pets_data[] = $pet;
        }
        foreach ($all_pets_data as $pet):
            $age_months_total = ($pet['age_years'] * 12) + $pet['age_months'];
        ?>
        <?php $is_fav = in_array($pet['pet_id'], $my_favorites); ?>
    <div class="pet-card"
     data-name="<?= strtolower(htmlspecialchars($pet['name'])) ?>"
     data-breed="<?= strtolower(htmlspecialchars($pet['breed'] ?? '')) ?>"
     data-species="<?= htmlspecialchars($pet['species']) ?>"
     data-gender="<?= htmlspecialchars($pet['gender']) ?>"
     data-age-months="<?= $age_months_total ?>"
     data-created="<?= strtotime($pet['created_at']) ?>"
     data-pet='<?= htmlspecialchars(json_encode($pet), ENT_QUOTES) ?>'
>
    <div class="pet-img">
        <button class="quickview-btn" onclick="openQuickView(this)"><i class="bi bi-eye-fill"></i> Quick View</button>
        <button class="fav-btn <?= $is_fav ? 'active' : '' ?>" data-pet-id="<?= $pet['pet_id'] ?>" onclick="toggleFav(event,this,<?= $pet['pet_id'] ?>)"><i class="bi bi-heart-fill"></i></button>
        <?php if (!empty($pet['image_path'])): ?>
            <img src="<?= htmlspecialchars($pet['image_path']) ?>" alt="<?= htmlspecialchars($pet['name']) ?>">
        <?php else: ?>
            <?= $pet['species'] === 'Cat' ? '🐱' : ($pet['species'] === 'Dog' ? '🐶' : '🐾') ?>
        <?php endif; ?>
    </div>
    <div class="pet-body">
        <div class="pet-name"><?= htmlspecialchars($pet['name']) ?></div>
        <div class="pet-meta"><?= htmlspecialchars($pet['species']) ?> · <?= htmlspecialchars($pet['breed'] ?? 'Mixed') ?> · <?= $pet['gender'] === 'male' ? '♂ Male' : '♀ Female' ?></div>
        <div class="pet-meta"><?= $pet['age_years'] ?>y <?= $pet['age_months'] ?>m old</div>
        <?php if (!empty($pet['description'])): ?>
            <div class="pet-meta" style="margin-top:0.4rem;color:var(--text-muted)"><?= htmlspecialchars(substr($pet['description'], 0, 72)) ?>…</div>
        <?php endif; ?>
        <div class="badges-wrap"><?= petBadges($pet) ?></div>
    </div>
 <a href="adopt.php?pet_id=<?= $pet['pet_id'] ?>" class="btn-adopt"><i class="bi bi-heart-fill me-1"></i> Adopt Me</a>
</div>
<?php endforeach; ?>

    </div><!-- /.pets-grid #pet-grid -->

    <!-- Empty state (shown when no results) -->
    <div class="empty-state" id="no-results" style="display:none">
        <i class="bi bi-search" style="font-size:2.5rem;display:block;margin-bottom:0.5rem;color:#E8D8CC"></i>
        <strong style="display:block;margin-bottom:0.3rem">No pets found</strong>
        <span style="font-size:0.88rem">Try adjusting your search or filters.</span>
        <br><br>
        <button onclick="clearAllFilters()" style="color:var(--orange);font-weight:700;background:none;border:none;cursor:pointer;font-size:0.9rem">Clear all filters →</button>
    </div>

</div><!-- /.tab-pets -->

    <!-- ══ MY APPLICATIONS TAB ══ -->
    <div id="tab-applications" class="tab-panel">
        <div class="section-head">
            <div class="section-title"><i class="bi bi-file-earmark-check-fill" style="color:var(--orange)"></i> My Applications</div>
        </div>
        <div class="card-box">
            <?php if (mysqli_num_rows($my_apps) === 0): ?>
            <div class="empty-state">
                <i class="bi bi-file-earmark-x"></i>
                You haven't applied for any pets yet.
                <br><br>
                <a href="#" onclick="switchTab('pets')" style="color:var(--orange);font-weight:700">Browse available pets →</a>
            </div>
            <?php else: ?>
            <div style="overflow-x:auto">
            <table>
                <thead>
    <tr>
        <th>#</th><th>Pet</th><th>Species</th><th>Status</th>
        <th>Timeline</th><th>Notes</th><th>Applied</th><th>Action</th>
    </tr>
</thead>
<tbody>
    <?php
    mysqli_data_seek($my_apps, 0);
    while ($app = mysqli_fetch_assoc($my_apps)):
        $st = $app['status'];
        $s1 = 'done';
        $s2 = 'pending-tl';
        $s3 = 'pending-tl';
        if ($st === 'pending')    { $s2 = 'active'; }
        elseif ($st === 'approved')  { $s2 = 'done'; $s3 = 'done'; }
        elseif ($st === 'rejected')  { $s2 = 'done'; $s3 = 'rejected'; }
        elseif ($st === 'cancelled') { $s2 = 'cancelled'; $s3 = 'cancelled'; }
    ?>
    <tr id="app-row-<?= $app['application_id'] ?>">
        <td style="color:var(--brown-light);font-size:.78rem"><?= $app['application_id'] ?></td>
        <td>
            <?php if (!empty($app['image_path'])): ?>
                <img src="<?= htmlspecialchars($app['image_path']) ?>" style="width:34px;height:34px;border-radius:8px;object-fit:cover;margin-right:.5rem;vertical-align:middle">
            <?php else: ?>
                <span style="font-size:1.2rem;margin-right:.5rem"><?= $app['species'] === 'Cat' ? '🐱' : '🐶' ?></span>
            <?php endif; ?>
            <strong><?= htmlspecialchars($app['pet_name']) ?></strong>
        </td>
        <td style="color:var(--text-muted)"><?= htmlspecialchars($app['species']) ?></td>
        <td><span class="badge-<?= $st ?>"><?= ucfirst($st) ?></span></td>
        <td style="min-width:170px">
            <div class="timeline-wrap">
                <div class="tl-step <?= $s1 ?>">
                    <div class="tl-dot"><i class="bi bi-check-lg" style="font-size:.6rem"></i></div>
                    <span class="tl-label">Applied</span>
                </div>
                <div class="tl-step <?= $s2 ?>">
                    <div class="tl-dot">
                        <?php if ($s2 === 'active'): ?><i class="bi bi-clock-fill" style="font-size:.55rem"></i>
                        <?php elseif ($s2 === 'done'): ?><i class="bi bi-check-lg" style="font-size:.6rem"></i>
                        <?php elseif ($s2 === 'cancelled'): ?><i class="bi bi-x-lg" style="font-size:.55rem"></i>
                        <?php else: ?><i class="bi bi-dash" style="font-size:.6rem"></i><?php endif; ?>
                    </div>
                    <span class="tl-label">Review</span>
                </div>
                <div class="tl-step <?= $s3 ?>">
                    <div class="tl-dot">
                        <?php if ($s3 === 'done'): ?><i class="bi bi-check-lg" style="font-size:.6rem"></i>
                        <?php elseif ($s3 === 'rejected'): ?><i class="bi bi-x-lg" style="font-size:.55rem"></i>
                        <?php elseif ($s3 === 'cancelled'): ?><i class="bi bi-x-lg" style="font-size:.55rem"></i>
                        <?php else: ?><i class="bi bi-dash" style="font-size:.6rem"></i><?php endif; ?>
                    </div>
                    <span class="tl-label">Decision</span>
                </div>
            </div>
        </td>
        <td style="min-width:160px">
            <?php if (!empty($app['admin_notes'])): ?>
                <div class="rejection-note">
                    <strong><?= $st === 'rejected' ? '❌ Reason' : 'ℹ️ Note' ?></strong>
                    <?= htmlspecialchars($app['admin_notes']) ?>
                </div>
            <?php else: ?>
                <span style="color:#D7CCC8;font-size:.8rem">—</span>
            <?php endif; ?>
        </td>
        <td style="color:var(--text-muted);white-space:nowrap;font-size:.82rem">
            <?= date('M j, Y', strtotime($app['applied_at'])) ?>
            <?php if ($app['reviewed_at']): ?>
                <div style="font-size:.72rem;color:#BCAAA4;margin-top:2px">Reviewed <?= date('M j', strtotime($app['reviewed_at'])) ?></div>
            <?php endif; ?>
        </td>
        <td>
            <?php if ($st === 'pending'): ?>
                <button class="btn-cancel-app" onclick="cancelApplication(<?= $app['application_id'] ?>, this)">
                    <i class="bi bi-x-circle"></i> Cancel
                </button>
            <?php else: ?>
                <span style="color:#D7CCC8;font-size:.8rem">—</span>
            <?php endif; ?>
        </td>
    </tr>
    <?php endwhile; ?>
</tbody>
            </table>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ══ CARE TIPS TAB ══ -->
    <div id="tab-tips" class="tab-panel">
        <div class="section-head">
            <div class="section-title"><i class="bi bi-lightbulb-fill" style="color:var(--orange)"></i> Pet Care Tips</div>
        </div>
        <div class="tips-grid">
            <div class="tip-card">
                <span class="tip-icon">🍖</span>
                <div class="tip-title">Balanced Nutrition</div>
                <div class="tip-text">Feed your pet a balanced diet appropriate for their species, age, and size. Always provide fresh water and avoid feeding them human food that can be harmful.</div>
            </div>
            <div class="tip-card">
                <span class="tip-icon">🏃</span>
                <div class="tip-title">Regular Exercise</div>
                <div class="tip-text">Pets need daily physical activity to stay healthy and happy. Dogs need walks and playtime, while cats benefit from interactive toys and climbing structures.</div>
            </div>
            <div class="tip-card">
                <span class="tip-icon">🏥</span>
                <div class="tip-title">Vet Checkups</div>
                <div class="tip-text">Schedule regular vet visits at least once a year. Keep vaccinations up to date and watch for signs of illness like changes in appetite, behavior, or energy.</div>
            </div>
            <div class="tip-card">
                <span class="tip-icon">🛁</span>
                <div class="tip-title">Grooming & Hygiene</div>
                <div class="tip-text">Regular grooming keeps your pet clean and comfortable. Brush their fur, trim nails, and clean ears regularly to prevent infections and discomfort.</div>
            </div>
            <div class="tip-card">
                <span class="tip-icon">❤️</span>
                <div class="tip-title">Love & Socialization</div>
                <div class="tip-text">Pets thrive on attention and social interaction. Spend quality time with your pet daily — cuddles, play, and training sessions all strengthen your bond.</div>
            </div>
            <div class="tip-card">
                <span class="tip-icon">🏠</span>
                <div class="tip-title">Safe Environment</div>
                <div class="tip-text">Pet-proof your home by securing hazardous items, toxic plants, and small objects. Create a comfortable, designated space where your pet feels safe and secure.</div>
            </div>
        </div>
    </div>

    <!-- ══ PROFILE TAB ══ -->
    <div id="tab-profile" class="tab-panel">
        <div class="section-head">
            <div class="section-title"><i class="bi bi-person-circle" style="color:var(--orange)"></i> My Profile</div>
        </div>
        <div class="profile-card">
            <div class="profile-avatar"><?= strtoupper(substr($full_name, 0, 1)) ?></div>
            <div>
                <div class="profile-name"><?= htmlspecialchars($profile['full_name']) ?></div>
                <div class="profile-email"><i class="bi bi-envelope-fill me-1"></i><?= htmlspecialchars($profile['email']) ?></div>
                <span class="profile-role">🐾 Adopter</span>
            </div>
        </div>
        <div class="card-box">
            <div class="section-title" style="margin-bottom:1.2rem"><i class="bi bi-info-circle-fill" style="color:var(--orange)"></i> Account Details</div>
            <table>
                <tbody>
                    <tr>
                        <td style="font-weight:700;color:var(--brown);width:180px;padding:0.7rem 1rem">Full Name</td>
                        <td style="padding:0.7rem 1rem"><?= htmlspecialchars($profile['full_name']) ?></td>
                    </tr>
                    <tr>
                        <td style="font-weight:700;color:var(--brown);padding:0.7rem 1rem">Email</td>
                        <td style="padding:0.7rem 1rem"><?= htmlspecialchars($profile['email']) ?></td>
                    </tr>
                    <tr>
                        <td style="font-weight:700;color:var(--brown);padding:0.7rem 1rem">Role</td>
                        <td style="padding:0.7rem 1rem">Adopter</td>
                    </tr>
                    <tr>
                        <td style="font-weight:700;color:var(--brown);padding:0.7rem 1rem">Total Applications</td>
                        <td style="padding:0.7rem 1rem"><?= $total_my_apps ?></td>
                    </tr>
                    <tr>
                        <td style="font-weight:700;color:var(--brown);padding:0.7rem 1rem">Approved</td>
                        <td style="padding:0.7rem 1rem"><?= $total_approved ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

</div>

<!-- ══ FOOTER ══ -->
<footer style="
    background: #3E2723;
    color: #EFEBE9;
    padding: 50px 0 24px;
    margin-top: 2rem;
">
    <div style="max-width:1200px; margin:0 auto; padding:0 2.2rem;">
        <div style="display:grid; grid-template-columns:2fr 1fr 1fr 1.5fr; gap:2rem; flex-wrap:wrap;">

<!-- Brand -->
<div>
    <img src="assets/images/logo.png" alt="PawPal" style="height:80px; width:auto;">
    <p style="color:#BCAAA4; font-size:0.88rem; line-height:1.65; margin-top:0.5rem;">
        Connecting loving homes with animals in need. Every adoption changes two lives — the pet's and yours.
    </p>
    <div style="margin-top:1rem; display:flex; gap:0.5rem;">
        <a href="#" style="width:34px;height:34px;border-radius:50%;background:rgba(255,255,255,0.08);display:inline-flex;align-items:center;justify-content:center;color:#BCAAA4;text-decoration:none;transition:all 0.2s;"
           onmouseover="this.style.background='#FF7043';this.style.color='#fff'"
           onmouseout="this.style.background='rgba(255,255,255,0.08)';this.style.color='#BCAAA4'">
            <i class="bi bi-facebook"></i>
        </a>
        <a href="#" style="width:34px;height:34px;border-radius:50%;background:rgba(255,255,255,0.08);display:inline-flex;align-items:center;justify-content:center;color:#BCAAA4;text-decoration:none;"
           onmouseover="this.style.background='#FF7043';this.style.color='#fff'"
           onmouseout="this.style.background='rgba(255,255,255,0.08)';this.style.color='#BCAAA4'">
            <i class="bi bi-instagram"></i>
        </a>
        <a href="#" style="width:34px;height:34px;border-radius:50%;background:rgba(255,255,255,0.08);display:inline-flex;align-items:center;justify-content:center;color:#BCAAA4;text-decoration:none;"
           onmouseover="this.style.background='#FF7043';this.style.color='#fff'"
           onmouseout="this.style.background='rgba(255,255,255,0.08)';this.style.color='#BCAAA4'">
            <i class="bi bi-twitter-x"></i>
        </a>
        <a href="#" style="width:34px;height:34px;border-radius:50%;background:rgba(255,255,255,0.08);display:inline-flex;align-items:center;justify-content:center;color:#BCAAA4;text-decoration:none;"
           onmouseover="this.style.background='#FF7043';this.style.color='#fff'"
           onmouseout="this.style.background='rgba(255,255,255,0.08)';this.style.color='#BCAAA4'">
            <i class="bi bi-envelope-fill"></i>
        </a>
    </div>
</div>

            <!-- Quick Links -->
            <div>
                <h6 style="font-family:'Baloo 2',cursive; font-size:1rem; font-weight:700; color:#fff; margin-bottom:1rem;">Quick Links</h6>
                <ul style="list-style:none; padding:0; margin:0;">
                    <li style="margin-bottom:0.5rem"><a href="#" onclick="switchTab('home', this)" style="color:#BCAAA4;text-decoration:none;font-size:0.88rem;" onmouseover="this.style.color='#FFB347'" onmouseout="this.style.color='#BCAAA4'">🏠 Home</a></li>
                    <li style="margin-bottom:0.5rem"><a href="#" onclick="switchTab('pets', this)" style="color:#BCAAA4;text-decoration:none;font-size:0.88rem;" onmouseover="this.style.color='#FFB347'" onmouseout="this.style.color='#BCAAA4'">🔍 Browse Pets</a></li>
                    <li style="margin-bottom:0.5rem"><a href="#" onclick="switchTab('applications', this)" style="color:#BCAAA4;text-decoration:none;font-size:0.88rem;" onmouseover="this.style.color='#FFB347'" onmouseout="this.style.color='#BCAAA4'">📋 My Applications</a></li>
                    <li style="margin-bottom:0.5rem"><a href="#" onclick="switchTab('tips', this)" style="color:#BCAAA4;text-decoration:none;font-size:0.88rem;" onmouseover="this.style.color='#FFB347'" onmouseout="this.style.color='#BCAAA4'">💡 Care Tips</a></li>
                </ul>
            </div>

            <!-- Account -->
            <div>
                <h6 style="font-family:'Baloo 2',cursive; font-size:1rem; font-weight:700; color:#fff; margin-bottom:1rem;">My Account</h6>
                <ul style="list-style:none; padding:0; margin:0;">
                    <li style="margin-bottom:0.5rem"><a href="#" onclick="switchTab('profile', this)" style="color:#BCAAA4;text-decoration:none;font-size:0.88rem;" onmouseover="this.style.color='#FFB347'" onmouseout="this.style.color='#BCAAA4'">👤 My Profile</a></li>
                    <li style="margin-bottom:0.5rem"><a href="#" onclick="switchTab('applications', this)" style="color:#BCAAA4;text-decoration:none;font-size:0.88rem;" onmouseover="this.style.color='#FFB347'" onmouseout="this.style.color='#BCAAA4'">📄 Applications</a></li>
                    <li style="margin-bottom:0.5rem"><a href="auth/logout.php" style="color:#BCAAA4;text-decoration:none;font-size:0.88rem;" onmouseover="this.style.color='#ef9a9a'" onmouseout="this.style.color='#BCAAA4'">🚪 Log Out</a></li>
                </ul>
            </div>

            <!-- Contact -->
            <div>
                <h6 style="font-family:'Baloo 2',cursive; font-size:1rem; font-weight:700; color:#fff; margin-bottom:1rem;">Contact Us</h6>
                <ul style="list-style:none; padding:0; margin:0;">
                    <li style="margin-bottom:0.6rem; display:flex; align-items:flex-start; gap:0.5rem;">
                        <i class="bi bi-geo-alt-fill" style="color:#FFB347; margin-top:2px; flex-shrink:0;"></i>
                        <span style="color:#BCAAA4; font-size:0.88rem;">123 Shelter St., Manila, PH</span>
                    </li>
                    <li style="margin-bottom:0.6rem; display:flex; align-items:center; gap:0.5rem;">
                        <i class="bi bi-telephone-fill" style="color:#FFB347; flex-shrink:0;"></i>
                        <span style="color:#BCAAA4; font-size:0.88rem;">+63 912 345 6789</span>
                    </li>
                    <li style="margin-bottom:0.6rem; display:flex; align-items:center; gap:0.5rem;">
                        <i class="bi bi-envelope-fill" style="color:#FFB347; flex-shrink:0;"></i>
                        <span style="color:#BCAAA4; font-size:0.88rem;">hello@pawpal.ph</span>
                    </li>
                    <li style="display:flex; align-items:center; gap:0.5rem;">
                        <i class="bi bi-clock-fill" style="color:#FFB347; flex-shrink:0;"></i>
                        <span style="color:#BCAAA4; font-size:0.88rem;">Mon–Sat, 8AM – 5PM</span>
                    </li>
                </ul>
            </div>

        </div>

        <!-- Divider -->
        <hr style="border-color:rgba(255,255,255,0.1); margin:2rem 0 1.2rem;">

        <!-- Bottom bar -->
        <div style="display:flex; justify-content:space-between; flex-wrap:wrap; gap:0.5rem; font-size:0.82rem; color:#8D6E63;">
            <span>© <?php echo date('Y'); ?> PawPal. All rights reserved.</span>
            <span>Made with 🧡 for animals everywhere</span>
        </div>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>

function switchTab(name, linkEl) {
    const current = document.querySelector('.tab-panel.active');
    const target  = document.getElementById('tab-' + name);
    if (current === target) return false;

    current.classList.remove('active');
    // slight delay so the fade-in re-triggers
    setTimeout(() => {
        target.classList.add('active');
    }, 50);

    document.querySelectorAll('.nav-links a').forEach(a => a.classList.remove('active'));
    if (linkEl) linkEl.classList.add('active');
    return false;
}

/* Filter state */
const filters = { species: '', gender: '', age: '', search: '' };
 
function setChip(type, value, el) {
    filters[type] = value;
    // update active chip in the group
    el.closest('.chip-group').querySelectorAll('.chip').forEach(c => c.classList.remove('active'));
    el.classList.add('active');
    applyFilters();
}
 
function clearSearch() {
    document.getElementById('pet-search').value = '';
    filters.search = '';
    document.getElementById('search-clear').style.display = 'none';
    applyFilters();
}
 
function clearAllFilters() {
    // reset search
    document.getElementById('pet-search').value = '';
    filters.search = '';
    // reset chips
    ['species','gender','age'].forEach(type => {
        filters[type] = '';
        document.querySelectorAll(`#${type}-chips .chip`).forEach((c,i) => {
            c.classList.toggle('active', i === 0);
        });
    });
    // reset sort
    document.getElementById('sort-select').value = 'newest';
    document.getElementById('search-clear').style.display = 'none';
    applyFilters();
}
 
function ageCategory(months) {
    if (months < 6)   return 'baby';
    if (months < 24)  return 'young';
    if (months < 84)  return 'adult';
    return 'senior';
}
 
function applyFilters() {
    const q      = document.getElementById('pet-search').value.trim().toLowerCase();
    const sort   = document.getElementById('sort-select').value;
    filters.search = q;
 
    // show/hide clear-search button
    document.getElementById('search-clear').style.display = q ? 'block' : 'none';
 
    const cards  = Array.from(document.querySelectorAll('#pet-grid .pet-card'));
    let visible  = [];
 
    cards.forEach(card => {
        const name    = card.dataset.name   || '';
        const breed   = card.dataset.breed  || '';
        const species = card.dataset.species || '';
        const gender  = card.dataset.gender || '';
        const ageMon  = parseInt(card.dataset.ageMonths || '0', 10);
 
        let show = true;
 
        // search
        if (q && !name.includes(q) && !breed.includes(q) && !species.toLowerCase().includes(q)) {
            show = false;
        }
        // species filter (Other = not Dog & not Cat)
        if (filters.species) {
            if (filters.species === 'Other') {
                if (species === 'Dog' || species === 'Cat') show = false;
            } else {
                if (species !== filters.species) show = false;
            }
        }
        // gender
        if (filters.gender && gender !== filters.gender) show = false;
        // age
        if (filters.age && ageCategory(ageMon) !== filters.age) show = false;
 
        card.classList.toggle('hidden', !show);
        if (show) visible.push(card);
    });
 
    // Sort visible cards
    const grid = document.getElementById('pet-grid');
    visible.sort((a, b) => {
        if (sort === 'newest')  return parseInt(b.dataset.created) - parseInt(a.dataset.created);
        if (sort === 'oldest')  return parseInt(a.dataset.created) - parseInt(b.dataset.created);
        if (sort === 'name_az') return a.dataset.name.localeCompare(b.dataset.name);
        if (sort === 'name_za') return b.dataset.name.localeCompare(a.dataset.name);
        if (sort === 'age_asc') return parseInt(a.dataset.ageMonths) - parseInt(b.dataset.ageMonths);
        if (sort === 'age_desc')return parseInt(b.dataset.ageMonths) - parseInt(a.dataset.ageMonths);
        return 0;
    });
 
    // Re-append in sorted order (hidden cards stay but won't display)
    visible.forEach(c => grid.appendChild(c));
 
    // Update results label
    const total = cards.length;
    const shown = visible.length;
    const countEl = document.getElementById('results-count');
    countEl.textContent = shown === total
        ? `${total} pet${total !== 1 ? 's' : ''} available`
        : `${shown} of ${total} pet${total !== 1 ? 's' : ''} shown`;
 
    // Show/hide "no results"
    document.getElementById('no-results').style.display = shown === 0 ? 'block' : 'none';
    grid.style.display = shown === 0 ? 'none' : 'grid';
 
    // Show/hide "clear all" button
    const hasFilter = q || filters.species || filters.gender || filters.age || sort !== 'newest';
    document.getElementById('clear-all-btn').style.display = hasFilter ? 'inline-flex' : 'none';
}
 
// Initialise count on page load
document.addEventListener('DOMContentLoaded', () => applyFilters());
// Also re-run when the Pets tab is switched to
const origSwitch = window.switchTab;
window.switchTab = function(name, el) {
    origSwitch && origSwitch(name, el);
    if (name === 'pets') setTimeout(applyFilters, 60);
};
// Footer reveal on scroll
const footer = document.querySelector('footer');
const footerObserver = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
        if (entry.isIntersecting) {
            footer.classList.add('footer-visible');
        }
    });
}, { threshold: 0.1 });

footerObserver.observe(footer);

// ── Toast ──────────────────────────────────────────────────────
function showToast(msg, type = 'success') {
    const wrap = document.getElementById('toast-wrap');
    const el = document.createElement('div');
    el.className = 'toast-msg' + (type === 'error' ? ' error' : type === 'success' ? ' success' : '');
    el.innerHTML = (type === 'success' ? '✅ ' : '❌ ') + msg;
    wrap.appendChild(el);
    setTimeout(() => el.remove(), 3000);
}

// ── Favourite toggle ───────────────────────────────────────────
function toggleFav(e, btn, petId) {
    e.stopPropagation();
    e.preventDefault();
    const wasActive = btn.classList.contains('active');
    document.querySelectorAll('.fav-btn[data-pet-id="' + petId + '"], .qv-btn-fav[data-pet-id="' + petId + '"]')
        .forEach(b => b.classList.toggle('active', !wasActive));
    fetch('dashboard.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=toggle_favorite&pet_id=' + petId
    })
    .then(r => r.json())
    .then(d => {
        showToast(d.status === 'added' ? 'Added to wishlist ❤️' : 'Removed from wishlist');
    })
    .catch(() => {
        document.querySelectorAll('.fav-btn[data-pet-id="' + petId + '"], .qv-btn-fav[data-pet-id="' + petId + '"]')
            .forEach(b => b.classList.toggle('active', wasActive));
        showToast('Something went wrong', 'error');
    });
}

// ── Quick-view modal ───────────────────────────────────────────
function openQuickView(btn) {
    event.stopPropagation();
    event.preventDefault();
    const card = btn.closest('.pet-card');
    const pet  = JSON.parse(card.dataset.pet);
    const isFav = card.querySelector('.fav-btn').classList.contains('active');
    const gender = pet.gender === 'male' ? '♂ Male' : '♀ Female';
    const age    = pet.age_years + 'y ' + pet.age_months + 'm';

    let badges = '<span style="background:#E8F5E9;color:#2E7D32;font-size:.72rem;font-weight:800;padding:.2rem .6rem;border-radius:50px;">✅ Available</span>';
    if (pet.is_vaccinated == 1)  badges += ' <span style="background:#E3F2FD;color:#1565C0;font-size:.72rem;font-weight:800;padding:.2rem .6rem;border-radius:50px;">💉 Vaccinated</span>';
    if (pet.is_neutered == 1)    badges += ' <span style="background:#F3E5F5;color:#6A1B9A;font-size:.72rem;font-weight:800;padding:.2rem .6rem;border-radius:50px;">✂️ Neutered</span>';
    if (pet.good_with_kids == 1) badges += ' <span style="background:#FFF8E1;color:#E65100;font-size:.72rem;font-weight:800;padding:.2rem .6rem;border-radius:50px;">👶 Good with Kids</span>';

    const imgHtml = pet.image_path
        ? `<img src="${pet.image_path}" alt="${pet.name}">`
        : (pet.species === 'Cat' ? '🐱' : (pet.species === 'Dog' ? '🐶' : '🐾'));

    document.getElementById('qv-content').innerHTML = `
        <button class="qv-close" onclick="document.getElementById('qv-overlay').classList.remove('open');document.body.style.overflow=''">
            <i class="bi bi-x-lg"></i>
        </button>
        <div class="qv-img-wrap">${imgHtml}</div>
        <div class="qv-body">
            <div class="qv-name">${pet.name}</div>
            <div class="qv-meta">${pet.species} · ${pet.breed || 'Mixed'} · ${gender} · ${age} old</div>
            <div style="display:flex;flex-wrap:wrap;gap:.4rem;margin-bottom:.9rem">${badges}</div>
            ${pet.description ? `<div class="qv-desc">${pet.description}</div>` : ''}
            <div class="qv-info-grid">
                <div class="qv-info-item"><div class="qv-info-label">Species</div><div class="qv-info-value">${pet.species}</div></div>
                <div class="qv-info-item"><div class="qv-info-label">Breed</div><div class="qv-info-value">${pet.breed || 'Mixed'}</div></div>
                <div class="qv-info-item"><div class="qv-info-label">Gender</div><div class="qv-info-value">${gender}</div></div>
                <div class="qv-info-item"><div class="qv-info-label">Age</div><div class="qv-info-value">${age}</div></div>
            </div>
            <div class="qv-actions">
                <a href="adopt.php?pet_id=${pet.pet_id}" class="qv-btn-adopt"><i class="bi bi-heart-fill"></i> Adopt Me</a>
                <button class="qv-btn-fav ${isFav ? 'active' : ''}" data-pet-id="${pet.pet_id}"
                        onclick="toggleFav(event, this, ${pet.pet_id})">
                    <i class="bi bi-heart-fill"></i>
                </button>
            </div>
        </div>`;

    document.getElementById('qv-overlay').classList.add('open');
    document.body.style.overflow = 'hidden';
}

function closeQuickView(e) {
    if (e && e.target !== document.getElementById('qv-overlay')) return;
    document.getElementById('qv-overlay').classList.remove('open');
    document.body.style.overflow = '';
}

// Escape key closes modal
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
        document.getElementById('qv-overlay').classList.remove('open');
        document.body.style.overflow = '';
    }
});

// ── Cancel application ─────────────────────────────────────────
function cancelApplication(appId, btn) {
    if (!confirm('Cancel this application? This cannot be undone.')) return;
    btn.disabled = true;
    btn.innerHTML = '<i class="bi bi-hourglass-split"></i> Cancelling…';
    fetch('dashboard.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=cancel_application&application_id=' + appId
    })
    .then(r => r.json())
    .then(d => {
        if (d.status === 'cancelled') {
            showToast('Application cancelled');
            const row = document.getElementById('app-row-' + appId);
            const badge = row.querySelector('[class^="badge-"]');
            badge.className = 'badge-cancelled';
            badge.textContent = 'Cancelled';
            btn.parentElement.innerHTML = '<span style="color:#D7CCC8;font-size:.8rem">—</span>';
            const steps = row.querySelectorAll('.tl-step');
            if (steps[1]) {
                steps[1].className = 'tl-step cancelled';
                steps[1].querySelector('.tl-dot').innerHTML = '<i class="bi bi-x-lg" style="font-size:.55rem"></i>';
            }
            if (steps[2]) {
                steps[2].className = 'tl-step cancelled';
                steps[2].querySelector('.tl-dot').innerHTML = '<i class="bi bi-x-lg" style="font-size:.55rem"></i>';
            }
        } else {
            showToast('Could not cancel', 'error');
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-x-circle"></i> Cancel';
        }
    })
    .catch(() => {
        showToast('Something went wrong', 'error');
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-x-circle"></i> Cancel';
    });
}

</script>
</body>
</html>