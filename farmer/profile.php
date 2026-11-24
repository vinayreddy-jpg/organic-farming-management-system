<?php
declare(strict_types=1);

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/../auth/bootstrap.php';

// Check login
$user = current_user();

if (!$user) {
    redirect('../auth/login.php');
}

$pdo = db();
$userId = (int) $user['id'];

$success = '';
$error = '';

// Update profile
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $token = $_POST['csrf'] ?? '';

    if (!is_string($token) ||
        !hash_equals($_SESSION['csrf'] ?? '', $token)) {
        $error = 'Invalid security token. Refresh and try again.';
    } else {

        $name = trim($_POST['name'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $mobile = trim($_POST['mobile'] ?? '');
        $district = trim($_POST['district'] ?? '');
        $village = trim($_POST['village'] ?? '');

        if ($name === '' || $username === '' ||
            $mobile === '' || $district === '' || $village === '') {
            $error = 'Please fill in all fields.';
        } elseif (!preg_match('/^[0-9]{10}$/', $mobile)) {
            $error = 'Enter a valid 10-digit mobile number.';
        } else {
            try {
                $check = $pdo->prepare(
                    "SELECT id FROM users
                     WHERE mobile = ? AND id != ? LIMIT 1"
                );
                $check->execute([$mobile, $userId]);

                if ($check->fetch()) {
                    $error = 'This mobile number is already registered.';
                } else {
                    $update = $pdo->prepare(
                        "UPDATE users
                         SET name = ?, username = ?, mobile = ?,
                             district = ?, village = ?
                         WHERE id = ?"
                    );

                    $update->execute([
                        $name, $username, $mobile,
                        $district, $village, $userId
                    ]);

                    $_SESSION['user']['name'] = $name;
                    $_SESSION['user']['username'] = $username;
                    $_SESSION['user']['mobile'] = $mobile;

                    $success = 'Your profile has been updated successfully!';
                }
            } catch (PDOException $ex) {
                $error = 'Unable to update your profile. Please try again.';
            }
        }
    }
}

// Get latest profile details
$stmt = $pdo->prepare(
    "SELECT name, username, mobile, district, village, role
     FROM users WHERE id = ? LIMIT 1"
);
$stmt->execute([$userId]);
$profile = $stmt->fetch();

if (!$profile) {
    exit('Profile not found.');
}

function profileValue(array $profile, string $field): string {
    return htmlspecialchars(
        (string)($profile[$field] ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>My Profile | Organic Farming</title>

<style>
    .back-btn {
    display: inline-block;
    background: #194d30;
    color: #ffffff !important;
    padding: 11px 18px;
    border-radius: 9px;
    text-decoration: none !important;
    font-weight: 600;
    transition: background 0.2s ease;
}

.back-btn:hover {
    background: #267447;
    color: #ffffff !important;
}
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: Arial, sans-serif;
    background: #f3f7f2;
    color: #263b2d;
    padding: 35px 20px;
}

.container {
    max-width: 950px;
    margin: auto;
}

.header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 25px;
    gap: 15px;
}

.header h1 {
    color: #194d30;
    font-size: 27px;
}

.header p {
    color: #78877b;
    margin-top: 7px;
}

.back {
    text-decoration: none;
    background: #194d30;
    color: white;
    padding: 12px 18px;
    border-radius: 9px;
    font-size: 14px;
}

.card {
    background: white;
    border-radius: 16px;
    padding: 30px;
    box-shadow: 0 5px 20px #00000009;
    border: 1px solid #e8eee7;
}

.profile-heading {
    display: flex;
    align-items: center;
    gap: 18px;
    padding-bottom: 25px;
    border-bottom: 1px solid #edf1eb;
    margin-bottom: 25px;
}

.avatar {
    width: 75px;
    height: 75px;
    background: #e8f4e9;
    color: #24713f;
    border-radius: 18px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 34px;
}

.profile-heading h2 {
    color: #194d30;
    margin-bottom: 7px;
}

.profile-heading p {
    color: #78877b;
    font-size: 14px;
}

.section-title {
    color: #245c37;
    font-size: 18px;
    margin-bottom: 20px;
}

.form-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 22px;
}

.field {
    display: flex;
    flex-direction: column;
    gap: 9px;
}

.field label {
    font-size: 13px;
    font-weight: bold;
    color: #536557;
}

.field input {
    width: 100%;
    padding: 14px;
    border: 1px solid #dce6dc;
    border-radius: 9px;
    outline: none;
    font-size: 14px;
    color: #263b2d;
}

.field input:focus {
    border-color: #32834a;
    box-shadow: 0 0 0 3px #32834a18;
}

.actions {
    display: flex;
    justify-content: flex-end;
    margin-top: 30px;
}

.save-btn {
    background: #194d30;
    color: white;
    border: none;
    padding: 14px 25px;
    border-radius: 9px;
    cursor: pointer;
    font-size: 14px;
    font-weight: bold;
}

.save-btn:hover {
    background: #32834a;
}

.message {
    padding: 13px 16px;
    border-radius: 9px;
    margin-bottom: 20px;
    font-size: 14px;
}

.success {
    background: #e8f6eb;
    color: #24713f;
}

.error {
    background: #fff0f0;
    color: #c03939;
}

@media(max-width: 650px) {
    .form-grid {
        grid-template-columns: 1fr;
    }

    .header {
        align-items: flex-start;
        flex-direction: column;
    }

    .card {
        padding: 20px;
    }
}
</style>
<link rel="stylesheet" href="/organic-farming/assets/css/mobile-responsive.css">
</head>

<body>

<div class="container">

    <div class="header">
        <div>
            <h1>My Profile</h1>
            <p>Manage your personal information</p>
        </div>

        <a href="../dashboard.php" class="back-btn">← Dashboard</a>
        </a>
    </div>

    <div class="card">

        <div class="profile-heading">
            <div class="avatar">👨‍🌾</div>
            <div>
                <h2><?= profileValue($profile, 'name') ?></h2>
                <p>Farmer Account</p>
            </div>
        </div>

        <?php if ($success): ?>
            <div class="message success">
                <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="message error">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <h3 class="section-title">Personal Information</h3>

        <form method="POST">

            <input type="hidden" name="csrf"
                   value="<?= htmlspecialchars(csrf()) ?>">

            <div class="form-grid">

                <div class="field">
                    <label>Full Name</label>
                    <input type="text" name="name"
                           value="<?= profileValue($profile, 'name') ?>"
                           required>
                </div>

                <div class="field">
                    <label>Username</label>
                    <input type="text" name="username"
                           value="<?= profileValue($profile, 'username') ?>"
                           required>
                </div>

                <div class="field">
                    <label>Mobile Number</label>
                    <input type="tel" name="mobile"
                           maxlength="10"
                           pattern="[0-9]{10}"
                           value="<?= profileValue($profile, 'mobile') ?>"
                           required>
                </div>

                <div class="field">
                    <label>District</label>
                    <input type="text" name="district"
                           value="<?= profileValue($profile, 'district') ?>"
                           required>
                </div>

                <div class="field">
                    <label>Village</label>
                    <input type="text" name="village"
                           value="<?= profileValue($profile, 'village') ?>"
                           required>
                </div>

                <div class="field">
                    <label>Account Type</label>
                    <input type="text"
                           value="Farmer"
                           readonly>
                </div>

            </div>

            <div class="actions">
                <button type="submit" class="save-btn">
                    Save Changes
                </button>
            </div>

        </form>

    </div>
</div>

</body>
</html>
