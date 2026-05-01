<?php
session_start();
require_once 'config/database.php';

// Auth check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'adopter') {
    header('Location: auth/login.php');
    exit;
}

$user_id   = $_SESSION['user_id'];
$full_name = $_SESSION['full_name'];
$error     = '';
$success   = '';

// Get pet_id
$pet_id = isset($_GET['pet_id']) && is_numeric($_GET['pet_id']) ? (int)$_GET['pet_id'] : 0;
if (!$pet_id) {
    header('Location: dashboard.php');
    exit;
}

// Fetch pet
$stmt = mysqli_prepare($conn, "SELECT * FROM pets WHERE pet_id = ? AND status = 'available'");
mysqli_stmt_bind_param($stmt, 'i', $pet_id);
mysqli_stmt_execute($stmt);
$pet = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
if (!$pet) {
    header('Location: dashboard.php');
    exit;
}

// Check existing application
$stmt2 = mysqli_prepare($conn, "SELECT application_id, status FROM adoption_applications WHERE user_id = ? AND pet_id = ? AND status IN ('pending','approved')");
mysqli_stmt_bind_param($stmt2, 'ii', $user_id, $pet_id);
mysqli_stmt_execute($stmt2);
$existing = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt2));
if ($existing) {
    header('Location: adopt.php?pet_id=' . $pet_id);
    exit;
}

// ── Handle form submission ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $contact_number   = trim($_POST['contact_number'] ?? '');
    $address          = trim($_POST['address'] ?? '');
    $occupation       = trim($_POST['occupation'] ?? '');
    $monthly_income   = trim($_POST['monthly_income'] ?? '');
    $living_situation = $_POST['living_situation'] ?? '';
    $ownership_status = $_POST['ownership_status'] ?? '';
    $has_existing_pets= isset($_POST['has_existing_pets']) ? 1 : 0;
    $has_children     = isset($_POST['has_children']) ? 1 : 0;
    $reason           = trim($_POST['reason'] ?? '');

    // Validate
    if (empty($contact_number) || empty($address) || empty($occupation) ||
        empty($monthly_income) || empty($living_situation) || empty($ownership_status) || empty($reason)) {
        $error = 'Please fill in all required fields.';
    } elseif (!is_numeric($monthly_income) || $monthly_income < 0) {
        $error = 'Please enter a valid monthly income.';
    } else {
        $stmt = mysqli_prepare($conn,
            "INSERT INTO adoption_applications
             (user_id, pet_id, contact_number, address, occupation, monthly_income, living_situation, ownership_status, has_existing_pets, has_children, reason)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        mysqli_stmt_bind_param($stmt, 'iisssdssiis',
            $user_id, $pet_id, $contact_number, $address, $occupation,
            $monthly_income, $living_situation, $ownership_status,
            $has_existing_pets, $has_children, $reason
        );
        if (mysqli_stmt_execute($stmt)) {
            $success = true;
        } else {
            $error = 'Could not submit application. Please try again.';
        }
    }
}

