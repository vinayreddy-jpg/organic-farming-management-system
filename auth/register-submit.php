<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('auth/register.php');
}

require_csrf();

$username = trim($_POST['username'] ?? '');
$district = trim($_POST['district'] ?? '');
$village = trim($_POST['village'] ?? '');
$mobile = trim($_POST['mobile'] ?? '');
$password = $_POST['password'] ?? '';
$confirmPassword = $_POST['confirm_password'] ?? '';

// Validate required fields
if (
    $username === '' ||
    $district === '' ||
    $village === '' ||
    $mobile === '' ||
    $password === '' ||
    $confirmPassword === ''
) {
    flash('error', 'Please fill in all registration details.');
    redirect('auth/register.php');
}

// Validate username
if (!preg_match('/^[a-zA-Z0-9_]{3,50}$/', $username)) {
    flash('error', 'Username must contain 3-50 letters, numbers or underscores.');
    redirect('auth/register.php');
}

// Validate mobile
if (!preg_match('/^[6-9][0-9]{9}$/', $mobile)) {
    flash('error', 'Enter a valid 10-digit mobile number.');
    redirect('auth/register.php');
}

// Validate password
if (strlen($password) < 8) {
    flash('error', 'Password must contain at least 8 characters.');
    redirect('auth/register.php');
}

if ($password !== $confirmPassword) {
    flash('error', 'Password and confirm password do not match.');
    redirect('auth/register.php');
}

try {
    $pdo = db();

    // Check duplicate username or mobile
    $check = $pdo->prepare(
        'SELECT id FROM users
         WHERE username = ? OR mobile = ?
         LIMIT 1'
    );

    $check->execute([$username, $mobile]);

    if ($check->fetch()) {
        flash('error', 'Username or mobile number is already registered.');
        redirect('auth/register.php');
    }

    // Secure password hashing
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

    flash('success', 'Farmer account created successfully. Please log in.');
    redirect('auth/login.php');

} catch (PDOException $e) {
    error_log('Registration error: ' . $e->getMessage());

    flash('error', 'Unable to create your account. Please try again.');
    redirect('auth/register.php');
}
