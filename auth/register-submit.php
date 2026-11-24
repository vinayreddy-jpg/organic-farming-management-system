<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('register.php');
}

require_csrf();

$username = trim($_POST['username'] ?? '');
$district = trim($_POST['district'] ?? '');
$village = trim($_POST['village'] ?? '');
$mobile = trim($_POST['mobile'] ?? '');
$password = $_POST['password'] ?? '';
$confirmPassword = $_POST['confirm_password'] ?? '';

if (
    $username === '' ||
    $district === '' ||
    $village === '' ||
    $mobile === '' ||
    $password === '' ||
    $confirmPassword === ''
) {
    fail('Please fill in all registration details.');
}

if (!preg_match('/^[a-zA-Z0-9_]{3,50}$/', $username)) {
    fail('Username must contain 3-50 letters, numbers or underscores.');
}

if (!preg_match('/^[6-9][0-9]{9}$/', $mobile)) {
    fail('Enter a valid 10-digit mobile number.');
}

if (strlen($password) < 8) {
    fail('Password must contain at least 8 characters.');
}

if ($password !== $confirmPassword) {
    fail('Password and confirm password do not match.');
}

try {
    $pdo = db();

    // Check existing username or mobile
    $check = $pdo->prepare(
        'SELECT id FROM users
         WHERE username = ? OR mobile = ?
         LIMIT 1'
    );

    $check->execute([$username, $mobile]);

    if ($check->fetch()) {
        fail('Username or mobile number is already registered.');
    }

    // Hash password securely
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

    // Create farmer account
    $stmt = $pdo->prepare(
        "INSERT INTO users
        (name, email, username, mobile, district, village, password_hash, role)
        VALUES (?, NULL, ?, ?, ?, ?, ?, 'farmer')"
    );

    $stmt->execute([
        $username,
        $username,
        $mobile,
        $district,
        $village,
        $passwordHash
    ]);

    flash(
        'success',
        'Farmer account created successfully. Please log in.'
    );

    redirect('login.php');

} catch (PDOException $e) {
    error_log('Registration error: ' . $e->getMessage());
    fail('Unable to create your account. Please try again.');
}