function petEmoji($species) {
    $map = ['Dog'=>'🐶','Cat'=>'🐱','Bird'=>'🐦','Rabbit'=>'🐰','Other'=>'🐾'];
    return $map[$species] ?? '🐾';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Apply for <?= htmlspecialchars($pet['name']) ?> — PawPal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Baloo+2:wght@400;600;700;800&family=Nunito:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --cream: #FFF8F0;
            --orange: #FF7043;
            --orange-dark: #E64A19;
            --brown: #6D4C41;
            --brown-light: #A1887F;
            --text-dark: #3E2723;
            --text-muted: #795548;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Nunito', sans-serif; background: var(--cream); color: var(--text-dark); min-height: 100vh; }
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
        .brand img { height: 38px; width: auto; }
        .btn-back {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            font-size: 0.88rem;
            font-weight: 700;
            color: var(--text-muted);
            text-decoration: none;
            padding: 0.45rem 1rem;
            border-radius: 8px;
            border: 1.5px solid #F0E6DE;
            background: #fff;
            transition: all 0.18s;
        }
        .btn-back:hover { border-color: var(--orange); color: var(--orange); }

        /* ── Main ── */
        .main-wrap { padding: 2.5rem 1.5rem; max-width: 780px; margin: 0 auto; }

        /* ── Pet mini card ── */
        .pet-mini {
            background: #fff;
            border: 1.5px solid #F0E6DE;
            border-radius: 16px;
            padding: 1.2rem 1.5rem;
            display: flex;
            align-items: center;
            gap: 1.2rem;
            margin-bottom: 1.8rem;
        }
        .pet-mini-img {
            width: 70px; height: 70px;
            border-radius: 12px;
            background: linear-gradient(135deg, #FFF3E0, #FCE4EC);
            display: flex; align-items: center; justify-content: center;
            font-size: 2.2rem;
            overflow: hidden;
            flex-shrink: 0;
        }
        .pet-mini-img img { width: 100%; height: 100%; object-fit: cover; }
        .pet-mini-name { font-size: 1.1rem; font-weight: 800; color: var(--text-dark); }
        .pet-mini-meta { font-size: 0.82rem; color: var(--text-muted); font-weight: 600; margin-top: 0.2rem; }

        /* ── Form card ── */
        .form-card {
            background: #fff;
            border: 1.5px solid #F0E6DE;
            border-radius: 20px;
            padding: 2rem 2.5rem;
            box-shadow: 0 4px 20px rgba(0,0,0,0.05);
        }
        .form-title {
            font-size: 1.4rem;
            font-weight: 800;
            color: var(--text-dark);
            margin-bottom: 0.3rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .form-subtitle { font-size: 0.85rem; color: var(--text-muted); font-weight: 600; margin-bottom: 1.8rem; }

        .section-divider {
            font-size: 0.72rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 1.2px;
            color: var(--orange);
            margin: 1.5rem 0 1rem;
            display: flex;
            align-items: center;
            gap: 0.6rem;
        }
        .section-divider::after {
            content: '';
            flex: 1;
            height: 1.5px;
            background: #F0E6DE;
        }

        .lbl { display: block; font-size: 0.73rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: var(--brown); margin-bottom: 0.3rem; }
        .ipt { width: 100%; border: 2px solid #E8D8CC; border-radius: 10px; padding: 0.65rem 0.9rem; font-family: 'Nunito', sans-serif; font-size: 0.9rem; color: var(--text-dark); background: #fff; outline: none; transition: border-color 0.2s; }
        .ipt:focus { border-color: var(--orange); box-shadow: 0 0 0 3px rgba(255,112,67,0.1); }
        .mb { margin-bottom: 1rem; }

        /* Checkbox toggle style */
        .check-group { display: flex; gap: 1rem; flex-wrap: wrap; }
        .check-option {
            flex: 1;
            min-width: 140px;
            border: 2px solid #E8D8CC;
            border-radius: 10px;
            padding: 0.75rem 1rem;
            display: flex;
            align-items: center;
            gap: 0.6rem;
            cursor: pointer;
            transition: all 0.18s;
            font-size: 0.88rem;
            font-weight: 700;
            color: var(--text-muted);
        }
        .check-option input { display: none; }
        .check-option:has(input:checked) {
            border-color: var(--orange);
            background: #FFF0E8;
            color: var(--orange);
        }
        .check-option .check-icon { font-size: 1.2rem; }

        /* Radio style */
        .radio-group { display: flex; gap: 0.75rem; flex-wrap: wrap; }
        .radio-option {
            flex: 1;
            min-width: 120px;
            border: 2px solid #E8D8CC;
            border-radius: 10px;
            padding: 0.65rem 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            cursor: pointer;
            transition: all 0.18s;
            font-size: 0.88rem;
            font-weight: 700;
            color: var(--text-muted);
        }
        .radio-option input { display: none; }
        .radio-option:has(input:checked) {
            border-color: var(--orange);
            background: #FFF0E8;
            color: var(--orange);
        }
        .radio-dot {
            width: 16px; height: 16px;
            border-radius: 50%;
            border: 2px solid #E8D8CC;
            flex-shrink: 0;
            transition: all 0.18s;
        }
        .radio-option:has(input:checked) .radio-dot {
            border-color: var(--orange);
            background: var(--orange);
        }

        /* Submit */
        .btn-submit {
            width: 100%;
            background: var(--orange);
            color: #fff;
            border: none;
            border-radius: 50px;
            padding: 0.9rem;
            font-weight: 800;
            font-size: 1rem;
            font-family: 'Baloo 2', cursive;
            cursor: pointer;
            box-shadow: 0 6px 20px rgba(255,112,67,0.3);
            transition: all 0.2s;
            margin-top: 0.5rem;
        }
        .btn-submit:hover { background: var(--orange-dark); transform: translateY(-1px); }

        .alert-error {
            background: #FFF0ED; border: 1.5px solid #FFCCBC; border-radius: 10px;
            color: #BF360C; padding: 0.8rem 1rem; font-size: 0.88rem;
            font-weight: 600; margin-bottom: 1.2rem;
            display: flex; gap: 0.5rem; align-items: center;
        }

        /* ── Success screen ── */
        .success-wrap {
            text-align: center;
            padding: 3rem 2rem;
        }
        .success-icon {
            font-size: 5rem;
            margin-bottom: 1rem;
            display: block;
            animation: popIn 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }
        @keyframes popIn {
            0% { transform: scale(0); opacity: 0; }
            100% { transform: scale(1); opacity: 1; }
        }
        .success-title { font-size: 1.8rem; font-weight: 800; color: var(--text-dark); margin-bottom: 0.5rem; }
        .success-text  { font-size: 0.95rem; color: var(--text-muted); font-weight: 600; line-height: 1.6; max-width: 400px; margin: 0 auto 1.8rem; }
        .btn-dashboard {
            display: inline-flex; align-items: center; gap: 0.5rem;
            background: var(--orange); color: #fff;
            border-radius: 50px; padding: 0.8rem 2rem;
            font-weight: 800; font-size: 0.95rem;
            font-family: 'Baloo 2', cursive;
            text-decoration: none;
            box-shadow: 0 6px 18px rgba(255,112,67,0.3);
            transition: all 0.2s;
        }
        .btn-dashboard:hover { background: var(--orange-dark); color: #fff; transform: translateY(-1px); }

        @media (max-width: 600px) {
            .form-card { padding: 1.5rem 1.2rem; }
            .main-wrap { padding: 1.5rem 1rem; }
        }

        /* ── Page Transition ── */
        body {
            animation: slideInFromRight 0.4s cubic-bezier(0.25, 0.46, 0.45, 0.94) both;
        }
        @keyframes slideInFromRight {
            from { opacity: 0; transform: translateX(50px); }
            to   { opacity: 1; transform: translateX(0); }
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
    <a href="adopt.php?pet_id=<?= $pet_id ?>" class="btn-back"><i class="bi bi-arrow-left"></i> Back to Pet</a>
</nav>

<div class="main-wrap">

    <?php if ($success): ?>
    <!-- ── Success State ── -->
    <div class="form-card">
        <div class="success-wrap">
            <span class="success-icon">🎉</span>
            <div class="success-title">Application Submitted!</div>
            <div class="success-text">
                Your application for <strong><?= htmlspecialchars($pet['name']) ?></strong> has been received.
                Our team will review it and get back to you soon. Thank you for choosing to adopt!
            </div>
            <a href="dashboard.php" class="btn-dashboard"><i class="bi bi-house-heart-fill"></i> Back to Dashboard</a>
        </div>
    </div>

    <?php else: ?>

    <!-- ── Pet Mini Card ── -->
    <div class="pet-mini">
        <div class="pet-mini-img">
            <?php if (!empty($pet['image_path']) && file_exists($pet['image_path'])): ?>
                <img src="<?= htmlspecialchars($pet['image_path']) ?>" alt="<?= htmlspecialchars($pet['name']) ?>">
            <?php else: ?>
                <?= petEmoji($pet['species']) ?>
            <?php endif; ?>
        </div>
        <div>
            <div class="pet-mini-name">Adopting: <?= htmlspecialchars($pet['name']) ?></div>
            <div class="pet-mini-meta">
                <?= htmlspecialchars($pet['species']) ?>
                <?= !empty($pet['breed']) ? ' · ' . htmlspecialchars($pet['breed']) : '' ?>
                · <?= ucfirst($pet['gender']) ?>
            </div>
        </div>
    </div>

    <!-- ── Form ── -->
    <div class="form-card">
        <div class="form-title"><i class="bi bi-heart-fill" style="color:var(--orange)"></i> Adoption Application</div>
        <div class="form-subtitle">Please fill out the form honestly. All information helps us find the best match for our pets.</div>

        <?php if ($error): ?>
        <div class="alert-error"><i class="bi bi-exclamation-circle-fill"></i><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST">

            <!-- Contact Info -->
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

            <!-- Background -->
            <div class="section-divider">💼 Personal Background</div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem" class="mb">
                <div>
                    <label class="lbl">Occupation *</label>
                    <input type="text" class="ipt" name="occupation" placeholder="e.g. Teacher, Engineer"
                           value="<?= htmlspecialchars($_POST['occupation'] ?? '') ?>" required>
                </div>
                <div>
                    <label class="lbl">Monthly Income (₱) *</label>
                    <input type="number" class="ipt" name="monthly_income" min="0" step="0.01"
                           placeholder="e.g. 25000"
                           value="<?= htmlspecialchars($_POST['monthly_income'] ?? '') ?>" required>
                </div>
            </div>

            <!-- Living Situation -->
            <div class="section-divider">🏠 Living Situation</div>

            <div class="mb">
                <label class="lbl">Type of Home *</label>
                <div class="radio-group">
                    <label class="radio-option">
                        <input type="radio" name="living_situation" value="house" <?= ($_POST['living_situation'] ?? '') === 'house' ? 'checked' : '' ?> required>
                        <span class="radio-dot"></span> 🏡 House
                    </label>
                    <label class="radio-option">
                        <input type="radio" name="living_situation" value="apartment" <?= ($_POST['living_situation'] ?? '') === 'apartment' ? 'checked' : '' ?>>
                        <span class="radio-dot"></span> 🏢 Apartment
                    </label>
                </div>
            </div>

            <div class="mb">
                <label class="lbl">Ownership Status *</label>
                <div class="radio-group">
                    <label class="radio-option">
                        <input type="radio" name="ownership_status" value="owned" <?= ($_POST['ownership_status'] ?? '') === 'owned' ? 'checked' : '' ?> required>
                        <span class="radio-dot"></span> 🔑 Owned
                    </label>
                    <label class="radio-option">
                        <input type="radio" name="ownership_status" value="rented" <?= ($_POST['ownership_status'] ?? '') === 'rented' ? 'checked' : '' ?>>
                        <span class="radio-dot"></span> 📄 Rented
                    </label>
                </div>
            </div>

            <!-- Household -->
            <div class="section-divider">👨‍👩‍👧 Household</div>

            <div class="mb">
                <label class="lbl">Additional Information</label>
                <div class="check-group">
                    <label class="check-option">
                        <input type="checkbox" name="has_existing_pets" value="1" <?= !empty($_POST['has_existing_pets']) ? 'checked' : '' ?>>
                        <span class="check-icon">🐾</span> I have existing pets
                    </label>
                    <label class="check-option">
                        <input type="checkbox" name="has_children" value="1" <?= !empty($_POST['has_children']) ? 'checked' : '' ?>>
                        <span class="check-icon">👶</span> I have children at home
                    </label>
                </div>
            </div>

            <!-- Reason -->
            <div class="section-divider">💬 Your Story</div>

            <div class="mb">
                <label class="lbl">Why do you want to adopt <?= htmlspecialchars($pet['name']) ?>? *</label>
                <textarea class="ipt" name="reason" rows="4"
                          placeholder="Tell us about yourself and why you'd be a great match for this pet..."
                          required><?= htmlspecialchars($_POST['reason'] ?? '') ?></textarea>
            </div>

            <button type="submit" class="btn-submit">
                <i class="bi bi-send-fill me-2"></i> Submit Application
            </button>
        </form>
    </div>

    <?php endif; ?>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
